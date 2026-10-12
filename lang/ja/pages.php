<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ページのタイトルと説明
|--------------------------------------------------------------------------
|
| SSR を使わないため、初期 HTML の <title> と meta description に出す文言はサーバー側に置く。
|
*/

return [
    'home' => [
        'title' => 'ホーム',
    ],

    'dashboard' => [
        'title' => 'ダッシュボード',
        'description' => 'アプリケーションの利用状況や設定への導線を確認できます。',
    ],

    'auth' => [
        'login' => [
            'title' => 'ログイン',
            'description' => 'メールアドレスとパスワードを入力してログインします。',
        ],
        'register' => [
            'title' => '新規登録',
            'description' => '必要事項を入力して新しいアカウントを作成します。',
        ],
        'reset_password' => [
            'title' => 'パスワード再設定',
            'description' => 'アカウントの新しいパスワードを設定します。',
        ],
        'forgot_password' => [
            'title' => 'パスワードをお忘れの方',
            'description' => 'メールアドレスを入力してパスワード再設定リンクを受け取ります。',
        ],
        'verify_email' => [
            'title' => 'メール認証',
            'description' => 'メールアドレスの認証を完了してください。',
        ],
        'confirm_password' => [
            'title' => '本人確認',
            'description' => '続行するには現在のパスワードを入力してください。',
        ],
        'google_link' => [
            'title' => 'Googleログイン連携の確認',
            'description' => '確認メールからGoogleログイン連携を完了してください。',
            'success' => [
                'title' => 'Googleログイン連携完了',
                'description' => 'Googleログイン連携が完了しました。',
            ],
            'confirm' => [
                'title' => 'Googleログイン連携の確認',
                'description' => 'Googleログイン連携を確認してください。',
            ],
            'error' => [
                'title' => 'Googleログイン連携エラー',
                'description' => 'Googleログイン連携に失敗しました。',
            ],
        ],
        'email_change' => [
            'verify' => [
                'confirm' => [
                    'title' => 'メールアドレス変更の確認',
                    'description' => '新しいメールアドレスへの変更を確認してください。',
                ],
                'success' => [
                    'title' => 'メールアドレス変更完了',
                    'description' => 'メールアドレスの変更が完了しました。',
                ],
                'error' => [
                    'title' => 'メールアドレス変更エラー',
                    'description' => 'メールアドレスを変更できませんでした。',
                ],
            ],
            'revert' => [
                'confirm' => [
                    'title' => 'メールアドレス変更の取り消し',
                    'description' => 'メールアドレスの変更を取り消すか確認してください。',
                ],
                'success' => [
                    'title' => 'メールアドレス変更の取り消し完了',
                    'description' => 'メールアドレスの変更を取り消しました。',
                ],
                'error' => [
                    'title' => 'メールアドレス変更の取り消しエラー',
                    'description' => 'メールアドレスの変更を取り消せませんでした。',
                ],
            ],
        ],
    ],

    'settings' => [
        'profile' => [
            'title' => 'プロフィール設定',
            'description' => '名前とメールアドレスを管理します。',
        ],
        'security' => [
            'title' => 'セキュリティ設定',
            'description' => 'パスワード、パスキー、Googleログイン連携を管理します。',
        ],
        'appearance' => [
            'title' => '表示設定',
            'description' => 'アカウントの表示設定を変更します。',
        ],
    ],
];
