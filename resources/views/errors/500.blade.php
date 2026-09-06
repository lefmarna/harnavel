@php
    $requestId = request()->attributes->get('request_id');
    $displayRequestId = is_string($requestId) && $requestId !== '' ? $requestId : __('errors.reference_id_unavailable');
    $appName = config('app.name', 'Laravel');
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.500.title') }} - {{ $appName }}</title>
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
        <h1>{{ __('errors.500.title') }}</h1>
        <p>{{ __('errors.500.body') }}</p>
        <p>{{ __('errors.reference_id') }}: <code>{{ $displayRequestId }}</code></p>
        <p><a href="{{ url('/') }}">{{ __('errors.back_home') }}</a></p>
    </main>
</body>
</html>
