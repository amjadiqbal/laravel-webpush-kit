/*! Laravel WebPush Kit service worker | MIT | https://github.com/amjadiqbal/laravel-webpush-kit
 *
 * Shows pushes sent with laravel-notification-channels/webpush (WebPushMessage and
 * DeclarativeWebPushMessage), opens the right page on click, and keeps the subscription
 * alive when the browser rotates it. Settings come from this file's query string (the
 * subscribe button adds them), or from self.WEBPUSH_KIT when you importScripts() this
 * file into your own service worker.
 */
(() => {
    'use strict';

    const query = new URL(self.location.href).searchParams;
    const config = Object.assign(
        { rotateUrl: query.get('rotate'), vapidKey: query.get('key') },
        self.WEBPUSH_KIT || {},
    );

    // Our own copy installs and takes over at once; an imported copy leaves that to its host.
    if (!self.WEBPUSH_KIT) {
        self.addEventListener('install', () => self.skipWaiting());
        self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
    }

    const keyToBytes = (base64url) => {
        const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    };

    const readPayload = (data) => {
        if (!data) {
            return {};
        }
        try {
            return data.json();
        } catch {
            return { title: data.text() };
        }
    };

    // Declarative web push (W3C Push API, "web_push": 8030) and the channel's WebPushMessage
    // become the same showNotification() call. The click targets go into data.webpushKit.
    const toNotification = (payload) => {
        if (payload && payload.web_push === 8030 && payload.notification) {
            const { title, navigate, actions = [], app_badge: badgeCount, ...options } = payload.notification;
            return {
                title,
                badgeCount,
                options: {
                    ...options,
                    actions: actions.map(({ navigate: _ignored, ...action }) => action),
                    data: {
                        app: options.data,
                        webpushKit: { url: navigate, actions: Object.fromEntries(actions.map((a) => [a.action, a.navigate])) },
                    },
                },
            };
        }

        const { title = '', ...options } = payload || {};
        const data = options.data && typeof options.data === 'object' ? options.data : {};
        return {
            title,
            options: {
                ...options,
                data: {
                    app: options.data,
                    webpushKit: { url: data.url, actions: data.actions && typeof data.actions === 'object' ? data.actions : {} },
                },
            },
        };
    };

    self.addEventListener('push', (event) => {
        // A browser with declarative web push already shows the message. It only wakes the
        // service worker for "mutable" messages, and showing nothing keeps its version.
        if (event.notification) {
            return;
        }

        const { title, options, badgeCount } = toNotification(readPayload(event.data));
        const jobs = [self.registration.showNotification(title || self.location.hostname, options)];

        if (typeof badgeCount === 'number' && self.navigator.setAppBadge) {
            jobs.push(self.navigator.setAppBadge(badgeCount).catch(() => {}));
        }

        // Safari revokes permission when a push shows nothing, so always show it.
        event.waitUntil(Promise.all(jobs));
    });

    self.addEventListener('notificationclick', (event) => {
        const kit = (event.notification.data && event.notification.data.webpushKit) || {};
        const target = (event.action && kit.actions && kit.actions[event.action]) || kit.url;

        event.notification.close();

        if (!target) {
            return;
        }

        const url = new URL(target, self.location.origin).href;

        event.waitUntil((async () => {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            const open = windows.find((client) => client.url === url && 'focus' in client);

            return open ? open.focus() : self.clients.openWindow(url);
        })());
    });

    // The push service rotated or expired the subscription: subscribe again and tell the app,
    // so the old endpoint is replaced instead of turning into a 404/410 later.
    self.addEventListener('pushsubscriptionchange', (event) => {
        event.waitUntil((async () => {
            let subscription = event.newSubscription;

            if (!subscription) {
                const key = (event.oldSubscription && event.oldSubscription.options.applicationServerKey)
                    || (config.vapidKey ? keyToBytes(config.vapidKey) : null);

                if (!key) {
                    return;
                }

                subscription = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            }

            if (!config.rotateUrl) {
                return;
            }

            const encodings = (self.PushManager && self.PushManager.supportedContentEncodings) || ['aesgcm'];

            await fetch(config.rotateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    old_endpoint: event.oldSubscription ? event.oldSubscription.endpoint : null,
                    subscription: {
                        ...subscription.toJSON(),
                        content_encoding: encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0],
                    },
                }),
            });
        })());
    });
})();
