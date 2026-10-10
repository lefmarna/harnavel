<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

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

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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
}

/**
 * 直前のリクエストが使ったセッションの ID。
 */
function lastSessionId(): string
{
    return app('session')->driver()->getId();
}

/**
 * パスワードでログインし、その端末のセッション ID を返す。
 */
function loginFromNewDevice(User $user): string
{
    forgetRequestState();

    test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => UserFactory::DEFAULT_PASSWORD,
    ])->assertRedirect(config('fortify.home'));

    return lastSessionId();
}

/**
 * 指定したセッションの端末から、ログインが必要な画面を開く。
 */
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
