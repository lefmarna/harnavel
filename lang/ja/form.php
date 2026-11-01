<?php

declare(strict_types=1);

// validation.php の 'custom' は全画面共通の field.rule の対応表なので、画面ごとに意味が変わる文言と
// 既定に無い属性名はここに置き、既定の属性名は validation.php に置く。
// name はパスキーの名前にも使うため、既定の属性名にしない。

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
