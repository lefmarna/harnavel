<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * database ストアの期限切れの行を消す。database ストアは期限切れの行を同じキーで読み直すまで
 * 消さないので、RateLimiter のキーのように二度と読まれない行が増え続けるため。
 */
class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'database ストアの期限切れのキャッシュを削除する（日次 cron 用）';

    /** 1 回の DELETE で消す上限。大量の行を 1 文で消してテーブルを長くロックしないため。 */
    private const int CHUNK_SIZE = 1000;

    public function handle(): int
    {
        $store = Cache::getStore();

        if (! $store instanceof DatabaseStore) {
            $this->info('既定のキャッシュが database ストアではないので、何も削除しません。');

            return self::SUCCESS;
        }

        $table = config()->string('cache.stores.'.Cache::getDefaultDriver().'.table');
        // DatabaseStore は期限が今以前の行を期限切れとして扱う。
        $now = now()->getTimestamp();
        $total = 0;

        do {
            $deleted = $store->getConnection()
                ->table($table)
                ->where('expiration', '<=', $now)
                ->limit(self::CHUNK_SIZE)
                ->delete();

            $total += $deleted;
        } while ($deleted === self::CHUNK_SIZE);

        $this->info("期限切れのキャッシュを {$total} 件削除しました。");

        return self::SUCCESS;
    }
}
