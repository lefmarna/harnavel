<div>
    {{ __('mail.verify_new_email.intro', ['app' => $appName]) }}
</div>
<br>
<div>
    {{ __('mail.verify_new_email.instruction') }}<br>
    <a href="{{ $url }}">{{ $url }}</a>
</div>
<br>
<div>
    {{ __('mail.verify_new_email.expiry') }}<br>
    {{ __('mail.verify_new_email.ignore') }}
</div>
