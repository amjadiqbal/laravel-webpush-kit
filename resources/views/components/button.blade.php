@props([
    'prompt' => 'click',
    'delay' => 5,
    'remindAfterDays' => 7,
    'title' => null,
    'body' => null,
    'theme' => null,
])
@auth(config('webpush-kit.guard'))
@php
    $texts = trans('webpush-kit::ui');
    if ($title !== null) $texts['prompt_title'] = $title;
    if ($body !== null) $texts['prompt_body'] = $body;
@endphp
<div wire:ignore {{ $attributes->only(['class', 'style', 'id']) }}>
    @once('webpush-kit-script')
        <script src="{{ \AmjadIqbal\WebPushKit\WebPushKit::scriptUrl() }}" defer data-navigate-once></script>
    @endonce
    <webpush-kit-button
        vapid-key="{{ \AmjadIqbal\WebPushKit\WebPushKit::vapidPublicKey() }}"
        subscribe-url="{{ route('webpush-kit.subscriptions.store') }}"
        sw-url="{{ \AmjadIqbal\WebPushKit\WebPushKit::serviceWorkerUrl() }}"
        sw-scope="{{ '/'.ltrim((string) config('webpush-kit.service_worker.scope', '/'), '/') }}"
        @if (! config('webpush-kit.service_worker.register', true)) sw-register="false" @endif
        csrf="{{ csrf_token() }}"
        @if ($theme) theme="{{ $theme }}" @endif
        prompt="{{ $prompt }}"
        delay="{{ (int) $delay }}"
        remind-after-days="{{ (int) $remindAfterDays }}"
        texts="{{ json_encode($texts, JSON_UNESCAPED_UNICODE) }}"
    ></webpush-kit-button>
</div>
@endauth
