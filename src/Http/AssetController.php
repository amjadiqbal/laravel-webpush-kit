<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit\Http;

use AmjadIqbal\WebPushKit\WebPushKit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AssetController
{
    public function script(): Response
    {
        return $this->javascript('webpush-kit.js', 'public, max-age=31536000, immutable');
    }

    /**
     * Served with no-cache so browsers pick up a new version on the next update check.
     */
    public function serviceWorker(): Response
    {
        return $this->javascript('webpush-sw.js', 'no-cache')
            ->header('Service-Worker-Allowed', '/'.ltrim((string) config('webpush-kit.service_worker.scope', '/'), '/'));
    }

    public function manifest(): JsonResponse
    {
        abort_unless((bool) config('webpush-kit.manifest.enabled'), 404);

        /** @var array<string, mixed> $config */
        $config = config('webpush-kit.manifest', []);

        $manifest = array_filter([
            'name' => $config['name'] ?? config('app.name'),
            'short_name' => $config['short_name'] ?? null,
            'start_url' => $config['start_url'] ?? '/',
            'scope' => '/',
            'display' => $config['display'] ?? 'standalone',
            'theme_color' => $config['theme_color'] ?? null,
            'background_color' => $config['background_color'] ?? null,
            'icons' => $config['icons'] ?? [],
        ], fn ($value): bool => $value !== null && $value !== []);

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }

    private function javascript(string $file, string $cacheControl): Response
    {
        return response((string) file_get_contents(WebPushKit::assetPath($file)), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
