<?php

use NotificationChannels\WebPush\PushSubscription;

it('saves the browser subscription for the signed-in user', function () {
    $user = $this->user();

    $this->actingAs($user)->postJson(route('webpush-kit.subscriptions.store'), browserSubscription())
        ->assertCreated()
        ->assertJson(['subscribed' => true]);

    $subscription = $user->pushSubscriptions()->sole();
    expect($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc123')
        ->and($subscription->public_key)->toBe('BPublicKeyFromTheBrowser')
        ->and($subscription->auth_token)->toBe('AuthSecret');
});

it('moves an endpoint to the user who subscribes with it now', function () {
    $first = $this->user('Ada');
    $second = $this->user('Grace');

    $this->actingAs($first)->postJson(route('webpush-kit.subscriptions.store'), browserSubscription())->assertCreated();
    $this->actingAs($second)->postJson(route('webpush-kit.subscriptions.store'), browserSubscription())->assertCreated();

    expect($first->pushSubscriptions()->count())->toBe(0)
        ->and($second->pushSubscriptions()->count())->toBe(1)
        ->and(PushSubscription::query()->count())->toBe(1);
});

it('rejects subscriptions that are not https push endpoints', function (array $override) {
    $this->actingAs($this->user())
        ->postJson(route('webpush-kit.subscriptions.store'), array_replace_recursive(browserSubscription(), $override))
        ->assertUnprocessable();
})->with([
    'plain http' => [['endpoint' => 'http://push.example.com/x']],
    'not a url' => [['endpoint' => 'nope']],
    'missing key' => [['keys' => ['p256dh' => '']]],
    'unknown encoding' => [['content_encoding' => 'gzip']],
]);

it('needs a signed-in user', function () {
    $this->postJson(route('webpush-kit.subscriptions.store'), browserSubscription())->assertUnauthorized();
    $this->deleteJson(route('webpush-kit.subscriptions.destroy'), ['endpoint' => 'https://x.example/1'])->assertUnauthorized();
});

it('deletes only the given endpoint of the signed-in user', function () {
    $user = $this->user();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/keep', 'k', 't');
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/drop', 'k', 't');

    $this->actingAs($user)->deleteJson(route('webpush-kit.subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/drop'])
        ->assertOk()
        ->assertJson(['subscribed' => false]);

    expect($user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/keep']);
});

it('rotates a subscription from the service worker', function () {
    $user = $this->user();
    $user->updatePushSubscription('https://updates.push.services.mozilla.com/wpush/v2/old', 'k', 't');

    $this->actingAs($user)
        ->withHeader('Sec-Fetch-Site', 'same-origin')
        ->postJson(route('webpush-kit.subscriptions.rotate'), [
            'old_endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/old',
            'subscription' => browserSubscription('https://updates.push.services.mozilla.com/wpush/v2/new'),
        ])
        ->assertOk();

    expect($user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://updates.push.services.mozilla.com/wpush/v2/new']);
});

it('refuses a cross-site rotate request', function (array $headers) {
    $user = $this->user();
    $user->updatePushSubscription('https://updates.push.services.mozilla.com/wpush/v2/old', 'k', 't');

    $this->actingAs($user)->withHeaders($headers)
        ->postJson(route('webpush-kit.subscriptions.rotate'), [
            'old_endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/old',
            'subscription' => browserSubscription('https://evil.example/push'),
        ])
        ->assertForbidden();

    expect($user->pushSubscriptions()->count())->toBe(1);
})->with([
    'cross-site fetch' => [['Sec-Fetch-Site' => 'cross-site']],
    'foreign origin' => [['Origin' => 'https://evil.example']],
    'no origin at all' => [[]],
]);

it('does not delete another user\'s endpoint on rotate', function () {
    $owner = $this->user('Ada');
    $owner->updatePushSubscription('https://fcm.googleapis.com/fcm/send/ada', 'k', 't');
    $other = $this->user('Grace');

    $this->actingAs($other)->withHeader('Origin', 'http://localhost')
        ->postJson(route('webpush-kit.subscriptions.rotate'), [
            'old_endpoint' => 'https://fcm.googleapis.com/fcm/send/ada',
            'subscription' => browserSubscription('https://fcm.googleapis.com/fcm/send/grace'),
        ])
        ->assertOk();

    expect($owner->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/ada']);
});
