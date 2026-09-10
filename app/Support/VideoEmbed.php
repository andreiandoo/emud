<?php

namespace App\Support;

/**
 * Turns a video link an operator pasted into something an <iframe> can load.
 *
 * People paste whatever the browser or the share button gave them — a watch URL, a youtu.be
 * short link, a Shorts link, a Vimeo page — and none of those render in a frame. Anything not
 * recognised returns null, so the caller shows a plain link rather than an empty black box.
 */
final class VideoEmbed
{
    public static function url(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $id = self::youtubeId($url);

        if ($id !== null) {
            // rel=0 keeps the end screen inside the channel; no autoplay, because a video that
            // starts on its own on a product page is the fastest way to lose the visitor.
            return 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0';
        }

        $id = self::vimeoId($url);

        return $id === null ? null : 'https://player.vimeo.com/video/'.$id;
    }

    /** A still frame for the video, used as the poster before anyone presses play. */
    public static function youtubeThumbnail(?string $url): ?string
    {
        $id = self::youtubeId(trim((string) $url));

        return $id === null ? null : 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
    }

    private static function youtubeId(string $url): ?string
    {
        $patterns = [
            '#youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})#i',
            '#youtu\.be/([A-Za-z0-9_-]{6,})#i',
            '#youtube\.com/embed/([A-Za-z0-9_-]{6,})#i',
            '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function vimeoId(string $url): ?string
    {
        return preg_match('#vimeo\.com/(?:video/)?(\d{6,})#i', $url, $matches) === 1 ? $matches[1] : null;
    }
}
