<div>
    このメールは{{ $appName }}のGoogleログイン連携手続きをされた方に自動送信しております。
</div>
<br>
<div>
    対象アカウント: {{ $user->email }}<br>
    下記URLを開いたあと、画面上の「Googleログインを連携する」ボタンを押すとGoogleログインの連携が完了いたします。<br>
    <a href="{{ $url }}">{{ $url }}</a>
</div>
<br>
<div>
    URLの有効期間は1時間です。
</div>
