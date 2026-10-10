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
use Illuminate\Support\Facades\Log;

mutates(RejectRevokedSessions::class);

/**
 * 別の端末で本人がパスワードを再設定したことにする。
 */
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

    // リクエストの最初に読んだ世代から進めると、途中の再設定で進んだ世代を巻き戻し、失効させたセッションを戻してしまう。
    it('設定画面でのパスワード変更と同時に再設定されても、世代を巻き戻さない', function () {
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
            ->assertSessionHasNoErrors();

        expect($resetDone)->toBeTrue()
            ->and($user->refresh()->session_generation)->toBe(3);
        visitDashboardFrom(lastSessionId())->assertOk();
    });
});
