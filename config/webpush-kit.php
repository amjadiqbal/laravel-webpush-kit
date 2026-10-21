<?php

return [

    /*
     * Routes that save and delete the signed-in user's push subscriptions.
     * The user model must use NotificationChannels\WebPush\HasPushSubscriptions.
     */
    'routes' => [
        'prefix' => 'webpush',
        'middleware' => ['web', 'auth'],
        'domain' => null,
    ],

    /*
     * The auth guard the subscribe button checks. Null uses the default guard.
     */
    'guard' => null,

    /*
     * The service worker. It is served by a route at this path unless a file with the same
     * name exists in public/ (php artisan vendor:publish --tag=webpush-kit-service-worker).
     * Set 'register' to false when your app already has a service worker at this scope and
     * add importScripts('/webpush-sw.js') to it instead.
     */
    'service_worker' => [
        'register' => true,
        'path' => 'webpush-sw.js',
        'scope' => '/',
    ],

    /*
     * A web app manifest. iPhone and iPad only allow web push for a site added to the
     * Home Screen, and only with a manifest whose display is standalone or fullscreen.
     * Leave this off if your app already ships its own manifest.
     */
    'manifest' => [
        'enabled' => env('WEBPUSH_KIT_MANIFEST', false),
        'name' => env('APP_NAME', 'Laravel'),
        'short_name' => null,
        'start_url' => '/',
        'display' => 'standalone',
        'theme_color' => '#0f172a',
        'background_color' => '#ffffff',
        'icons' => [
            // ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            // ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ],

    /*
     * The model push:test sends to. Null uses the model of the "users" auth provider.
     */
    'notifiable' => null,

];
