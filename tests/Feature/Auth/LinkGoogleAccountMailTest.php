<?php

use App\Mail\LinkGoogleAccountMail;
use App\Models\User;

test('Googleログイン連携メールは件名と本文に表示名を使う', function () {
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
