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
    @include('errors.partials.style')
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
