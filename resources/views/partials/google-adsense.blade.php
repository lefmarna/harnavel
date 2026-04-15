@php
    $publisherId = config('services.google.adsense_publisher_id');
@endphp

@if (is_string($publisherId) && $publisherId !== '')
    <script
        async
        src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $publisherId }}"
        crossorigin="anonymous"
    ></script>
@endif
