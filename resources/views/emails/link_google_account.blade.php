<div>
    {{ __('mail.link_google_account.intro', ['app' => $appName]) }}
</div>
<br>
<div>
    {{ __('mail.link_google_account.account', ['email' => $user->email]) }}<br>
    {{ __('mail.link_google_account.instruction') }}<br>
    <a href="{{ $url }}">{{ $url }}</a>
</div>
<br>
<div>
    {{ __('mail.link_google_account.expiry') }}
</div>
