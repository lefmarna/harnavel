@php
    $trackingId = config('services.google.analytics_tracking_id');
@endphp

@if (is_string($trackingId) && $trackingId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $trackingId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        // send_page_view: false — 初回自動 page_view を無効化。
        // SPA 遷移の page_view はアプリ側（GoogleAnalyticsTracker）で手動送信する。
        // GA4 管理画面の Enhanced Measurement「ブラウザ履歴イベントに基づくページの変更」も
        // 必ず OFF にすること（有効のままだと遷移ごとに二重計測になる）。
        gtag('config', '{{ $trackingId }}', {
            send_page_view: false,
        });
    </script>
@endif
