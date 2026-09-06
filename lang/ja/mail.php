<?php

declare(strict_types=1);

return [
    'link_google_account' => [
        'subject' => '【:app】Googleログイン連携の確認',
        'intro' => 'このメールは:appのGoogleログイン連携手続きをされた方に自動送信しております。',
        'account' => '対象アカウント: :email',
        'instruction' => '下記URLを開いたあと、画面上の「Googleログインを連携する」ボタンを押すとGoogleログインの連携が完了いたします。',
        'expiry' => 'URLの有効期間は1時間です。',
    ],
];
