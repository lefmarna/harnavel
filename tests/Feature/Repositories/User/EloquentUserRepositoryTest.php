<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\User\EloquentUserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('Eloquent ユーザーリポジトリ', function () {
    describe('主キー検索', function () {
        it('lock=true の場合主キー検索を FOR UPDATE で実行できる', function () {
            $user = User::factory()->create();
            $repository = new EloquentUserRepository;

            DB::flushQueryLog();
            DB::enableQueryLog();

            $found = $repository->find($user->getKey(), lock: true);

            expect($found?->is($user))->toBeTrue()
                ->and(selectsForUpdate())->toBeTrue();
        });
    });

    describe('候補検索', function () {
        it('google_id または email に一致する候補ユーザーを id 昇順で取得できる', function () {
            $googleIdMatch = User::factory()->create([
                'email' => 'google-id-match@example.com',
                'google_id' => 'candidate-google',
            ]);
            $emailMatch = User::factory()->create([
                'email' => 'email-match@example.com',
                'google_id' => null,
            ]);
            User::factory()->create([
                'email' => 'other@example.com',
                'google_id' => 'other-google',
            ]);
            $repository = new EloquentUserRepository;

            $candidates = $repository->findCandidates('candidate-google', 'email-match@example.com');

            expect($candidates->modelKeys())->toBe([
                $googleIdMatch->getKey(),
                $emailMatch->getKey(),
            ]);
        });

        it('lock=true の場合候補検索を FOR UPDATE で実行できる', function () {
            User::factory()->create([
                'email' => 'email-match@example.com',
                'google_id' => 'candidate-google',
            ]);
            $repository = new EloquentUserRepository;

            DB::flushQueryLog();
            DB::enableQueryLog();

            $repository->findCandidates('candidate-google', 'email-match@example.com', lock: true);

            expect(selectsForUpdate())->toBeTrue();
        });
    });

    describe('google_id 検索', function () {
        it('google_id に一致するユーザーを取得し、lock=true なら FOR UPDATE で実行する', function () {
            $user = User::factory()->create([
                'google_id' => 'google-lookup',
            ]);
            $repository = new EloquentUserRepository;

            expect($repository->findByGoogleId('missing-google'))->toBeNull();

            DB::flushQueryLog();
            DB::enableQueryLog();

            expect($repository->findByGoogleId('google-lookup', lock: true)?->is($user))->toBeTrue()
                ->and(selectsForUpdate())->toBeTrue();
        });
    });

    describe('email 検索', function () {
        it('email に一致するユーザーを取得し、lock=true なら FOR UPDATE で実行する', function () {
            $user = User::factory()->create([
                'email' => 'lookup@example.com',
            ]);
            $repository = new EloquentUserRepository;

            expect($repository->findByEmail('missing@example.com'))->toBeNull();

            DB::flushQueryLog();
            DB::enableQueryLog();

            expect($repository->findByEmail('lookup@example.com', lock: true)?->is($user))->toBeTrue()
                ->and(selectsForUpdate())->toBeTrue();
        });
    });
});

function selectsForUpdate(): bool
{
    return collect(DB::getQueryLog())
        ->pluck('query')
        ->contains(fn (string $query): bool => Str::contains(Str::lower($query), 'for update'));
}
