<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// 漏洩パスワード照会を外部へ送らず、各テストで漏洩の有無を差し替えられるようにする。
uses()
    ->beforeEach(function () {
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    })
    ->in('Feature');

/**
 * @param  list<string>  $keys
 */
function assertInertiaShape(AssertableInertia $page, string $component, array $keys): AssertableInertia
{
    $page->component($component);

    foreach ($keys as $key) {
        $page->has($key);
    }

    return $page;
}

/**
 * 一意制約違反を FakeUserRepository の save へ差し込むための例外。
 */
function testingUniqueConstraintViolation(): UniqueConstraintViolationException
{
    return new UniqueConstraintViolationException(
        'testing',
        'INSERT INTO `users`',
        [],
        new PDOException('Integrity constraint violation')
    );
}

/**
 * 他端末のログインを再現するため、DB セッションの行を直接作る。
 */
function insertDatabaseSession(string $id, ?User $user): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user?->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

/**
 * @return list<string>
 */
function databaseSessionIds(): array
{
    /** @var list<string> */
    return DB::table('sessions')->orderBy('id')->pluck('id')->all();
}

/**
 * テストでは前のリクエストで読み込んだユーザーとセッションがメモリに残る。
 * 次のリクエストを、別のプロセスが受けたときと同じ状態から始める。
 */
function forgetRequestState(): void
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    // Redirector は作られたときのセッションへ withErrors() などを書くので、作り直す。
    app()->forgetInstance('redirect');
    Facade::clearResolvedInstance('redirect');
}

function lastSessionId(): string
{
    return app('session')->driver()->getId();
}

function loginFromNewDevice(User $user): string
{
    forgetRequestState();

    test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::DEFAULT_PASSWORD,
    ])->assertRedirect(config('fortify.home'));

    return lastSessionId();
}

function visitDashboardFrom(string $sessionId): TestResponse
{
    forgetRequestState();

    return test()->withCookie(config('session.cookie'), $sessionId)->get(route('dashboard'));
}

function configureGoogleOAuth(bool $configured): void
{
    config([
        'services.google.client_id' => $configured ? 'google-client-id' : null,
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => 'https://example.com/auth/google/callback',
    ]);
}
