<?php

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\GoogleAuth\GoogleAuthService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

describe('Google 認証サービス', function () {
    it('新規ユーザーを作成する', function () {
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

    it('Google プロフィール名が null、空、空白のみのときは email を name に使う', function (?string $blankName) {
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

    it('既存メールユーザーには確認メールを送信し即時連携しない', function () {
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

    it('lowercase usernamesが無効なら lowercase のGoogleメールでも mixed-case メールの既存ユーザーに確認メールを送信し casing を維持する', function () {
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

    it('既存 google_id ユーザーをそのまま返す', function () {
        $existing = User::factory()->create([
            'google_id' => 'google-789',
            'email' => 'googleuser@example.com',
        ]);

        $service = app(GoogleAuthService::class);
        $user = $service->findOrCreate('google-789', 'googleuser@example.com', 'Google User');

        expect($user->id)->toBe($existing->id);
        $this->assertDatabaseCount('users', 1);
    });

    it('google_id 未連携の既存ユーザーは email_verified_at が null でも確認メール待ちにする', function () {
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

    it('google_id ユーザーとメールユーザーが別レコードの場合 GoogleAuthException を投げる', function () {
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

    it('別の google_id がすでに連携済みの場合 GoogleAuthException を投げる', function () {
        User::factory()->create([
            'email' => 'linked@example.com',
            'google_id' => 'old-google-id',
        ]);

        $service = app(GoogleAuthService::class);

        expect(fn () => $service->findOrCreate('new-google-id', 'linked@example.com', 'User'))
            ->toThrow(GoogleAuthException::class, 'このメールアドレスは別のGoogleアカウントに連携済みです。');
    });

    it('二段階認証が有効な既存ユーザーもサービス層では返却する', function () {
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

    it('交差入力（google_id と email が別ユーザーに対応）で衝突 GoogleAuthException を投げる', function () {
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

    it('UniqueConstraintViolationException 発生時にフォールバックで既存ユーザーを返す', function () {
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

    it('UniqueConstraintViolationException 後に findByEmail で既存メールユーザーを取得した場合は確認メールを送信する', function () {
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

    it('UniqueConstraintViolationException 後のフォールバックでも別レコード衝突なら GoogleAuthException を投げる', function () {
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

    it('lowercase usernamesが無効なら UniqueConstraintViolationException 後も mixed-case メールの既存ユーザーに確認メールを送信する', function () {
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

    it('confirmLink は署名メールの対象ユーザーへ google_id を連携しメール認証済みにする', function () {
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

    it('confirmLink は payload email の casing が異なっても署名メールの対象ユーザーへ連携する', function () {
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

    it('confirmLink は署名メールの対象ユーザーと payload email が異なる場合 false を返す', function () {
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

    it('confirmLink は署名メールの userId を lock 付きで取得する', function () {
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

    it('confirmLink は対象ユーザーが異なる場合 false を返す', function () {
        User::factory()->create([
            'email' => 'confirm@example.com',
            'google_id' => null,
        ]);

        $service = app(GoogleAuthService::class);

        expect($service->confirmLink('999999', 'google-confirm', 'confirm@example.com'))->toBeFalse();
    });

    describe('リンク確認の未仕様分岐', function () {
        it('同じ google_id で再確認した場合 true になる', function () {
            $existing = User::factory()->create([
                'email' => 'confirmed@example.com',
                'google_id' => 'google-confirmed',
            ]);

            $fakeRepo = new class($existing) implements UserRepositoryContract
            {
                public int $saveCount = 0;

                public function __construct(private readonly User $existingUser) {}

                public function find(string|int $id, bool $lock = false): ?User
                {
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
                    throw new BadMethodCallException;
                }

                public function create(array $attributes): User
                {
                    throw new BadMethodCallException;
                }

                public function save(User $user): void
                {
                    $this->saveCount++;
                }

                public function updatePassword(User $user, string $newPassword): void
                {
                    throw new BadMethodCallException;
                }
            };

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'google-confirmed', 'confirmed@example.com'))->toBeTrue()
                ->and($fakeRepo->saveCount)->toBe(0);
        });

        it('別の google_id が既に連携済みの場合 false になる', function () {
            $existing = User::factory()->create([
                'email' => 'already-linked@example.com',
                'google_id' => 'old-google',
            ]);

            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'new-google', 'already-linked@example.com'))->toBeFalse();

            expect($existing->fresh())
                ->google_id->toBe('old-google');
        });

        it('google_id 連携保存がユニーク制約で競合しても同じユーザーへ連携済みなら true になる', function () {
            $existing = User::factory()->unverified()->create([
                'email' => 'race-confirm@example.com',
                'google_id' => null,
            ]);

            $fakeRepo = new class($existing) implements UserRepositoryContract
            {
                public int $saveCount = 0;

                public function __construct(private readonly User $existingUser) {}

                public function find(string|int $id, bool $lock = false): ?User
                {
                    return $this->existingUser;
                }

                public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
                {
                    throw new BadMethodCallException;
                }

                public function findByGoogleId(string $googleId, bool $lock = false): ?User
                {
                    return $this->existingUser;
                }

                public function findByEmail(string $email, bool $lock = false): ?User
                {
                    throw new BadMethodCallException;
                }

                public function create(array $attributes): User
                {
                    throw new BadMethodCallException;
                }

                public function save(User $user): void
                {
                    $this->saveCount++;

                    throw testingUniqueConstraintViolation();
                }

                public function updatePassword(User $user, string $newPassword): void
                {
                    throw new BadMethodCallException;
                }
            };

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'race-google', 'race-confirm@example.com'))->toBeTrue()
                ->and($fakeRepo->saveCount)->toBe(1);
        });
    });

    describe('ユニーク制約競合の未仕様分岐', function () {
        it('既存ユーザーを回復できないユニーク制約競合が続く場合ログを出して GoogleAuthException になる', function () {
            $fakeRepo = new class implements UserRepositoryContract
            {
                public int $saveCount = 0;

                public int $googleIdLookupCount = 0;

                public int $emailLookupCount = 0;

                public function find(string|int $id, bool $lock = false): ?User
                {
                    throw new BadMethodCallException;
                }

                public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
                {
                    return collect();
                }

                public function findByGoogleId(string $googleId, bool $lock = false): ?User
                {
                    $this->googleIdLookupCount++;

                    return null;
                }

                public function findByEmail(string $email, bool $lock = false): ?User
                {
                    $this->emailLookupCount++;

                    return null;
                }

                public function create(array $attributes): User
                {
                    throw new BadMethodCallException;
                }

                public function save(User $user): void
                {
                    $this->saveCount++;

                    throw testingUniqueConstraintViolation();
                }

                public function updatePassword(User $user, string $newPassword): void
                {
                    throw new BadMethodCallException;
                }
            };

            Log::shouldReceive('warning')
                ->once()
                ->with('Google OAuth: UniqueConstraintViolationException but user not found.', [
                    'google_id' => 'retry-google',
                ]);

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect(fn () => $service->findOrCreate('retry-google', 'retry@example.com', 'Retry User'))
                ->toThrow(GoogleAuthException::class, 'Google認証に失敗しました。もう一度お試しください。');

            expect($fakeRepo->saveCount)->toBe(5)
                ->and($fakeRepo->googleIdLookupCount)->toBe(5)
                ->and($fakeRepo->emailLookupCount)->toBe(5);
        });
    });

    describe('既存 Google アカウント同期の未仕様分岐', function () {
        it('既存 google_id ユーザーの email_verified_at が未設定の場合補完できる', function () {
            $existing = User::factory()->unverified()->create([
                'email' => 'unverified-google@example.com',
                'google_id' => 'unverified-google',
            ]);

            $service = app(GoogleAuthService::class);

            $user = $service->findOrCreate('unverified-google', 'unverified-google@example.com', 'Google User');

            expect($user->is($existing))->toBeTrue()
                ->and($existing->fresh()->email_verified_at)->not->toBeNull();
        });

        it('lowercase usernames が有効な場合既存 google_id ユーザーの email を lowercase に同期できる', function () {
            config(['fortify.lowercase_usernames' => true]);

            $existing = User::factory()->create([
                'email' => 'Mixed.User@Example.COM',
                'google_id' => 'mixed-google',
            ]);

            $service = app(GoogleAuthService::class);

            $user = $service->findOrCreate('mixed-google', 'Mixed.User@Example.COM', 'Google User');

            expect($user->is($existing))->toBeTrue()
                ->and($existing->fresh()->email)->toBe('mixed.user@example.com');
        });

        it('既存 google_id ユーザーに同期差分がない場合保存しない', function () {
            $existing = User::factory()->create([
                'email' => 'stable@example.com',
                'google_id' => 'stable-google',
            ]);

            $fakeRepo = new class($existing) implements UserRepositoryContract
            {
                public int $saveCount = 0;

                public function __construct(private readonly User $existingUser) {}

                public function find(string|int $id, bool $lock = false): ?User
                {
                    throw new BadMethodCallException;
                }

                public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
                {
                    return collect([$this->existingUser]);
                }

                public function findByGoogleId(string $googleId, bool $lock = false): ?User
                {
                    throw new BadMethodCallException;
                }

                public function findByEmail(string $email, bool $lock = false): ?User
                {
                    throw new BadMethodCallException;
                }

                public function create(array $attributes): User
                {
                    throw new BadMethodCallException;
                }

                public function save(User $user): void
                {
                    $this->saveCount++;
                }

                public function updatePassword(User $user, string $newPassword): void
                {
                    throw new BadMethodCallException;
                }
            };

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            $user = $service->findOrCreate('stable-google', 'stable@example.com', 'Stable User');

            expect($user->is($existing))->toBeTrue()
                ->and($fakeRepo->saveCount)->toBe(0);
        });
    });
});

function testingUniqueConstraintViolation(): UniqueConstraintViolationException
{
    return new UniqueConstraintViolationException(
        'testing',
        'INSERT INTO `users`',
        [],
        new PDOException('Integrity constraint violation')
    );
}
