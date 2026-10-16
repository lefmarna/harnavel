<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Log;

describe('他のすべての端末からログアウト', function () {
    it('他の端末のセッションを失効させ、今の端末はログインを保つ', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $otherDeviceSessionId = loginFromNewDevice($user);
        $currentSessionId = loginFromNewDevice($user);

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->delete(route('security.sessions.destroy'))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status', 'other-sessions-logged-out');
        $renewedSessionId = lastSessionId();

        expect($renewedSessionId)->not->toBe($currentSessionId);
        visitDashboardFrom($renewedSessionId)->assertOk();
        visitDashboardFrom($otherDeviceSessionId)->assertRedirect(route('login'));
    });

    it('他の端末のログアウトを記録する', function () {
        Log::spy();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('security.sessions.destroy'))
            ->assertRedirect();

        Log::shouldHaveReceived('info')->with('Other sessions were logged out.', ['user_id' => $user->getKey()])->once();
    });

    it('本人確認が切れていれば確認を求める', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('security.sessions.destroy'))
            ->assertRedirect(route('password.confirm'));

        expect($user->refresh()->session_generation)->toBe(0);
    });
});
