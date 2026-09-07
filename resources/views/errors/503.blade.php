{{--
    このビューは 2 通りの経路で出る。実行時の 503 応答と、配備時に
    `artisan down --render='errors::503'` で焼かれる静的 HTML（.github/workflows/delivery.yml）。
    後者はリクエストを持たないため、文言は配備時点の既定ロケールで固定される。
--}}
@php
    $requestId = request()->attributes->get('request_id');
    $hasRequestId = is_string($requestId) && $requestId !== '';
    $appName = config('app.name', 'Laravel');
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.503.title') }} - {{ $appName }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f8fafc;
            color: #111827;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        main {
            width: min(100%, 560px);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            line-height: 1.3;
        }

        p {
            margin: 0 0 20px;
            color: #475569;
            line-height: 1.8;
        }

        code {
            display: inline-block;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            overflow-wrap: anywhere;
        }

        a {
            color: #0f766e;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('errors.503.title') }}</h1>
        <p>{{ $hasRequestId ? __('errors.503.body') : __('errors.503.retry_later') }}</p>
        @if ($hasRequestId)
            <p>{{ __('errors.reference_id') }}: <code>{{ $requestId }}</code></p>
        @endif
        <p><a href="{{ url('/') }}">{{ __('errors.back_home') }}</a></p>
    </main>
</body>
</html>
