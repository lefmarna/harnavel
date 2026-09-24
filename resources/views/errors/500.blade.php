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
    @include('errors.partials.style')
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
