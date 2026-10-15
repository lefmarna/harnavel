<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| フォームごとのバリデーション文言
|--------------------------------------------------------------------------
|
| validation.php の 'custom' へは置けない。'custom' は field.rule の単一グローバル
| マップなので、パスワード変更（PasswordUpdate）の「新しいパスワード」という文言が、
| 登録や再設定など password を使うほかの画面にも出てしまう。
|
| 登録（CreateNewUser）と再設定（ResetUserPassword）は validation.php の 'attributes' しか
| 見ないため、既定の属性名はそちらに残し、ここには画面ごとに意味が変わるものと既定に無いものを置く。
|
*/

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
        'email' => 'メールアドレス',
        'name_required' => 'ニックネームを入力してください。',
        'name_string' => 'ニックネームは文字列で入力してください。',
        'name_max' => 'ニックネームは255文字以内で入力してください。',
        'email_required' => 'メールアドレスを入力してください。',
        'email_string' => 'メールアドレスは文字列で入力してください。',
        'email_email' => '有効なメールアドレスを入力してください。',
        'email_max' => 'メールアドレスは255文字以内で入力してください。',
        'email_unique' => 'このメールアドレスは既に使用されています。',
        'email_change_failed' => '確認メールを送信できませんでした。時間をおいて再度お試しください。',
    ],
    'google_unlink' => [
        'password_required' => 'Googleログイン連携を解除する前に、パスワードを設定してください。',
    ],
];
