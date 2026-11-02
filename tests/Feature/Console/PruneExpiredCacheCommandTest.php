<?php

declare(strict_types=1);

use App\Console\Commands\PruneExpiredCacheCommand;
use Illuminate\Support\Facades\DB;

mutates(PruneExpiredCacheCommand::class);

// database ストアは、期限切れの行を同じキーで読み直すまで消さない。RateLimiter のキーのように
// 二度と読まれないキーは、消さなければ cache テーブルに残り続ける。

beforeEach(function () {
    config(['cache.default' => 'database']);
    $this->freezeSecond();
});

/**
 * @param  array<string, int>  $expirations  キーごとの期限（UNIX 時刻）
 */
function insertCacheRows(array $expirations): void
{
    DB::table('cache')->insert(array_map(
        fn (string $key, int $expiration): array => ['key' => $key, 'value' => 's:1:"x";', 'expiration' => $expiration],
        array_keys($expirations),
        $expirations,
    ));
}

/**
 * @return list<string>
 */
function storedCacheKeys(): array
{
    /** @var list<string> */
    return DB::table('cache')->orderBy('key')->pluck('key')->all();
}

describe('期限切れのキャッシュの削除', function () {
    it('期限が今以前の行だけを消し、期限内の行は残す', function () {
        $now = now()->getTimestamp();
        insertCacheRows(['expired' => $now - 1, 'expiring-now' => $now, 'live' => $now + 1]);

        $this->artisan('cache:prune-expired')
            ->expectsOutputToContain('キャッシュを 2 件削除')
            ->assertSuccessful();

        expect(storedCacheKeys())->toBe(['live']);
    });

    it('一度に消す件数を超える期限切れの行も、1 回の実行ですべて消す', function () {
        $now = now()->getTimestamp();
        insertCacheRows(array_combine(
            array_map(fn (int $i): string => "expired-{$i}", range(1, 1001)),
            array_fill(0, 1001, $now - 60),
        ));

        $this->artisan('cache:prune-expired')
            ->expectsOutputToContain('キャッシュを 1001 件削除')
            ->assertSuccessful();

        expect(storedCacheKeys())->toBe([]);
    });

    it('既定のキャッシュが database ストアでなければ、何も消さない', function () {
        insertCacheRows(['expired' => now()->getTimestamp() - 1]);
        config(['cache.default' => 'array']);

        $this->artisan('cache:prune-expired')
            ->expectsOutputToContain('database ストアではない')
            ->assertSuccessful();

        expect(storedCacheKeys())->toBe(['expired']);
    });
});
