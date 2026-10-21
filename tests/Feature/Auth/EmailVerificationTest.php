<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

describe('メール認証', function () {
    it('メール認証できる', function () {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
        $response->assertRedirect(config('fortify.home').'?verified=1');
    });

    it('確認の途中でアドレスが切り替わっていたら、確認日時を書き戻さない', function () {
        $this->freezeSecond();
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('old@example.com')]
        );
        $switchedAt = now()->addSeconds(2);
        User::query()->whereKey($user->getKey())->update(['email' => 'new@example.com', 'email_verified_at' => $switchedAt]);

        $this->actingAs($user)->get($verificationUrl);

        $user->refresh();
        expect($user->email)->toBe('new@example.com')
            ->and($user->email_verified_at?->equalTo($switchedAt))->toBeTrue();
    });

    it('無効なハッシュではメール認証されない', function () {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('無効なユーザーIDではメール認証されない', function () {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => User::factory()->create()->getKey(), 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });
});
