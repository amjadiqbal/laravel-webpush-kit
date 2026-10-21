<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit;

use Composer\InstalledVersions;
use Illuminate\Support\Str;
use Throwable;

final class WebPushKit
{
    public const SUPPORT_URL = 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-webpush-kit';

    public static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('amjadiqbal/laravel-webpush-kit') ?? 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }

    public static function vapidPublicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public static function vapidConfigured(): bool
    {
        $private = config('webpush.vapid.private_key');
        $pem = config('webpush.vapid.pem_file');

        return self::vapidPublicKey() !== null && ((is_string($private) && $private !== '') || (is_string($pem) && $pem !== ''));
    }

    /**
     * Problems with VAPID_SUBJECT that make a push service reject the JWT.
     * Apple answers 403 BadJwtToken when the subject isn't a URL or mailto:.
     *
     * @return list<string>
     */
    public static function subjectProblems(): array
    {
        $subject = config('webpush.vapid.subject');

        if (! is_string($subject) || trim($subject) === '') {
            return ['VAPID_SUBJECT is empty, so the channel sends APP_URL ('.url('/').'). Set it to mailto:you@yourdomain.com or an https:// URL.'];
        }

        $subject = trim($subject);
        $problems = [];

        if (Str::startsWith($subject, 'mailto:')) {
            $address = substr($subject, 7);

            if ($address === '' || preg_match('/[\s<>]/', $address) === 1 || ! str_contains($address, '@')) {
                $problems[] = 'VAPID_SUBJECT must be a plain address like mailto:you@yourdomain.com (no spaces or angle brackets).';
            }

            $host = Str::after($address, '@');
        } elseif (Str::startsWith($subject, 'https://')) {
            $host = (string) parse_url($subject, PHP_URL_HOST);
        } else {
            return ['VAPID_SUBJECT must start with mailto: or https:// (RFC 8292). Safari rejects anything else with 403 BadJwtToken.'];
        }

        if ($host === 'localhost' || ! str_contains($host, '.') || Str::endsWith($host, ['.local', '.localhost', '.test'])) {
            $problems[] = "VAPID_SUBJECT uses \"{$host}\". Use a real public domain: Apple's push service may reject local hosts.";
        }

        return $problems;
    }

    public static function scriptUrl(): string
    {
        return route('webpush-kit.script', ['v' => self::assetVersion('webpush-kit.js')]);
    }

    /**
     * The service worker URL, with the settings it needs in its query string so the same
     * file works when served by the route or published to public/.
     */
    public static function serviceWorkerUrl(): string
    {
        $query = http_build_query(array_filter([
            'rotate' => route('webpush-kit.subscriptions.rotate', absolute: false),
            'key' => self::vapidPublicKey(),
            'v' => self::assetVersion('webpush-sw.js'),
        ]));

        return '/'.ltrim((string) config('webpush-kit.service_worker.path', 'webpush-sw.js'), '/').'?'.$query;
    }

    public static function assetPath(string $file): string
    {
        return dirname(__DIR__).'/resources/js/'.$file;
    }

    private static function assetVersion(string $file): string
    {
        $mtime = @filemtime(self::assetPath($file));

        return substr(md5(self::version().'|'.($mtime === false ? '0' : (string) $mtime)), 0, 8);
    }
}
