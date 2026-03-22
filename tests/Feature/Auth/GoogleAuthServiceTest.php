<?php

use App\Exceptions\GoogleAuthException;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\GoogleAuth\GoogleAuthService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

test('新規ユーザーを作成する', function () {
    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('google-123', 'new@example.com', 'New User');

    expect($user->google_id)->toBe('google-123')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('users', [
        'google_id' => 'google-123',
        'email' => 'new@example.com',
    ]);
});

test('既存メールユーザーに google_id を連携する', function () {
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);
    $user = $service->findOrCreate('google-456', 'existing@example.com', 'Existing User');

    expect($user->id)->toBe($existing->id)
        ->and($user->google_id)->toBe('google-456');

    $this->assertDatabaseHas('users', [
        'id' => $existing->id,
        'google_id' => 'google-456',
    ]);
});

test('lowercase usernamesが無効なら mixed-case メールの既存ユーザーをGoogleログインで再利用する', function () {
    config(['fortify.lowercase_usernames' => false]);

    $existing = User::factory()->create([
        'email' => 'Test.User@Example.COM',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);
    $user = $service->findOrCreate('google-mixed-case', 'Test.User@Example.COM', 'Existing User');

    expect($user->id)->toBe($existing->id)
        ->and($user->google_id)->toBe('google-mixed-case')
        ->and(User::query()->count())->toBe(1);
});

test('既存 google_id ユーザーをそのまま返す', function () {
    $existing = User::factory()->create([
        'google_id' => 'google-789',
        'email' => 'googleuser@example.com',
    ]);

    $service = app(GoogleAuthService::class);
    $user = $service->findOrCreate('google-789', 'googleuser@example.com', 'Google User');

    expect($user->id)->toBe($existing->id);
    $this->assertDatabaseCount('users', 1);
});

test('既存ユーザーの email_verified_at が null なら自動設定する', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'unverified@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);
    $user = $service->findOrCreate('google-abc', 'unverified@example.com', 'Unverified User');

    expect($user->email_verified_at)->not->toBeNull();
    expect(User::find($existing->id)?->email_verified_at)->not->toBeNull();
});

test('google_id ユーザーとメールユーザーが別レコードの場合 GoogleAuthException を投げる', function () {
    User::factory()->create([
        'google_id' => 'google-aaa',
        'email' => 'aaa@example.com',
    ]);
    User::factory()->create([
        'google_id' => null,
        'email' => 'bbb@example.com',
    ]);

    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-aaa', 'bbb@example.com', 'User'))
        ->toThrow(GoogleAuthException::class);
});

test('別の google_id がすでに連携済みの場合 GoogleAuthException を投げる', function () {
    User::factory()->create([
        'email' => 'linked@example.com',
        'google_id' => 'old-google-id',
    ]);

    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('new-google-id', 'linked@example.com', 'User'))
        ->toThrow(GoogleAuthException::class, 'このメールアドレスは別のGoogleアカウントに連携済みです。');
});

test('二段階認証が有効な既存ユーザーもサービス層では返却する', function () {
    $existing = User::factory()->create([
        'email' => 'two-factor@example.com',
        'google_id' => 'google-2fa',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('google-2fa', 'two-factor@example.com', 'Two Factor User');

    expect($user->is($existing))->toBeTrue();
});

test('交差入力（google_id と email が別ユーザーに対応）で衝突 GoogleAuthException を投げる', function () {
    // findCandidates が 2 ユーザーを返し、衝突判定を発動することを確認するテスト
    // ※並行ロック順序の正確性は統合テストで検証する必要がある
    User::factory()->create([
        'google_id' => 'google-X',
        'email' => 'x@example.com',
    ]);
    User::factory()->create([
        'google_id' => null,
        'email' => 'y@example.com',
    ]);

    $service = app(GoogleAuthService::class);

    // google_id が userX を、email が userY を指す「交差入力」
    expect(fn () => $service->findOrCreate('google-X', 'y@example.com', 'User'))
        ->toThrow(GoogleAuthException::class, 'このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
});

test('UniqueConstraintViolationException 発生時にフォールバックで既存ユーザーを返す', function () {
    $existing = User::factory()->create([
        'email' => 'race@example.com',
        'google_id' => 'race-google',
    ]);

    // 競合状態をシミュレート:
    // - トランザクション内: findCandidates が空を返す（先行リクエストがまだ見えない）
    // - save() が UniqueConstraintViolationException を投げる
    // - フォールバック: findByGoogleId が先行リクエストの作成済みユーザーを返す
    $fakeRepo = new class($existing) implements UserRepositoryContract
    {
        public function __construct(private readonly User $existingUser) {}

        public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
        {
            return collect();
        }

        public function findByGoogleId(string $googleId, bool $lock = false): ?User
        {
            return $this->existingUser;
        }

        public function findByEmail(string $email, bool $lock = false): ?User
        {
            return null;
        }

        public function save(User $user): void
        {
            throw new UniqueConstraintViolationException(
                'testing',
                'INSERT INTO `users`',
                [],
                new PDOException('Integrity constraint violation')
            );
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('race-google', 'race@example.com', 'Race User');

    expect($user->id)->toBe($existing->id);
});

test('UniqueConstraintViolationException 後に findByEmail でユーザーを取得し google_id と email_verified_at を補完する', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'fallback@example.com',
        'google_id' => null,
    ]);

    // findByGoogleId では見つからないが findByEmail で見つかるケース
    $saveCount = 0;
    $fakeRepo = new class($existing, $saveCount) implements UserRepositoryContract
    {
        public function __construct(
            private readonly User $existingUser,
            private int $saveCount,
        ) {}

        public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
        {
            return collect();
        }

        public function findByGoogleId(string $googleId, bool $lock = false): ?User
        {
            return null;
        }

        public function findByEmail(string $email, bool $lock = false): ?User
        {
            return $this->existingUser;
        }

        public function save(User $user): void
        {
            if ($this->saveCount === 0) {
                $this->saveCount++;
                throw new UniqueConstraintViolationException(
                    'testing',
                    'INSERT INTO `users`',
                    [],
                    new PDOException('Integrity constraint violation')
                );
            }
            // 2 回目（フォールバック内の更新保存）は成功させる
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('new-google', 'fallback@example.com', 'Fallback User');

    expect($user->id)->toBe($existing->id)
        ->and($user->google_id)->toBe('new-google')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('UniqueConstraintViolationException 後のフォールバックでも別レコード衝突なら GoogleAuthException を投げる', function () {
    $userA = User::factory()->create([
        'google_id' => 'google-A',
        'email' => 'a@example.com',
    ]);
    $userB = User::factory()->create([
        'google_id' => null,
        'email' => 'b@example.com',
    ]);

    // 先行トランザクションの save 後に別トランザクションが
    // google_id 側と email 側を別々に作成した状況をシミュレート
    $fakeRepo = new class($userA, $userB) implements UserRepositoryContract
    {
        public function __construct(
            private readonly User $userByGoogleId,
            private readonly User $userByEmail,
        ) {}

        public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
        {
            return collect();
        }

        public function findByGoogleId(string $googleId, bool $lock = false): ?User
        {
            return $this->userByGoogleId;
        }

        public function findByEmail(string $email, bool $lock = false): ?User
        {
            return $this->userByEmail;
        }

        public function save(User $user): void
        {
            throw new UniqueConstraintViolationException(
                'testing',
                'INSERT INTO `users`',
                [],
                new PDOException('Integrity constraint violation')
            );
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-A', 'b@example.com', 'User'))
        ->toThrow(GoogleAuthException::class, 'このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
});
