<?php

declare(strict_types=1);

use App\Mail\LinkGoogleAccountMail;
use App\Models\User;

describe('Googleログイン連携メール', function () {
    it('件名と本文に表示名を使う', function () {
        config([
            'app.name' => 'Internal App',
            'app.display_name' => 'Public App',
        ]);

        $mail = new LinkGoogleAccountMail(
            User::factory()->make(['email' => 'user@example.com']),
            'https://example.com/google-link'
        );

        $mail
            ->assertHasSubject('【Public App】Googleログイン連携の確認')
            ->assertSeeInHtml('このメールはPublic AppのGoogleログイン連携手続きをされた方に自動送信しております。')
            ->assertDontSeeInHtml('Internal App');
    });

    it('本文に対象アカウントと連携手順を表示する', function () {
        $mail = new LinkGoogleAccountMail(
            User::factory()->make(['email' => 'user@example.com']),
            'https://example.com/google-link'
        );

        $mail
            ->assertSeeInHtml('対象アカウント: user@example.com')
            ->assertSeeInHtml('下記URLを開いたあと、画面上の「Googleログインを連携する」ボタンを押すとGoogleログインの連携が完了いたします。')
            ->assertSeeInHtml('URLの有効期間は1時間です。');
    });
});
