<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;

describe('パスキー', function () {
    it('パスキー登録の候補は未認証では取得できない', function () {
        $this->get(route('passkey.registration-options'))
            ->assertRedirect(route('login'));
    });

    it('パスキーでのログイン候補は未認証でも取得できる', function () {
        $this->getJson(route('passkey.login-options'))->assertOk();
    });

    it('well-known エンドポイントが登録画面と管理画面を案内する', function () {
        $this->getJson(route('well-known.passkeys'))
            ->assertOk()
            ->assertExactJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    });

    it('パスワード確認が切れているとき登録候補は 423 を返す', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('passkey.registration-options'))
            ->assertStatus(423);
    });

    it('ルートビューは csrf-token meta を出さない', function () {
        // @laravel/passkeys は meta があれば XSRF-TOKEN cookie より優先するが、ログイン時の
        // トークン再生成後も meta は古いままで、CSRF 検証が cookie を見に行かなくなる。
        $this->get(route('login'))->assertDontSee('name="csrf-token"', escape: false);
    });
});

describe('パスキーの credential_id', function () {
    it('WebAuthn が許す最大長の資格情報 ID を保存できる', function () {
        // 検証は 1023 バイトまで通し（webauthn-lib の CheckCredentialId）、保存はそれを Base64URL に
        // した文字列。varchar(255) では 192 バイト以上の資格情報 ID が INSERT で落ちる。
        $credentialId = Base64UrlSafe::encodeUnpadded(random_bytes(1023));

        expect($credentialId)->toHaveLength(1364);

        $user = User::factory()->create();

        $user->passkeys()->create([
            'name' => 'ステートレスな認証器',
            'credential_id' => $credentialId,
            'credential' => [],
        ]);

        expect(Passkey::query()->where('credential_id', $credentialId)->value('name'))
            ->toBe('ステートレスな認証器');
    });

    it('資格情報 ID は大文字と小文字を別の値として扱う', function () {
        // Base64URL は大文字小文字を区別する識別子。既定の *_general_ci では UNIQUE が誤衝突し、
        // where() も別のパスキーを引き当てる。
        $user = User::factory()->create();

        $user->passkeys()->create([
            'name' => '大文字を含むほう',
            'credential_id' => 'AbC',
            'credential' => [],
        ]);

        $user->passkeys()->create([
            'name' => '小文字だけのほう',
            'credential_id' => 'abc',
            'credential' => [],
        ]);

        expect(Passkey::query()->where('credential_id', 'AbC')->value('name'))->toBe('大文字を含むほう');
        expect(Passkey::query()->where('credential_id', 'abc')->value('name'))->toBe('小文字だけのほう');
    });
});
