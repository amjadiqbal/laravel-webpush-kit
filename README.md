<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/banner-dark.png">
    <img alt="Laravel WebPush Kit — the browser side of Laravel web push notifications" src="art/banner-light.png" width="100%">
  </picture>
</p>

# Laravel WebPush Kit

The browser side of web push for Laravel, built on
[`laravel-notification-channels/webpush`](https://github.com/laravel-notification-channels/webpush).
That channel sends the notifications; this kit adds what every app still writes by hand: a
Blade and Livewire subscribe button with a polite two-step permission prompt, a service worker,
routes that save and delete subscriptions, iOS home-screen guidance, a `push:test` command, and
automatic cleanup of dead subscriptions.

> **Status:** in development. The first release is not on Packagist yet.

## Support

- 🐛 **Bug or feature request** — [open an issue](https://github.com/amjadiqbal/laravel-webpush-kit/issues)
- 💬 **Questions** — [message me on Discord](https://discord.com/users/412244229520359434)
- 🔒 **Security issue** — please report privately via GitHub's security tab

## Need this customised, or something built?

I'm available for custom development, package integration, and technical consulting.

[![Hire me on Upwork](https://img.shields.io/badge/Hire%20me-Upwork-6FDA44?style=flat-square&logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/amjadkhatri)
[![Discord](https://img.shields.io/badge/Discord-Message%20me-5865F2?style=flat-square&logo=discord&logoColor=white)](https://discord.com/users/412244229520359434)

## License

MIT. See [LICENSE.md](LICENSE.md).
