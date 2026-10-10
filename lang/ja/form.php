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
| 属性名の切り分け:
| 全画面で同じ意味になる既定は validation.php の 'attributes' に置いたままにする。
| 素の Validator を使う登録（CreateNewUser）とパスワード再設定（ResetUserPassword）は
| そちらしか見ないため、既定を消すと今回移設しない画面の文言が壊れる。
| ここに置くのは、画面ごとに意味が変わるか、既定に無い属性名だけ。
|
*/

return [
    'password_update' => [
        // 既定の attributes.password は「パスワード」。この画面だけ意味が変わる。
        'new_password' => '新しいパスワード',
        'current_password_required' => '現在のパスワードを入力してください。',
        'current_password_string' => '現在のパスワードは文字列で入力してください。',
        'current_password_invalid' => '現在のパスワードが正しくありません。',
        'password_required' => '新しいパスワードを入力してください。',
        'password_string' => '新しいパスワードは文字列で入力してください。',
        'password_confirmed' => 'パスワード確認が一致しません。',
    ],

    'profile_update' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'name_required' => '名前を入力してください。',
        'name_string' => '名前は文字列で入力してください。',
        'name_max' => '名前は255文字以内で入力してください。',
        'email_required' => 'メールアドレスを入力してください。',
        'email_string' => 'メールアドレスは文字列で入力してください。',
        'email_email' => '有効なメールアドレスを入力してください。',
        'email_max' => 'メールアドレスは255文字以内で入力してください。',
        'email_unique' => 'このメールアドレスは既に使用されています。',
    ],
];
