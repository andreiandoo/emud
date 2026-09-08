<?php

namespace App\Content;

/**
 * Turns a pasted video URL into an embed.
 *
 * Only YouTube and Vimeo are recognised, and the result is always built from the extracted id
 * rather than from the URL the author supplied. Passing a URL through would let anything be
 * framed into an article, which is a way to put arbitrary third-party content — or a login
 * form — inside a page customers trust.
 *
 * YouTube resolves to youtube-nocookie.com: an article should not set advertising cookies on
 * a reader who only scrolled past a video.
 */
final readonly class VideoEmbed
{
    private function __construct(
        public string $provider,
        public string $videoId,
        public string $embedUrl,
    ) {}

    public static function fromUrl(?string $url): ?self
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $url, $matches)) {
            return new self('youtube', $matches[1], 'https://www.youtube-nocookie.com/embed/'.$matches[1]);
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~i', $url, $matches)) {
            return new self('vimeo', $matches[1], 'https://player.vimeo.com/video/'.$matches[1]);
        }

        return null;
    }
}
