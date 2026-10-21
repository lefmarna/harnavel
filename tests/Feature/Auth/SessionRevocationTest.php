<?php

declare(strict_types=1);

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\RejectRevokedSessions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Actions\StorePasskey;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

mutates(RejectRevokedSessions::class);

function resetPasswordElsewhere(User $user): void
{
    $user = User::findOrFail($user->getKey());

    app(ResetUserPassword::class)->reset($user, [
        'token' => Password::createToken($user),
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ]);
}

function recallerCookieName(): string
{
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');

    return $guard->getRecallerName();
}

describe('セッションの失効', function () {
    beforeEach(function () {
        config(['session.driver' => 'database']);
    });

    it('パスワードの照合と同時に再設定されたログインを、次のリクエストで使えなくする', function () {
        $user = User::factory()->create();
        Event::listen(Validated::class, fn () => resetPasswordElsewhere($user));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])->assertRedirect(config('fortify.home'));

        visitProtectedPageFrom(lastSessionId())->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('ログイン保持 Cookie での復帰と同時に再設定されたログインを、次のリクエストで使えなくする', function () {
        $user = User::factory()->create();
        $recaller = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();

        forgetRequestState();
        Event::listen(Login::class, fn () => resetPasswordElsewhere($user));

        getProtectedPage($this->withCookie(recallerCookieName(), $recaller))
            ->assertOk();

        visitProtectedPageFrom(lastSessionId())->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('失効の後にパスワードでログインしたセッションは使い続けられる', function () {
        $user = User::factory()->create(['session_generation' => 1]);

        visitProtectedPageFrom(loginFromNewDevice($user))->assertOk();
    });

    it('失効の後にログイン保持 Cookie で復帰したセッションは使い続けられる', function () {
        $user = User::factory()->create(['session_generation' => 1]);
        $recaller = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();

        forgetRequestState();
        getProtectedPage($this->withCookie(recallerCookieName(), $recaller))
            ->assertOk();

        visitProtectedPageFrom(lastSessionId())->assertOk();
    });

    // 世代を記録する前から続くログインを、導入時に一斉にログアウトさせない。
    it('世代を記録していないセッションは、次に失効させるまで使える', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);

        forgetRequestState();
        $session = app('session')->driver();
        $session->setId($sessionId);
        $session->start();
        $session->forget(User::SESSION_GENERATION_KEY);
        $session->save();

        visitProtectedPageFrom($sessionId)->assertOk();

        resetPasswordElsewhere($user);

        visitProtectedPageFrom($sessionId)->assertRedirect(route('login'));
    });

    it('失効させたセッションでログインの要らない画面を開くと、ゲストとして表示する', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
        $this->assertGuest();
        expect(app('session')->driver()->token())->toBeString()
            ->and(app('session')->driver()->getId())->not->toBe($sessionId);
    });

    it('失効させたセッションを拒否したことを記録する', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);
        Log::spy();

        visitProtectedPageFrom($sessionId)->assertRedirect(route('login'));

        Log::shouldHaveReceived('info')->with('Revoked session was rejected.', ['user_id' => $user->getKey()])->once();
    });

    it('失効させたセッションに本人確認の記録を残さない', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);

        forgetRequestState();
        getProtectedPage($this->withCookie(config('session.cookie'), $sessionId))
            ->assertRedirect(route('login'));

        expect(app('session')->driver()->has('auth.password_confirmed_at'))->toBeFalse();
    });

    it('失効させたセッションを拒否しても、ログイン保持の値を作り直さない', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);
        $rememberToken = $user->refresh()->remember_token;

        visitProtectedPageFrom($sessionId)->assertRedirect(route('login'));

        expect($user->refresh()->remember_token)->toBe($rememberToken);
    });

    it('他の端末を失効させる操作の後も、操作した端末はログイン保持で復帰できる', function (Closure $operate) {
        $user = User::factory()->create(['google_id' => 'google-1']);
        $recaller = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();
        $sessionId = lastSessionId();

        forgetRequestState();
        $response = $operate(
            $this->withCookie(config('session.cookie'), $sessionId)
                ->withCookie(recallerCookieName(), $recaller)
                ->withSession(['auth.password_confirmed_at' => time()]),
            $user,
        )->assertSessionHasNoErrors();

        forgetRequestState();
        getProtectedPage($this->withCookie(recallerCookieName(), $response->getCookie(recallerCookieName())?->getValue()))
            ->assertOk();
    })->with([
        'パスワードの変更' => fn ($test) => $test->put(route('user-password.update'), [
            'current_password' => UserFactory::DEFAULT_PASSWORD,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ]),
        'Google 連携の解除' => fn ($test) => $test->delete(route('security.google.destroy')),
        'パスキーの削除' => fn ($test, User $user) => $test->delete(route('passkey.destroy', $user->passkeys()->create([
            'name' => 'iPhone',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]))),
        '他の端末からのログアウト' => fn ($test) => $test->delete(route('security.sessions.destroy')),
    ]);

    it('ログイン保持を使っていない端末には、操作の後も保持 Cookie を出さない', function () {
        $response = $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('security.sessions.destroy'));

        expect($response->getCookie(recallerCookieName()))->toBeNull();
    });

    it('別の利用者の保持 Cookie が残っていても、操作した利用者の保持 Cookie は出さない', function () {
        $other = User::factory()->create();
        $recaller = $this->post(route('login.store'), [
            'email' => $other->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();

        $user = User::factory()->create();

        forgetRequestState();
        $response = $this->actingAs($user)
            ->withCookie(recallerCookieName(), $recaller)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('security.sessions.destroy'));

        expect($response->getCookie(recallerCookieName())?->getValue())->not->toStartWith($user->getKey().'|');
    });

    it('すでに使えなくなった自分の保持 Cookie が残っていても、保持 Cookie を出し直さない', function () {
        $user = User::factory()->create();
        $recaller = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();
        $user->refresh()->revokeSessions();
        $user->save();

        forgetRequestState();
        $response = $this->actingAs($user)
            ->withCookie(recallerCookieName(), $recaller)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                User::SESSION_GENERATION_KEY => $user->session_generation,
            ])
            ->delete(route('security.sessions.destroy'))
            ->assertSessionHas('status', 'other-sessions-logged-out');

        expect($response->getCookie(recallerCookieName())?->getValue())
            ->not->toContain('|'.$user->refresh()->getRememberToken().'|');
    });

    it('設定画面でのパスワード変更の途中で再設定されたら、その変更を拒否する', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);

        forgetRequestState();
        $resetDone = false;
        Event::listen(Authenticated::class, function () use ($user, &$resetDone): void {
            if ($resetDone) {
                return;
            }

            $resetDone = true;
            resetPasswordElsewhere($user);
            resetPasswordElsewhere($user);
        });

        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(settingsUrl('security'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password3',
                'password_confirmation' => 'new-password3',
            ])
            ->assertConflict();

        expect($resetDone)->toBeTrue()
            ->and($user->refresh()->session_generation)->toBe(2)
            ->and(Hash::check('new-password1', $user->password))->toBeTrue();
    });

    it('失効したセッションでのパスキー登録は、保存の前に拒否する', function (bool $revoked) {
        $user = User::factory()->create();

        if ($revoked) {
            resetPasswordElsewhere($user);
        }

        $source = CredentialRecord::create(
            publicKeyCredentialId: 'credential-1',
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'public-key',
            userHandle: $user->getPasskeyUserHandle(),
            counter: 0,
        );
        $store = fn () => app(StorePasskey::class)->createPasskey($user, 'New key', $source);

        if ($revoked) {
            expect($store)->toThrow(HttpException::class);
        } else {
            $store();
        }

        expect($user->passkeys()->count())->toBe($revoked ? 0 : 1);
    })->with([
        '失効していれば拒否する' => [true],
        '失効していなければ保存する' => [false],
    ]);

    it('失効と同時に進んだログインは、ログイン保持の値を書き戻さない', function () {
        $user = User::factory()->create(['remember_token' => null]);
        $loadedBeforeRevocation = User::findOrFail($user->getKey());
        $owner = User::findOrFail($user->getKey());
        $owner->revokeSessions();
        $owner->save();

        Auth::login($loadedBeforeRevocation, remember: true);

        expect($user->refresh()->getRememberToken())->toBe($owner->getRememberToken());
    });
});
