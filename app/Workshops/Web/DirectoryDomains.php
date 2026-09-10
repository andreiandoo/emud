<?php

namespace App\Workshops\Web;

/**
 * Which hosts are never a workshop's own website: company listings, marketplaces, maps, search
 * engines and social networks. Social profiles are still useful, as contacts of their own type.
 */
class DirectoryDomains
{
    private const SOCIAL = [
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'm.facebook.com' => 'facebook',
        'instagram.com' => 'instagram',
        'wa.me' => 'whatsapp', 'whatsapp.com' => 'whatsapp', 'api.whatsapp.com' => 'whatsapp',
    ];

    /** Mail providers: an address there says nothing about which website is the workshop's. */
    public const FREE_MAIL = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.ro', 'yahoo.co.uk', 'yahoo.es', 'yahoo.it', 'yahoo.fr',
        'yahoo.de', 'ymail.com', 'hotmail.com', 'hotmail.ro', 'outlook.com', 'live.com', 'msn.com', 'icloud.com',
        'me.com', 'aol.com', 'mail.com', 'gmx.com', 'gmx.net', 'protonmail.com', 'proton.me', 'mail.ru',
        'rdslink.ro', 'rdsmail.ro', 'rdsnet.ro', 'clicknet.ro', 'xnet.ro', 'upcmail.ro', 'k.ro', 'email.ro',
    ];

    public static function host(string $url): ?string
    {
        $url = trim($url);

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public static function isDirectory(string $host): bool
    {
        $host = preg_replace('/^www\./', '', strtolower($host)) ?? $host;

        foreach ((array) config('workshops.web.directory_domains') as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    public static function isSocial(string $host): bool
    {
        return self::socialType($host) !== 'other';
    }

    /** facebook | instagram | whatsapp | other */
    public static function socialType(string $host): string
    {
        $host = preg_replace('/^www\./', '', strtolower($host)) ?? $host;

        foreach (self::SOCIAL as $domain => $type) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $type;
            }
        }

        return 'other';
    }

    public static function isFreeMail(string $domain): bool
    {
        return in_array(strtolower($domain), self::FREE_MAIL, true);
    }
}
