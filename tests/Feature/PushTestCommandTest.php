<?php

use AmjadIqbal\WebPushKit\Tests\Fixtures\FakeWebPush;
use Illuminate\Contracts\Events\Dispatcher;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;

function fakePushService(array $statuses = []): FakeWebPush
{
    $fake = new FakeWebPush($statuses);
    app()->instance(WebPushChannel::class, new WebPushChannel($fake, new ReportHandler(app(Dispatcher::class))));

    return $fake;
}

it('sends to the newest subscriber and reports each push service', function () {
    $user = $this->user();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/live', 'k', 't');
    $fake = fakePushService();

    $this->artisan('push:test', ['--url' => '/orders/7'])
        ->expectsOutputToContain('Sending to 1 subscription(s) of User #'.$user->id)
        ->expectsTable(['Push service', 'Status', 'Result'], [['fcm.googleapis.com', 201, 'delivered']])
        ->assertSuccessful();

    $payload = json_decode($fake->sent[0]['payload'], true);
    expect($payload)->toMatchArray(['title' => 'Test notification', 'body' => 'Web push is working.'])
        ->and($payload['data']['url'])->toBe(url('/orders/7'));
});

it('shows the channel removing a dead subscription on 410', function () {
    $user = $this->user();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/live', 'k', 't');
    $user->updatePushSubscription('https://updates.push.services.mozilla.com/wpush/v2/gone', 'k', 't');
    fakePushService(['https://updates.push.services.mozilla.com/wpush/v2/gone' => 410]);

    $this->artisan('push:test', ['id' => $user->id])
        ->expectsTable(['Push service', 'Status', 'Result'], [
            ['fcm.googleapis.com', 201, 'delivered'],
            ['updates.push.services.mozilla.com', 410, 'expired: subscription removed'],
        ])
        ->assertSuccessful();

    expect($user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/live']);
});

it('sends a declarative web push message', function () {
    $this->user()->updatePushSubscription('https://web.push.apple.com/abc', 'k', 't');
    $fake = fakePushService();

    $this->artisan('push:test', ['--declarative' => true])->assertSuccessful();

    $payload = json_decode($fake->sent[0]['payload'], true);
    expect($payload['web_push'])->toBe(8030)
        ->and($payload['notification'])->toMatchArray(['title' => 'Test notification', 'navigate' => url('/')]);
});

it('fails when nothing was delivered', function () {
    $this->user()->updatePushSubscription('https://fcm.googleapis.com/fcm/send/gone', 'k', 't');
    fakePushService(['https://fcm.googleapis.com/fcm/send/gone' => 404]);

    $this->artisan('push:test')->expectsOutputToContain('No push was delivered.')->assertFailed();
});

it('explains what is missing', function () {
    $this->artisan('push:test')->expectsOutputToContain('No push subscriptions yet')->assertFailed();

    $user = $this->user();
    $this->artisan('push:test', ['id' => $user->id])->expectsOutputToContain('has no push subscriptions')->assertFailed();

    config(['webpush.vapid.public_key' => null]);
    $this->artisan('push:test')->expectsOutputToContain('php artisan webpush:vapid')->assertFailed();
});

it('warns about a VAPID subject Safari rejects', function () {
    config(['webpush.vapid.subject' => 'mailto: <me@localhost>']);
    $this->user()->updatePushSubscription('https://web.push.apple.com/abc', 'k', 't');
    fakePushService();

    $this->artisan('push:test')->expectsOutputToContain('no spaces or angle brackets')->assertSuccessful();
});
