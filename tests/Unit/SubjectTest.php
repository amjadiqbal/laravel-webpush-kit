<?php

use AmjadIqbal\WebPushKit\WebPushKit;

it('accepts mailto and https subjects on public domains', function (string $subject) {
    config(['webpush.vapid.subject' => $subject]);

    expect(WebPushKit::subjectProblems())->toBe([]);
})->with(['mailto:push@example.com', 'https://example.com', 'https://shop.example.co.uk/contact']);

it('flags subjects push services reject', function (?string $subject, string $problem) {
    config(['webpush.vapid.subject' => $subject]);

    expect(implode(' ', WebPushKit::subjectProblems()))->toContain($problem);
})->with([
    'empty' => [null, 'VAPID_SUBJECT is empty'],
    'no scheme' => ['push@example.com', 'must start with mailto: or https://'],
    'http' => ['http://example.com', 'must start with mailto: or https://'],
    'brackets' => ['mailto:<push@example.com>', 'no spaces or angle brackets'],
    'localhost' => ['https://localhost', 'real public domain'],
    'dot test' => ['mailto:me@shop.test', 'real public domain'],
]);

it('builds a service worker url that carries its settings', function () {
    $url = WebPushKit::serviceWorkerUrl();

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect(parse_url($url, PHP_URL_PATH))->toBe('/webpush-sw.js')
        ->and($query['rotate'])->toBe('/webpush/subscriptions/rotate')
        ->and($query['key'])->toStartWith('BNcR');
});
