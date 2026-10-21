<?php

declare(strict_types=1);

// validation.php の 'custom' へは置けない。'custom' は field.rule の単一グローバル
// マップなので、パスワード変更（PasswordUpdate）の「新しいパスワード」という文言が、
// 登録や再設定など password を使うほかの画面にも出てしまう。
//
// 既定の属性名は validation.php の 'attributes' に置き、ここには画面ごとに意味が変わるものと
// 既定に無いものを置く。name はパスキーの名前にも使うため、既定の属性名にしない。

return [
    'password_update' => [
        'new_password' => '新しいパスワード',
        'current_password_required' => '現在のパスワードを入力してください。',
        'current_password_string' => '現在のパスワードは文字列で入力してください。',
        'current_password_invalid' => '現在のパスワードが正しくありません。',
        'password_required' => '新しいパスワードを入力してください。',
        'password_string' => '新しいパスワードは文字列で入力してください。',
        'password_confirmed' => 'パスワード確認が一致しません。',
    ],

    'profile_update' => [
        'name' => 'ニックネーム',
        'email_change_failed' => '確認メールを送信できませんでした。時間をおいて再度お試しください。',
        'email_change_throttled' => '確認メールの送信が続いたため、しばらく時間をおいてから再度お試しください。',
    ],
    'google_unlink' => [
        'password_required' => 'Googleログイン連携を解除する前に、パスワードを設定してください。',
    ],
];
