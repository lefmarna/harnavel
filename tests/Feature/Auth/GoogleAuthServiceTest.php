<?php

declare(strict_types=1);

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\GoogleAuth\GoogleAuthService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeUserRepository;

describe('Google 認証サービス', function () {
    it('新規ユーザーを作成する', function () {
        $service = app(GoogleAuthService::class);

        $user = $service->findOrCreate('google-123', 'new@example.com', 'New User');

        expect($user->google_id)->toBe('google-123')
            ->and($user->email)->toBe('new@example.com')
            ->and($user->name)->toBe('New User')
            ->and($user->email_verified_at)->not->toBeNull();

        $this->assertDatabaseHas('users', [
            'google_id' => 'google-123',
            'email' => 'new@example.com',
            'name' => 'New User',
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

    it('既存メールユーザーへの確認メールURLに対象ユーザーとGoogle連携payloadを含められる', function () {
        Mail::fake();

        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        $service = app(GoogleAuthService::class);

        expect(fn () => $service->findOrCreate('google-456', 'existing@example.com', 'Existing User'))
            ->toThrow(GoogleLinkConfirmationPendingException::class);

        Mail::assertSent(LinkGoogleAccountMail::class, function (LinkGoogleAccountMail $mail) use ($existing): bool {
            $parts = parse_url($mail->url);
            expect($parts['path'] ?? null)->toBe('/auth/google/link/verify/'.$existing->getKey());

            parse_str((string) ($parts['query'] ?? ''), $query);

            expect($query)
                ->toHaveKeys(['expires', 'payload', 'signature'])
                ->and($query['payload'])->toBeString()->not->toBeEmpty();

            /** @var array{google_id?: mixed, email?: mixed, session_generation?: mixed} $payload */
            $payload = json_decode(Crypt::decryptString($query['payload']), true, flags: JSON_THROW_ON_ERROR);

            expect($payload)->toBe([
                'google_id' => 'google-456',
                'email' => 'existing@example.com',
                'session_generation' => '0',
            ]);

            return true;
        });
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

    it('lowercase usernamesが非 bool の真値でも bool 化して email を小文字化する', function () {
        config(['fortify.lowercase_usernames' => 1]);

        $service = app(GoogleAuthService::class);
        $user = $service->findOrCreate('google-int-config', 'Int.Config@Example.COM', 'Int Config');

        expect($user->email)->toBe('int.config@example.com');
    });

    it('lowercase usernamesが有効でレガシーな大小混在メールの既存ユーザーがいる場合は一意制約リカバリ経由で連携確認待ちになる', function () {
        Mail::fake();
        config(['fortify.lowercase_usernames' => true]);

        $existing = User::factory()->create([
            'email' => 'Mixed.Case@Example.COM',
            'google_id' => null,
        ]);

        $service = app(GoogleAuthService::class);

        // 厳密一致の候補照合ではレガシーな大小混在メールにヒットせず INSERT へ進むが、
        // email の一意制約は utf8mb4_general_ci で大小を区別しないため重複となり、
        // 一意制約リカバリ（findByEmail）で既存ユーザーへの連携確認待ちに収束する。
        expect(fn () => $service->findOrCreate('google-legacy-mixed', 'mixed.case@example.com', 'Legacy User'))
            ->toThrow(GoogleLinkConfirmationPendingException::class);

        expect(User::query()->count())->toBe(1)
            ->and(User::query()->sole()->email)->toBe('Mixed.Case@Example.COM')
            ->and(User::query()->sole()->google_id)->toBeNull();

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

    it('別の google_id がすでに連携済みの場合 GoogleAuthException を投げる', function () {
        User::factory()->create([
            'email' => 'linked@example.com',
            'google_id' => 'old-google-id',
        ]);

        $service = app(GoogleAuthService::class);

        expect(fn () => $service->findOrCreate('new-google-id', 'linked@example.com', 'User'))
            ->toThrow(GoogleAuthException::class, 'このメールアドレスは別のGoogleアカウントに連携済みです。');
    });

    it('連携済みの Google ID なら、Google のメールアドレスを別のユーザーが使っていてもそのユーザーでログインする', function () {
        $linked = User::factory()->create([
            'google_id' => 'google-X',
            'email' => 'x@example.com',
        ]);
        $other = User::factory()->create([
            'google_id' => null,
            'email' => 'y@example.com',
        ]);

        $user = app(GoogleAuthService::class)->findOrCreate('google-X', 'y@example.com', 'User');

        expect($user->is($linked))->toBeTrue()
            ->and($linked->refresh()->email)->toBe('x@example.com')
            ->and($other->refresh()->google_id)->toBeNull();
    });

    it('UniqueConstraintViolationException 発生時にフォールバックで既存ユーザーを返す', function () {
        $existing = User::factory()->create([
            'email' => 'race@example.com',
            'google_id' => 'race-google',
        ]);

        // トランザクション内では先行リクエストがまだ見えず、save が一意制約で落ちたあとの
        // フォールバックで初めて既存ユーザーが見える、という競合状態を作る。
        $fakeRepo = new FakeUserRepository(
            findCandidates: fn (): Collection => collect(),
            findByGoogleId: fn (): User => $existing,
            findByEmail: fn (): null => null,
            save: fn () => throw testingUniqueConstraintViolation(),
        );

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

        $saveCount = 0;
        $fakeRepo = new FakeUserRepository(
            findCandidates: fn (): Collection => collect(),
            findByGoogleId: fn (): null => null,
            findByEmail: fn (): User => $existing,
            // 2 回目（フォールバック内の更新保存）は成功させる。
            save: function () use (&$saveCount): void {
                if ($saveCount++ === 0) {
                    throw testingUniqueConstraintViolation();
                }
            },
        );

        app()->instance(UserRepositoryContract::class, $fakeRepo);
        $service = app(GoogleAuthService::class);

        expect(fn () => $service->findOrCreate('new-google', 'fallback@example.com', 'Fallback User'))
            ->toThrow(GoogleLinkConfirmationPendingException::class);

        expect($existing->fresh())
            ->google_id->toBeNull()
            ->email_verified_at->toBeNull();

        Mail::assertSent(LinkGoogleAccountMail::class);
    });

    it('UniqueConstraintViolationException 後のフォールバックでも、連携済みの Google ID のユーザーでログインする', function () {
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
        $fakeRepo = new FakeUserRepository(
            findCandidates: fn (): Collection => collect(),
            findByGoogleId: fn (): User => $userA,
            findByEmail: fn (): User => $userB,
            save: fn () => throw testingUniqueConstraintViolation(),
        );

        app()->instance(UserRepositoryContract::class, $fakeRepo);
        $service = app(GoogleAuthService::class);

        expect($service->findOrCreate('google-A', 'b@example.com', 'User')->is($userA))->toBeTrue();
    });

    it('lowercase usernamesが無効なら UniqueConstraintViolationException 後も mixed-case メールの既存ユーザーに確認メールを送信する', function () {
        Mail::fake();
        config(['fortify.lowercase_usernames' => false]);

        $existing = User::factory()->unverified()->create([
            'email' => 'Test.User@Example.COM',
            'google_id' => null,
        ]);

        $saveCount = 0;
        $fakeRepo = new FakeUserRepository(
            findCandidates: fn (): Collection => collect(),
            findByGoogleId: fn (): null => null,
            findByEmail: fn (): User => $existing,
            save: function () use (&$saveCount): void {
                if ($saveCount++ === 0) {
                    throw testingUniqueConstraintViolation();
                }
            },
        );

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

        expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com', '0'))->toBeTrue();

        expect($existing->fresh())
            ->google_id->toBe('google-confirm')
            ->email_verified_at->not->toBeNull();
    });

    // 第三者が他人のメールで先に登録したアカウントを、持ち主の連携後も使い続けられないようにする。
    it('confirmLink はメール未確認のアカウントへ連携するとき既存のパスワード・パスキー・セッションを無効にする', function () {
        $existing = User::factory()->unverified()->create([
            'email' => 'confirm@example.com',
            'google_id' => null,
            'remember_token' => 'old-remember-token',
        ]);
        $existing->passkeys()->create([
            'name' => 'Attacker key',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        expect(app(GoogleAuthService::class)->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com', '0'))->toBeTrue();

        $existing->refresh();

        expect($existing->password)->toBeNull()
            ->and($existing->remember_token)->not->toBe('old-remember-token')
            ->and($existing->passkeys()->count())->toBe(0)
            ->and($existing->session_generation)->toBe(1);
    });

    it('confirmLink はメール確認済みのアカウントへ連携しても既存のパスワード・パスキー・セッションを維持する', function () {
        $existing = User::factory()->create([
            'email' => 'confirm@example.com',
            'google_id' => null,
            'remember_token' => 'old-remember-token',
        ]);
        $existing->passkeys()->create([
            'name' => 'MacBook Pro',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);
        $password = $existing->password;

        expect(app(GoogleAuthService::class)->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com', '0'))->toBeTrue();

        $existing->refresh();

        expect($existing->password)->toBe($password)
            ->and($existing->remember_token)->toBe('old-remember-token')
            ->and($existing->passkeys()->count())->toBe(1)
            ->and($existing->session_generation)->toBe(0);
    });

    it('confirmLink は payload email の casing が異なっても署名メールの対象ユーザーへ連携する', function () {
        $existing = User::factory()->unverified()->create([
            'email' => 'Confirm.User@Example.COM',
            'google_id' => null,
        ]);

        $service = app(GoogleAuthService::class);

        expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm.user@example.com', '0'))->toBeTrue();

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

        expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'other@example.com', '0'))->toBeFalse();

        expect($existing->fresh())
            ->google_id->toBeNull()
            ->email_verified_at->toBeNull();
    });

    it('confirmLink は署名メールの userId を lock 付きで取得する', function () {
        $existing = User::factory()->unverified()->create([
            'email' => 'confirm@example.com',
            'google_id' => null,
        ]);

        $foundId = null;
        $foundWithLock = false;

        // findByEmail は差し込まない。confirmLink がそこへ回ると BadMethodCallException で落ちる。
        $fakeRepo = new FakeUserRepository(
            find: function (string|int $id, bool $lock) use ($existing, &$foundId, &$foundWithLock): User {
                $foundId = $id;
                $foundWithLock = $lock;

                return $existing;
            },
            save: fn (User $user) => $user->save(),
        );

        app()->instance(UserRepositoryContract::class, $fakeRepo);
        $service = app(GoogleAuthService::class);

        expect($service->confirmLink((string) $existing->getKey(), 'google-confirm', 'confirm@example.com', '0'))->toBeTrue()
            ->and($foundId)->toBe((string) $existing->getKey())
            ->and($foundWithLock)->toBeTrue();
    });

    it('confirmLink は対象ユーザーが異なる場合 false を返す', function () {
        User::factory()->create([
            'email' => 'confirm@example.com',
            'google_id' => null,
        ]);

        $service = app(GoogleAuthService::class);

        expect($service->confirmLink('999999', 'google-confirm', 'confirm@example.com', '0'))->toBeFalse();
    });

    it('findOrCreate は findCandidates を行ロック付きで取得する', function () {
        $findCandidatesWithLock = false;
        $fakeRepo = new FakeUserRepository(
            findCandidates: function (string $googleId, string $email, bool $lock) use (&$findCandidatesWithLock): Collection {
                $findCandidatesWithLock = $lock;

                return collect();
            },
            save: fn (User $user) => $user->save(),
        );

        app()->instance(UserRepositoryContract::class, $fakeRepo);

        app(GoogleAuthService::class)->findOrCreate('lock-google', 'lock@example.com', 'Lock User');

        expect($findCandidatesWithLock)->toBeTrue();
    });

    describe('リンク確認の未仕様分岐', function () {
        it('同じ google_id で再確認した場合 true になる', function () {
            $existing = User::factory()->create([
                'email' => 'confirmed@example.com',
                'google_id' => 'google-confirmed',
            ]);

            $saveCount = 0;
            $fakeRepo = new FakeUserRepository(
                find: fn (): User => $existing,
                save: function () use (&$saveCount): void {
                    $saveCount++;
                },
            );

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'google-confirmed', 'confirmed@example.com', '0'))->toBeTrue()
                ->and($saveCount)->toBe(0);
        });

        it('別の google_id が既に連携済みの場合 false になる', function () {
            $existing = User::factory()->create([
                'email' => 'already-linked@example.com',
                'google_id' => 'old-google',
            ]);

            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'new-google', 'already-linked@example.com', '0'))->toBeFalse();

            expect($existing->fresh())
                ->google_id->toBe('old-google');
        });

        it('google_id 連携保存がユニーク制約で競合しても同じユーザーへ連携済みなら true になる', function () {
            $existing = User::factory()->unverified()->create([
                'email' => 'race-confirm@example.com',
                'google_id' => null,
            ]);

            $saveCount = 0;
            $fakeRepo = new FakeUserRepository(
                find: fn (): User => $existing,
                findByGoogleId: fn (): User => $existing,
                save: function () use (&$saveCount): void {
                    $saveCount++;

                    throw testingUniqueConstraintViolation();
                },
            );

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect($service->confirmLink((string) $existing->getKey(), 'race-google', 'race-confirm@example.com', '0'))->toBeTrue()
                ->and($saveCount)->toBe(1);
        });
    });

    describe('ユニーク制約競合の未仕様分岐', function () {
        it('既存ユーザーを回復できないユニーク制約競合が続く場合ログを出して GoogleAuthException になる', function () {
            $saveCount = 0;
            $googleIdLookupCount = 0;
            $emailLookupCount = 0;

            $fakeRepo = new FakeUserRepository(
                findCandidates: fn (): Collection => collect(),
                findByGoogleId: function () use (&$googleIdLookupCount): null {
                    $googleIdLookupCount++;

                    return null;
                },
                findByEmail: function () use (&$emailLookupCount): null {
                    $emailLookupCount++;

                    return null;
                },
                save: function () use (&$saveCount): void {
                    $saveCount++;

                    throw testingUniqueConstraintViolation();
                },
            );

            Log::shouldReceive('warning')
                ->once()
                ->with('Google OAuth: UniqueConstraintViolationException but user not found.', [
                    'google_id' => 'retry-google',
                ]);

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            expect(fn () => $service->findOrCreate('retry-google', 'retry@example.com', 'Retry User'))
                ->toThrow(GoogleAuthException::class, 'Google認証に失敗しました。もう一度お試しください。');

            expect($saveCount)->toBe(5)
                ->and($googleIdLookupCount)->toBe(5)
                ->and($emailLookupCount)->toBe(5);
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

        // Google ID の一致はログインの根拠になるが、アカウントのメールの所有を示すのは同じアドレスのときだけ。
        it('既存 google_id ユーザーのメールが Google と異なる場合は書き換えず確認済みにもしない', function (bool $lowercaseUsernames) {
            config(['fortify.lowercase_usernames' => $lowercaseUsernames]);

            $existing = User::factory()->unverified()->create([
                'email' => 'changed@example.com',
                'google_id' => 'changed-google',
            ]);

            $user = app(GoogleAuthService::class)->findOrCreate('changed-google', 'original@example.com', 'Google User');

            $existing->refresh();

            expect($user->is($existing))->toBeTrue()
                ->and($existing->email)->toBe('changed@example.com')
                ->and($existing->email_verified_at)->toBeNull();
        })->with([
            '小文字化あり' => [true],
            '小文字化なし' => [false],
        ]);

        it('lowercase usernames が無効でも大文字小文字だけ異なる未確認メールは確認済みにする', function () {
            config(['fortify.lowercase_usernames' => false]);

            $existing = User::factory()->unverified()->create([
                'email' => 'Mixed.User@Example.COM',
                'google_id' => 'mixed-google',
            ]);

            app(GoogleAuthService::class)->findOrCreate('mixed-google', 'mixed.user@example.com', 'Google User');

            $existing->refresh();

            expect($existing->email)->toBe('Mixed.User@Example.COM')
                ->and($existing->email_verified_at)->not->toBeNull();
        });

        it('既存 google_id ユーザーに同期差分がない場合保存しない', function () {
            $existing = User::factory()->create([
                'email' => 'stable@example.com',
                'google_id' => 'stable-google',
            ]);

            $saveCount = 0;
            $fakeRepo = new FakeUserRepository(
                findCandidates: fn (): Collection => collect([$existing]),
                save: function () use (&$saveCount): void {
                    $saveCount++;
                },
            );

            app()->instance(UserRepositoryContract::class, $fakeRepo);
            $service = app(GoogleAuthService::class);

            $user = $service->findOrCreate('stable-google', 'stable@example.com', 'Stable User');

            expect($user->is($existing))->toBeTrue()
                ->and($saveCount)->toBe(0);
        });
    });
});
