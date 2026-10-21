# Changelog

All notable changes to `laravel-webpush-kit` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-21

First release.

### Added
- `<x-webpush-kit::button />`: Blade and Livewire subscribe button with a two-step permission prompt, states for subscribed, blocked, unsupported, insecure and iOS Safari (Add to Home Screen), and `webpush-kit:subscribed` / `webpush-kit:unsubscribed` browser events.
- Service worker served at `/webpush-sw.js` (publishable): shows `WebPushMessage` and declarative web push messages, opens `data.url` / `navigate` on click, re-subscribes on `pushsubscriptionchange`.
- Routes that save, delete and rotate subscriptions through the channel's `updatePushSubscription()` / `deletePushSubscription()`.
- Optional web app manifest and `<x-webpush-kit::head />`.
- `php artisan push:test` with per-push-service results and VAPID subject checks.
- `php artisan about` entry.

### Tested
- In a real Chrome 155 with Google's push service: subscribe and turn off from the button, normal and declarative pushes delivered and shown, a real 410 removing a dead subscription, Livewire 4 and `wire:navigate`, and the blocked state.
- Not yet tested: clicking a delivered notification (the click target is set and checked, but the macOS banner never appeared on the test machine), Firefox, and Safari on macOS and iOS.
