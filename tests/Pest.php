<?php

use AmjadIqbal\WebPushKit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');

/**
 * @return array<string, mixed>
 */
function browserSubscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return [
        'endpoint' => $endpoint,
        'expirationTime' => null,
        'keys' => ['p256dh' => 'BPublicKeyFromTheBrowser', 'auth' => 'AuthSecret'],
        'content_encoding' => 'aes128gcm',
    ];
}
