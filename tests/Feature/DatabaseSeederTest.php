<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * DatabaseSeeder は本番でも安全に実行できる必要がある。本番は composer --no-dev で
 * faker（require-dev）が無いため、faker を使う開発用 Test User の作成はローカル/テスト
 * 限定にガードする。
 *
 * seeder の run() を直接呼ぶのは、`$this->seed()` 経由だと db:seed コマンドの本番確認
 * プロンプト（ConfirmableTrait）に当たり、seeder 自身の env 分岐を検証できないため。
 */
function runDatabaseSeeder(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
    app()->make(DatabaseSeeder::class)->setContainer(app())->run();
}

describe('DatabaseSeeder', function () {
    it('本番環境では開発用 Test User をシードしない', function () {
        runDatabaseSeeder('production');

        expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    });

    it('ローカル環境では開発用 Test User をシードする', function () {
        runDatabaseSeeder('local');

        expect(User::query()->where('email', 'test@example.com')->exists())->toBeTrue();
    });
});
