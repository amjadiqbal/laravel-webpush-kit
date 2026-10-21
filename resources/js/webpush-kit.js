/*! Laravel WebPush Kit | MIT | https://github.com/amjadiqbal/laravel-webpush-kit */
(() => {
    'use strict';

    if (!window.customElements || customElements.get('webpush-kit-button')) {
        return;
    }

    const STYLE = `
webpush-kit-button{display:block;--wpk-accent:#4f46e5;--wpk-accent-text:#fff;--wpk-text:#0f172a;--wpk-muted:#475569;--wpk-surface:#fff;--wpk-border:#e2e8f0;font:inherit;color:var(--wpk-text)}
@media (prefers-color-scheme:dark){webpush-kit-button{--wpk-accent:#818cf8;--wpk-accent-text:#0f172a;--wpk-text:#f1f5f9;--wpk-muted:#94a3b8;--wpk-surface:#0f172a;--wpk-border:#334155}}
webpush-kit-button .wpk-btn{display:inline-flex;align-items:center;gap:.5em;border:1px solid var(--wpk-accent);background:var(--wpk-accent);color:var(--wpk-accent-text);border-radius:.5rem;padding:.5em 1em;font:inherit;font-weight:600;cursor:pointer}
webpush-kit-button .wpk-btn[disabled]{opacity:.6;cursor:progress}
webpush-kit-button .wpk-btn-quiet{background:transparent;color:var(--wpk-text);border-color:var(--wpk-border)}
webpush-kit-button .wpk-card{border:1px solid var(--wpk-border);background:var(--wpk-surface);border-radius:.75rem;padding:1rem;max-width:28rem}
webpush-kit-button .wpk-card strong{display:block;margin-bottom:.25rem}
webpush-kit-button .wpk-card p,webpush-kit-button .wpk-note{margin:0 0 .75rem;color:var(--wpk-muted)}
webpush-kit-button .wpk-note{margin:0;font-size:.9em}
webpush-kit-button .wpk-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
webpush-kit-button svg{width:1.1em;height:1.1em}`;

    const BELL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';

    const DISMISS_KEY = 'webpush-kit:dismissed-at';
    const SYNC_KEY = 'webpush-kit:synced';

    const store = (area) => ({
        get(key) {
            try { return window[area].getItem(key); } catch { return null; }
        },
        set(key, value) {
            try { window[area].setItem(key, value); } catch { /* private mode */ }
        },
        remove(key) {
            try { window[area].removeItem(key); } catch { /* private mode */ }
        },
    });
    const local = store('localStorage');
    const session = store('sessionStorage');

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    // The VAPID public key is base64url (RFC 8292); subscribe() takes it as bytes in every browser.
    const keyToBytes = (base64url) => {
        const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    };

    const sameKey = (subscription, vapidKey) => {
        const current = subscription.options && subscription.options.applicationServerKey;
        if (!current || !vapidKey) {
            return true;
        }
        const a = new Uint8Array(current);
        const b = keyToBytes(vapidKey);
        return a.length === b.length && a.every((byte, i) => byte === b[i]);
    };

    // iPadOS reports itself as a Mac; touch points tell them apart.
    const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isStandalone = () => navigator.standalone === true
        || window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches;
    const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const contentEncoding = () => {
        const encodings = (window.PushManager && PushManager.supportedContentEncodings) || ['aesgcm'];
        return encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0];
    };

    const registrations = new Map();

    const waitForActive = (registration) => new Promise((resolve) => {
        if (registration.active) {
            resolve(registration);
            return;
        }
        const worker = registration.installing || registration.waiting;
        if (!worker) {
            resolve(navigator.serviceWorker.ready);
            return;
        }
        worker.addEventListener('statechange', () => {
            if (worker.state === 'activated') {
                resolve(registration);
            }
        });
    });

    class WebPushKitButton extends HTMLElement {
        connectedCallback() {
            if (this.texts) {
                return;
            }
            if (!document.getElementById('webpush-kit-style')) {
                const style = document.createElement('style');
                style.id = 'webpush-kit-style';
                style.textContent = STYLE;
                document.head.appendChild(style);
            }
            try {
                this.texts = JSON.parse(this.getAttribute('texts') || '{}');
            } catch {
                this.texts = {};
            }
            this.addEventListener('click', (event) => this.onClick(event));
            this.init();
        }

        disconnectedCallback() {
            clearTimeout(this.autoTimer);
        }

        t(key) {
            return escapeHtml(this.texts[key] || key);
        }

        registration() {
            const url = this.getAttribute('sw-url');
            const scope = this.getAttribute('sw-scope') || '/';
            if (this.getAttribute('sw-register') === 'false') {
                return navigator.serviceWorker.ready;
            }
            if (!registrations.has(url)) {
                registrations.set(url, navigator.serviceWorker.register(url, { scope }).then(waitForActive));
            }
            return registrations.get(url);
        }

        async init() {
            if (!window.isSecureContext) {
                return this.render('insecure');
            }
            if (isIos() && !isStandalone() && !pushSupported()) {
                return this.render('ios');
            }
            if (!pushSupported()) {
                return this.render('unsupported');
            }
            if (Notification.permission === 'denied') {
                return this.render('denied');
            }
            try {
                const registration = await this.registration();
                const subscription = await registration.pushManager.getSubscription();
                if (subscription && Notification.permission === 'granted' && sameKey(subscription, this.getAttribute('vapid-key'))) {
                    this.render('subscribed');
                    // Once per tab session, so a subscription the server lost (or another user saved) is restored.
                    if (session.get(SYNC_KEY) !== subscription.endpoint) {
                        await this.save(subscription);
                        session.set(SYNC_KEY, subscription.endpoint);
                    }
                    return;
                }
            } catch (error) {
                console.warn('[webpush-kit]', error);
            }
            this.render('idle');
            this.scheduleAutoPrompt();
        }

        scheduleAutoPrompt() {
            if (this.getAttribute('prompt') !== 'auto' || Notification.permission !== 'default') {
                return;
            }
            const dismissedAt = Number(local.get(DISMISS_KEY) || 0);
            const remindAfter = Number(this.getAttribute('remind-after-days') || 7) * 864e5;
            if (dismissedAt && Date.now() - dismissedAt < remindAfter) {
                return;
            }
            this.autoTimer = setTimeout(() => {
                if (this.state === 'idle') {
                    this.render('prompt');
                }
            }, Number(this.getAttribute('delay') || 0) * 1000);
        }

        render(state, note = '') {
            this.state = state;
            const views = {
                idle: () => `<button type="button" class="wpk-btn" data-wpk="ask">${BELL}${this.t('enable')}</button>`,
                prompt: () => `<div class="wpk-card" role="dialog" aria-label="${this.t('prompt_title')}"><strong>${this.t('prompt_title')}</strong><p>${this.t('prompt_body')}</p><div class="wpk-row"><button type="button" class="wpk-btn" data-wpk="allow">${this.t('allow')}</button><button type="button" class="wpk-btn wpk-btn-quiet" data-wpk="dismiss">${this.t('not_now')}</button></div></div>`,
                busy: () => `<button type="button" class="wpk-btn" disabled>${BELL}${this.t('working')}</button>`,
                subscribed: () => `<div class="wpk-row"><span class="wpk-note">${this.t('subscribed')}</span><button type="button" class="wpk-btn wpk-btn-quiet" data-wpk="unsubscribe">${this.t('disable')}</button></div>`,
                denied: () => `<p class="wpk-note">${this.t('denied')}</p>`,
                unsupported: () => `<p class="wpk-note">${this.t('unsupported')}</p>`,
                insecure: () => `<p class="wpk-note">${this.t('insecure')}</p>`,
                ios: () => `<p class="wpk-note">${this.t('ios_install')}</p>`,
            };
            this.innerHTML = views[state]() + (note ? `<p class="wpk-note" role="alert">${escapeHtml(note)}</p>` : '');
            this.setAttribute('state', state);
        }

        onClick(event) {
            const action = event.target.closest('[data-wpk]')?.dataset.wpk;
            if (action === 'ask') {
                this.render('prompt');
                this.querySelector('[data-wpk="allow"]')?.focus();
            } else if (action === 'allow') {
                this.subscribe();
            } else if (action === 'dismiss') {
                local.set(DISMISS_KEY, String(Date.now()));
                this.render('idle');
            } else if (action === 'unsubscribe') {
                this.unsubscribe();
            }
        }

        async subscribe() {
            // Ask straight from the click: browsers only show the prompt for a user gesture.
            const permission = Notification.permission === 'default'
                ? Notification.requestPermission()
                : Promise.resolve(Notification.permission);
            this.render('busy');
            try {
                if ((await permission) !== 'granted') {
                    local.set(DISMISS_KEY, String(Date.now()));
                    return this.render(Notification.permission === 'denied' ? 'denied' : 'idle');
                }
                const registration = await this.registration();
                const options = { userVisibleOnly: true, applicationServerKey: keyToBytes(this.getAttribute('vapid-key') || '') };
                let subscription = await registration.pushManager.getSubscription();
                if (subscription && !sameKey(subscription, this.getAttribute('vapid-key'))) {
                    // Subscribed under an old VAPID key: subscribe() would throw InvalidStateError.
                    await subscription.unsubscribe();
                    subscription = null;
                }
                subscription = subscription || await registration.pushManager.subscribe(options);
                await this.save(subscription);
                session.set(SYNC_KEY, subscription.endpoint);
                local.remove(DISMISS_KEY);
                this.render('subscribed');
                this.emit('webpush-kit:subscribed', { endpoint: subscription.endpoint });
            } catch (error) {
                console.error('[webpush-kit]', error);
                this.render('idle', this.texts.error || 'Error');
            }
        }

        async unsubscribe() {
            this.render('busy');
            try {
                const registration = await this.registration();
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    await this.request('DELETE', { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }
                session.remove(SYNC_KEY);
                this.render('idle');
                this.emit('webpush-kit:unsubscribed', { endpoint: subscription ? subscription.endpoint : null });
            } catch (error) {
                console.error('[webpush-kit]', error);
                this.render('subscribed', this.texts.error || 'Error');
            }
        }

        save(subscription) {
            return this.request('POST', { ...subscription.toJSON(), content_encoding: contentEncoding() });
        }

        async request(method, body) {
            const response = await fetch(this.getAttribute('subscribe-url'), {
                method,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify(body),
            });
            if (!response.ok) {
                throw new Error(`${method} ${response.status}`);
            }
            return response;
        }

        // The page's meta token wins: after wire:navigate it is the freshest one.
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || this.getAttribute('csrf') || '';
        }

        emit(name, detail) {
            this.dispatchEvent(new CustomEvent(name, { detail, bubbles: true, composed: true }));
        }
    }

    customElements.define('webpush-kit-button', WebPushKitButton);
})();
