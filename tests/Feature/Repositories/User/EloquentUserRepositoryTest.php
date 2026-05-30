<?php

use App\Models\User;
use App\Repositories\User\EloquentUserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        it('google_id に一致するユーザーを取得できる', function () {
            $user = User::factory()->create([
                'google_id' => 'google-lookup',
            ]);
            $repository = new EloquentUserRepository;

            $found = $repository->findByGoogleId('google-lookup');

            expect($found?->is($user))->toBeTrue();
        });

        it('google_id に一致するユーザーがいない場合 null になる', function () {
            User::factory()->create([
                'google_id' => 'other-google',
            ]);
            $repository = new EloquentUserRepository;

            expect($repository->findByGoogleId('missing-google'))->toBeNull();
        });

        it('lock=true の場合 google_id 検索を FOR UPDATE で実行できる', function () {
            User::factory()->create([
                'google_id' => 'google-lookup',
            ]);
            $repository = new EloquentUserRepository;

            DB::flushQueryLog();
            DB::enableQueryLog();

            $repository->findByGoogleId('google-lookup', lock: true);

            expect(selectsForUpdate())->toBeTrue();
        });
    });

    describe('email 検索', function () {
        it('email に一致するユーザーを取得できる', function () {
            $user = User::factory()->create([
                'email' => 'lookup@example.com',
            ]);
            $repository = new EloquentUserRepository;

            $found = $repository->findByEmail('lookup@example.com');

            expect($found?->is($user))->toBeTrue();
        });

        it('email に一致するユーザーがいない場合 null になる', function () {
            User::factory()->create([
                'email' => 'other@example.com',
            ]);
            $repository = new EloquentUserRepository;

            expect($repository->findByEmail('missing@example.com'))->toBeNull();
        });

        it('lock=true の場合 email 検索を FOR UPDATE で実行できる', function () {
            User::factory()->create([
                'email' => 'lookup@example.com',
            ]);
            $repository = new EloquentUserRepository;

            DB::flushQueryLog();
            DB::enableQueryLog();

            $repository->findByEmail('lookup@example.com', lock: true);

            expect(selectsForUpdate())->toBeTrue();
        });
    });

    describe('パスワード更新', function () {
        it('新しいパスワードをハッシュ化して永続化できる', function () {
            $user = User::factory()->create([
                'password' => Hash::make('old-password'),
            ]);
            $repository = new EloquentUserRepository;

            $repository->updatePassword($user, 'new-password');

            $stored = User::query()->whereKey($user->getKey())->value('password');

            expect($stored)->toBeString()
                ->and(Hash::check('new-password', $stored))->toBeTrue()
                ->and(Hash::check('old-password', $stored))->toBeFalse();
        });
    });
});

function selectsForUpdate(): bool
{
    return collect(DB::getQueryLog())
        ->pluck('query')
        ->contains(fn (string $query): bool => Str::contains(Str::lower($query), 'for update'));
}
