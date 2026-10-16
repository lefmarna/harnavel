<?php

declare(strict_types=1);

use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthService;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function unlinkGoogle(User $user): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->from(route('security.edit'))
        ->delete(route('security.google.destroy'));
}

describe('Google ログイン連携の解除', function () {
    it('セキュリティ設定に連携の有無と解除できるかを渡す', function (array $attributes, bool $withPasskey, bool $linked, bool $canUnlink) {
        $user = User::factory()->create($attributes);

        if ($withPasskey) {
            $user->passkeys()->create(['name' => 'Key', 'credential_id' => 'credential-1', 'credential' => []]);
        }

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('googleLinked', $linked)
                ->where('canUnlinkGoogle', $canUnlink));
    })->with([
        '連携なし' => [['google_id' => null], false, false, false],
        'パスワードあり' => [['google_id' => 'google-1'], false, true, true],
        'パスキーだけ' => [['google_id' => 'google-1', 'password' => null], true, true, false],
        'Google だけ' => [['google_id' => 'google-1', 'password' => null], false, true, false],
    ]);

    it('連携を解除すると他の端末のセッションを失効させ、今の端末はログインを保つ', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['google_id' => 'google-1']);
        $otherDeviceSessionId = loginFromNewDevice($user);
        $currentSessionId = loginFromNewDevice($user);

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->from(route('security.edit'))
            ->delete(route('security.google.destroy'))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status', 'google-unlinked');
        $renewedSessionId = lastSessionId();

        expect($user->refresh()->google_id)->toBeNull()
            ->and($renewedSessionId)->not->toBe($currentSessionId);
        visitDashboardFrom($renewedSessionId)->assertOk();
        visitDashboardFrom($otherDeviceSessionId)->assertRedirect(route('login'));
    });

    it('連携の解除を記録する', function () {
        Log::spy();
        $user = User::factory()->create(['google_id' => 'google-1']);

        unlinkGoogle($user)->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')->with('Google account was unlinked.', ['user_id' => $user->getKey()])->once();
    });

    it('パスワードがなければ、パスキーがあっても解除しない', function (bool $withPasskey) {
        $user = User::factory()->create(['google_id' => 'google-1', 'password' => null]);

        if ($withPasskey) {
            $user->passkeys()->create(['name' => 'Key', 'credential_id' => 'credential-1', 'credential' => []]);
        }

        unlinkGoogle($user)->assertSessionHasErrors([
            'google' => 'Googleログイン連携を解除する前に、パスワードを設定してください。',
        ]);

        expect($user->refresh()->google_id)->toBe('google-1');
    })->with([
        'パスキーなし' => false,
        'パスキーあり' => true,
    ]);

    it('セッションを失効させた後は、その前に送った連携の確認リンクで連携できない', function () {
        $user = User::factory()->create(['google_id' => null]);
        $user->revokeSessions();
        $user->save();

        expect(app(GoogleAuthService::class)->confirmLink((string) $user->getKey(), 'google-1', $user->email, '0'))->toBeFalse()
            ->and($user->refresh()->google_id)->toBeNull();
    });

    it('連携の確認リンクを送るときに、その時点のセッションの世代を含める', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com', 'google_id' => null, 'session_generation' => 3]);

        expect(fn () => app(GoogleAuthService::class)->findOrCreate('google-1', 'owner@example.com', 'Owner'))
            ->toThrow(GoogleLinkConfirmationPendingException::class);

        Mail::assertSent(LinkGoogleAccountMail::class, function (LinkGoogleAccountMail $mail): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);
            $payload = json_decode(Crypt::decryptString($query['payload']), true);

            return $payload['session_generation'] === '3';
        });
    });

    it('解除の途中でセッションが失効したら、その解除を拒否する', function () {
        $user = User::factory()->create(['google_id' => 'google-1']);
        Event::listen(RouteMatched::class, function () use ($user): void {
            $fresh = User::findOrFail($user->getKey());
            $fresh->revokeSessions();
            $fresh->save();
        });

        unlinkGoogle($user)->assertConflict();

        expect($user->refresh()->google_id)->toBe('google-1');
    });
});
