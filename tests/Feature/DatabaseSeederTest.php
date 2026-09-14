<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * db:seed の本番確認プロンプトを経由せず、Seeder 自身の環境分岐を検証する。
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
