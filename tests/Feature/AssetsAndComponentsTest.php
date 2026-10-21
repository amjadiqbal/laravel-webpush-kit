<?php

use Illuminate\Support\Facades\Blade;

it('serves the service worker from the site root with its headers', function () {
    $response = $this->get('/webpush-sw.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
        ->and($response->headers->get('Service-Worker-Allowed'))->toBe('/')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->getContent())->toContain("addEventListener('pushsubscriptionchange'");
});

it('serves the browser script without a session', function () {
    $response = $this->get(route('webpush-kit.script'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
        ->and($response->headers->has('Set-Cookie'))->toBeFalse()
        ->and($response->getContent())->toContain("customElements.define('webpush-kit-button'");
});

it('has no manifest route unless enabled', function () {
    $this->get('/manifest.webmanifest')->assertNotFound();
});

it('renders the button for a signed-in user with everything the browser needs', function () {
    $this->actingAs($this->user());

    $html = Blade::render('<x-webpush-kit::button prompt="auto" title="Order updates?" />');

    expect($html)
        ->toContain('<webpush-kit-button')
        ->toContain('wire:ignore')
        ->toContain('vapid-key="BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM"')
        ->toContain('subscribe-url="'.route('webpush-kit.subscriptions.store').'"')
        ->toContain('sw-url="/webpush-sw.js?rotate=%2Fwebpush%2Fsubscriptions%2Frotate&amp;key=BNcR')
        ->toContain('prompt="auto"')
        ->toContain('Order updates?')
        ->toContain('data-navigate-once');
});

it('renders nothing for guests', function () {
    expect(trim(Blade::render('<x-webpush-kit::button />')))->toBe('');
});

it('adds the about entry', function () {
    $this->artisan('about', ['--only' => 'webpush_kit'])
        ->expectsOutputToContain('WebPush Kit')
        ->expectsOutputToContain('amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-webpush-kit')
        ->assertSuccessful();
});
