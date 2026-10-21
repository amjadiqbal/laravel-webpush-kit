<?php

use Illuminate\Support\Facades\Blade;

it('serves a standalone manifest when enabled', function () {
    config(['webpush-kit.manifest.enabled' => true, 'webpush-kit.manifest.name' => 'Push Shop']);

    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson(['display' => 'standalone', 'start_url' => '/', 'name' => 'Push Shop']);

    expect(Blade::render('<x-webpush-kit::head />'))->toContain('<link rel="manifest" href="'.route('webpush-kit.manifest').'">');
});

it('leaves the manifest link out when disabled', function () {
    expect(Blade::render('<x-webpush-kit::head />'))->not->toContain('rel="manifest"')->toContain('webpush-kit.js');
});
