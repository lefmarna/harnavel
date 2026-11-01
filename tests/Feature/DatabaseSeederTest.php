<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

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
