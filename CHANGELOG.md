# Changelog

All notable changes to `laravel-webpush-kit` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `<x-webpush-kit::button />`: Blade and Livewire subscribe button with a two-step permission prompt, states for subscribed, blocked, unsupported, insecure and iOS Safari (Add to Home Screen), and `webpush-kit:subscribed` / `webpush-kit:unsubscribed` browser events.
- Service worker served at `/webpush-sw.js` (publishable): shows `WebPushMessage` and declarative web push messages, opens `data.url` / `navigate` on click, re-subscribes on `pushsubscriptionchange`.
- Routes that save, delete and rotate subscriptions through the channel's `updatePushSubscription()` / `deletePushSubscription()`.
- Optional web app manifest and `<x-webpush-kit::head />`.
- `php artisan push:test` with per-push-service results and VAPID subject checks.
- `php artisan about` entry.
