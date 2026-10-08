<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Process\Process;
use Tests\Support\AppSubprocess;

describe('パスキー', function () {
    it('画面へ渡す許可 origin はサーバーの照合と同じ表記に正規化する', function () {
        config(['passkeys.allowed_origins' => ['https://example.test/', 'example.test:8443']]);

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('passkeyAllowedOrigins', ['https://example.test']));
    });

    it('許可 origin が空でもログイン画面を表示しパスキー導線を止める', function () {
        config(['passkeys.allowed_origins' => []]);

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('passkeyAllowedOrigins', []));
    });

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

    it('登録候補はユーザーと秘密鍵から導出した64文字の hex ハンドルを返す', function (int $id, string $secret, string $handle) {
        config(['passkeys.user_handle_secret' => $secret]);
        $user = User::factory()->create(['id' => $id]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonPath('options.user.id', Base64UrlSafe::encodeUnpadded($handle));
    })->with([
        'ユーザー1' => [1, 'test-passkey-secret', '4d577e59603ff8b43b36af5f1155eb0967e4390bdde64d75f16c118710484bf2'],
        'ユーザー2' => [2, 'test-passkey-secret', '4a900c2e4a2232079e8f8e6b12c597fe64b15ee8203b965863dfdb820427ccfd'],
        '異なる秘密鍵' => [1, 'other-passkey-secret', '95e5cb13d9d91579762dde0ee40a78495db0b628a7786c3d9b1ec5962422c23f'],
    ]);

    it('UUID 主キーは文字列全体を使って hex ハンドルを導出する', function () {
        config(['passkeys.user_handle_secret' => 'test-passkey-secret']);
        $user = (new User)
            ->setKeyType('string')
            ->setIncrementing(false)
            ->forceFill(['id' => '550e8400-e29b-41d4-a716-446655440000']);

        expect($user->getPasskeyUserHandle())
            ->toBe('0f1664ac77b67f341a8570bba45f5fec66cc9e6044528e33ff4426905ed9c03c');
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

describe('パスキーのユーザーハンドル秘密鍵', function () {
    it('本番環境では PASSKEYS_USER_HANDLE_SECRET が未設定だと起動しない', function () {
        $process = bootWithEnvironment(AppSubprocess::productionEnvironment(['PASSKEYS_USER_HANDLE_SECRET' => '']));

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput().$process->getErrorOutput())->toContain('PASSKEYS_USER_HANDLE_SECRET');
    });

    it('本番環境でも秘密鍵が明示されていれば起動する', function () {
        $process = bootWithEnvironment(AppSubprocess::productionEnvironment());

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });

    it('フォールバック時代のパスキーを保つため APP_KEY と同じ値を明示しても起動する', function () {
        $appKey = 'base64:'.base64_encode(random_bytes(32));

        $process = bootWithEnvironment(AppSubprocess::productionEnvironment([
            'APP_KEY' => $appKey,
            'PASSKEYS_USER_HANDLE_SECRET' => $appKey,
        ]));

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    });

    it('Artisan の --env で本番を指定した場合も秘密鍵の未設定を検出する', function () {
        $process = AppSubprocess::artisan(
            ['env', '--env=production'],
            ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'PASSKEYS_USER_HANDLE_SECRET' => ''],
        );

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput().$process->getErrorOutput())->toContain('PASSKEYS_USER_HANDLE_SECRET');
    });

    it('本番以外では秘密鍵が未設定でも APP_KEY からユーザーハンドルを導出する', function () {
        $process = bootWithEnvironment(
            ['APP_ENV' => 'testing', 'PASSKEYS_USER_HANDLE_SECRET' => ''],
            'echo (new App\Models\User)->forceFill(["id" => 1])->getPasskeyUserHandle();',
        );

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
            ->and($process->getOutput())->not->toBe('');
    });
});

/**
 * @param  array<string, string>  $env
 */
function bootWithEnvironment(array $env, string $afterBoot = ''): Process
{
    return AppSubprocess::php(
        'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'.$afterBoot,
        $env,
    );
}
