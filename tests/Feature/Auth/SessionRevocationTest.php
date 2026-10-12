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
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Actions\StorePasskey;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

mutates(RejectRevokedSessions::class);

function resetPasswordElsewhere(User $user): void
{
    app(ResetUserPassword::class)->reset(User::findOrFail($user->getKey()), [
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

    // 失効より前に照合を終えたログインが、失効の後にセッションを作ってそのまま残らないようにする。
    it('パスワードの照合と同時に再設定されたログインを、次のリクエストで使えなくする', function () {
        $user = User::factory()->create();
        Event::listen(Validated::class, fn () => resetPasswordElsewhere($user));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])->assertRedirect(config('fortify.home'));

        visitDashboardFrom(lastSessionId())->assertRedirect(route('login'));
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

        $this->withCookie(recallerCookieName(), $recaller)
            ->get(route('dashboard'))
            ->assertOk();

        visitDashboardFrom(lastSessionId())->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('失効の後にパスワードでログインしたセッションは使い続けられる', function () {
        $user = User::factory()->create(['session_generation' => 1]);

        visitDashboardFrom(loginFromNewDevice($user))->assertOk();
    });

    it('失効の後にログイン保持 Cookie で復帰したセッションは使い続けられる', function () {
        $user = User::factory()->create(['session_generation' => 1]);
        $recaller = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
            'remember' => true,
        ])->getCookie(recallerCookieName())->getValue();

        forgetRequestState();
        $this->withCookie(recallerCookieName(), $recaller)
            ->get(route('dashboard'))
            ->assertOk();

        visitDashboardFrom(lastSessionId())->assertOk();
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

        visitDashboardFrom($sessionId)->assertOk();

        resetPasswordElsewhere($user);

        visitDashboardFrom($sessionId)->assertRedirect(route('login'));
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

        visitDashboardFrom($sessionId)->assertRedirect(route('login'));

        Log::shouldHaveReceived('info')->with('Revoked session was rejected.', ['user_id' => $user->getKey()])->once();
    });

    it('失効させたセッションに本人確認の記録を残さない', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        expect(app('session')->driver()->has('auth.password_confirmed_at'))->toBeFalse();
    });

    // 拒否したセッションは、失効の後に本人がログインし直した端末のログイン保持まで外さない。
    it('失効させたセッションを拒否しても、ログイン保持の値を作り直さない', function () {
        $user = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        resetPasswordElsewhere($user);
        $rememberToken = $user->refresh()->remember_token;

        visitDashboardFrom($sessionId)->assertRedirect(route('login'));

        expect($user->refresh()->remember_token)->toBe($rememberToken);
    });

    // 失効より前に始まった変更を、失効の後に書き込ませない。巻き戻しも、失効させたセッションへの新しい世代の記録も起きない。
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
            ->from(route('security.edit'))
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

    // 失効より前に始まった登録が、失効の後にパスキーを保存させない。保存してから消すと、その間に別のリクエストがログインに使える。
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

    it('ログイン保持の値が空の既存ユーザーにも、移行で値を入れる', function () {
        $user = User::factory()->create();
        User::query()->whereKey($user->getKey())->update(['remember_token' => null]);

        (require database_path('migrations/2026_10_12_000000_fill_empty_remember_tokens.php'))->up();

        expect($user->refresh()->getRememberToken())->toBeString()->not->toBeEmpty();
    });
});
