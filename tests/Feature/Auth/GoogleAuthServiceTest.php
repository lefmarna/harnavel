<?php

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\GoogleAuth\GoogleAuthService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

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

test('Google プロフィール名が null、空、空白のみのときは email を name に使う', function (?string $blankName) {
    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('google-blank', 'blank@example.com', $blankName);

    expect($user->name)->toBe('blank@example.com');

    $this->assertDatabaseHas('users', [
        'google_id' => 'google-blank',
        'email' => 'blank@example.com',
        'name' => 'blank@example.com',
    ]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'ascii whitespace' => ['   '],
    'unicode whitespace' => ["\u{3000}\u{3000}"],
]);

test('既存メールユーザーには確認メールを送信し即時連携しない', function () {
    Mail::fake();

    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-456', 'existing@example.com', 'Existing User'))
        ->toThrow(GoogleLinkConfirmationPendingException::class);

    $this->assertDatabaseHas('users', [
        'id' => $existing->id,
        'google_id' => null,
    ]);

    Mail::assertSent(LinkGoogleAccountMail::class);
});

test('lowercase usernamesが無効なら lowercase のGoogleメールでも mixed-case メールの既存ユーザーに確認メールを送信し casing を維持する', function () {
    Mail::fake();
    config(['fortify.lowercase_usernames' => false]);

    $existing = User::factory()->create([
        'email' => 'Test.User@Example.COM',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-mixed-case', 'test.user@example.com', 'Existing User'))
        ->toThrow(GoogleLinkConfirmationPendingException::class);

    expect(User::query()->sole()->email)->toBe('Test.User@Example.COM')
        ->and(User::query()->sole()->google_id)->toBeNull()
        ->and(User::query()->count())->toBe(1);

    Mail::assertSent(LinkGoogleAccountMail::class);
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

test('google_id 未連携の既存ユーザーは email_verified_at が null でも確認メール待ちにする', function () {
    Mail::fake();

    $existing = User::factory()->unverified()->create([
        'email' => 'unverified@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-abc', 'unverified@example.com', 'Unverified User'))
        ->toThrow(GoogleLinkConfirmationPendingException::class);

    expect($existing->fresh())
        ->google_id->toBeNull()
        ->email_verified_at->toBeNull();

    Mail::assertSent(LinkGoogleAccountMail::class);
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

        public function find(string|int $id, bool $lock = false): ?User
        {
            return null;
        }

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

        public function create(array $attributes): User
        {
            throw new BadMethodCallException;
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

        public function updatePassword(User $user, string $newPassword): void
        {
            throw new BadMethodCallException;
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    $user = $service->findOrCreate('race-google', 'race@example.com', 'Race User');

    expect($user->id)->toBe($existing->id);
});

test('UniqueConstraintViolationException 後に findByEmail で既存メールユーザーを取得した場合は確認メールを送信する', function () {
    Mail::fake();

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

        public function find(string|int $id, bool $lock = false): ?User
        {
            return null;
        }

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

        public function create(array $attributes): User
        {
            throw new BadMethodCallException;
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

        public function updatePassword(User $user, string $newPassword): void
        {
            throw new BadMethodCallException;
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('new-google', 'fallback@example.com', 'Fallback User'))
        ->toThrow(GoogleLinkConfirmationPendingException::class);

    expect($existing->fresh())
        ->google_id->toBeNull()
        ->email_verified_at->toBeNull();

    Mail::assertSent(LinkGoogleAccountMail::class);
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

        public function find(string|int $id, bool $lock = false): ?User
        {
            return null;
        }

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

        public function create(array $attributes): User
        {
            throw new BadMethodCallException;
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

        public function updatePassword(User $user, string $newPassword): void
        {
            throw new BadMethodCallException;
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('google-A', 'b@example.com', 'User'))
        ->toThrow(GoogleAuthException::class, 'このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
});

test('lowercase usernamesが無効なら UniqueConstraintViolationException 後も mixed-case メールの既存ユーザーに確認メールを送信する', function () {
    Mail::fake();
    config(['fortify.lowercase_usernames' => false]);

    $existing = User::factory()->unverified()->create([
        'email' => 'Test.User@Example.COM',
        'google_id' => null,
    ]);

    $saveCount = 0;
    $fakeRepo = new class($existing, $saveCount) implements UserRepositoryContract
    {
        public function __construct(
            private readonly User $existingUser,
            private int $saveCount,
        ) {}

        public function find(string|int $id, bool $lock = false): ?User
        {
            return null;
        }

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

        public function create(array $attributes): User
        {
            throw new BadMethodCallException;
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
        }

        public function updatePassword(User $user, string $newPassword): void
        {
            throw new BadMethodCallException;
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    expect(fn () => $service->findOrCreate('new-google', 'test.user@example.com', 'Fallback User'))
        ->toThrow(GoogleLinkConfirmationPendingException::class);

    expect($existing->fresh())
        ->google_id->toBeNull()
        ->email->toBe('Test.User@Example.COM')
        ->email_verified_at->toBeNull();

    Mail::assertSent(LinkGoogleAccountMail::class);
});

test('confirmLink は署名メールの対象ユーザーへ google_id を連携しメール認証済みにする', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'confirm@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com'))->toBeTrue();

    expect($existing->fresh())
        ->google_id->toBe('google-confirm')
        ->email_verified_at->not->toBeNull();
});

test('confirmLink は payload email の casing が異なっても署名メールの対象ユーザーへ連携する', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'Confirm.User@Example.COM',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm.user@example.com'))->toBeTrue();

    expect($existing->fresh())
        ->google_id->toBe('google-confirm')
        ->email->toBe('Confirm.User@Example.COM')
        ->email_verified_at->not->toBeNull();
});

test('confirmLink は署名メールの対象ユーザーと payload email が異なる場合 false を返す', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'confirm@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'other@example.com'))->toBeFalse();

    expect($existing->fresh())
        ->google_id->toBeNull()
        ->email_verified_at->toBeNull();
});

test('confirmLink は署名メールの userId を lock 付きで取得する', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'confirm@example.com',
        'google_id' => null,
    ]);

    $fakeRepo = new class($existing) implements UserRepositoryContract
    {
        public string|int|null $foundId = null;

        public bool $foundWithLock = false;

        public bool $findByEmailCalled = false;

        public function __construct(private readonly User $existingUser) {}

        public function find(string|int $id, bool $lock = false): ?User
        {
            $this->foundId = $id;
            $this->foundWithLock = $lock;

            return $this->existingUser;
        }

        public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
        {
            throw new BadMethodCallException;
        }

        public function findByGoogleId(string $googleId, bool $lock = false): ?User
        {
            throw new BadMethodCallException;
        }

        public function findByEmail(string $email, bool $lock = false): ?User
        {
            $this->findByEmailCalled = true;

            return null;
        }

        public function create(array $attributes): User
        {
            throw new BadMethodCallException;
        }

        public function save(User $user): void
        {
            $user->save();
        }

        public function updatePassword(User $user, string $newPassword): void
        {
            throw new BadMethodCallException;
        }
    };

    app()->instance(UserRepositoryContract::class, $fakeRepo);
    $service = app(GoogleAuthService::class);

    expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com'))->toBeTrue()
        ->and($fakeRepo->foundId)->toBe((string) $existing->getKey())
        ->and($fakeRepo->foundWithLock)->toBeTrue()
        ->and($fakeRepo->findByEmailCalled)->toBeFalse();
});

test('confirmLink は対象ユーザーが異なる場合 false を返す', function () {
    User::factory()->create([
        'email' => 'confirm@example.com',
        'google_id' => null,
    ]);

    $service = app(GoogleAuthService::class);

    expect($service->confirmLink('999999', 'google-confirm', 'confirm@example.com'))->toBeFalse();
});
