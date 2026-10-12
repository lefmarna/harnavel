<?php

declare(strict_types=1);

return [
    'failed' => 'ログイン用の認証情報が正しくありません。再度お試しください。',
    'password' => 'パスワードが正しくありません。',
    'throttle' => 'ログインの試行回数が多すぎます。:seconds 秒後にお試しください。',

    'google' => [
        'unavailable' => 'Googleログインは現在利用できません。',
        'failed' => 'Google認証に失敗しました。もう一度お試しください。',
        'email_unverified' => 'Googleアカウントのメールアドレスが未検証です。Googleでメール認証を完了してから再度お試しください。',
        'email_missing' => 'Googleアカウントのメールアドレスが取得できませんでした。別のアカウントでお試しください。',
        'profile_failed' => 'Googleアカウント情報の取得に失敗しました。もう一度お試しください。',
        'account_conflict' => 'このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。',
        'email_taken' => 'このメールアドレスは別のGoogleアカウントに連携済みです。',
        'reauthentication_unavailable' => 'Googleでの本人確認は現在利用できません。',
        'reauthentication_mismatch' => '連携済みのGoogleアカウントで本人確認してください。',
    ],
];
