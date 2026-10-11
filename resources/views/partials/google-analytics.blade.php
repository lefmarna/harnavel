@php
    $trackingId = config('services.google.analytics_tracking_id');
@endphp

@if (is_string($trackingId) && $trackingId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $trackingId }}" nonce="{{ Vite::cspNonce() }}"></script>
    <script nonce="{{ Vite::cspNonce() }}">
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        // page_view は GoogleAnalyticsTracker が送る。GA4 管理画面の Enhanced Measurement
        // 「ブラウザ履歴イベントに基づくページの変更」は OFF にする（有効だと二重計測になる）。
        gtag('config', '{{ $trackingId }}', {
            send_page_view: false,
        });
    </script>
@endif
