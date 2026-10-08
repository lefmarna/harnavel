<?php

declare(strict_types=1);

return [
    'failed' => '認証に失敗しました。',
    'password' => 'パスワードが正しくありません。',
    'throttle' => 'ログインの試行回数が多すぎます。:seconds 秒後にお試しください。',

    /*
     * Google OAuth の失敗文言。フラッシュ経由でログイン画面に出る。
     * 完了操作の成功文言（flash）でも HTTP 層の文言（errors）でもなく、
     * auth.failed / auth.throttle と同じ認証ドメインの文言なのでここに置く。
     */
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
