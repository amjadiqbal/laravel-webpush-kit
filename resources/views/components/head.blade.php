@if (config('webpush-kit.manifest.enabled'))
    <link rel="manifest" href="{{ route('webpush-kit.manifest') }}">
@endif
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
@once('webpush-kit-script')
    <script src="{{ \AmjadIqbal\WebPushKit\WebPushKit::scriptUrl() }}" defer data-navigate-once></script>
@endonce
