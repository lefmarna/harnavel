<div>
    {{ __('mail.account_security.intro', ['app' => $appName]) }}
</div>
<br>
<div>
    {{ $eventText }}<br>
    {{ __('mail.account_security.time', ['time' => $time]) }}
</div>
<br>
<div>
    {{ __('mail.account_security.instruction') }}
</div>
<br>
<div>
    {{ __('mail.account_security.logged_in') }}<br>
    <a href="{{ $securityUrl }}">{{ $securityUrl }}</a>
</div>
<br>
<div>
    {{ __('mail.account_security.logged_out') }}<br>
    <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
</div>
