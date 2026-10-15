<?php

declare(strict_types=1);

use App\Http\Middleware\RejectRevokedSessions;
use App\Models\User;

mutates(RejectRevokedSessions::class);

// 共有端末で「戻る」を押しても、前の利用者の画面を出さない（TADR-0016）。
describe('ブラウザの履歴に残る画面データ', function () {
    it('画面のデータを暗号化して履歴に残す', function () {
        $page = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->inertiaPage();

        expect($page)->toHaveKey('encryptHistory', true);
    });

    it('ログインを終えた後の画面で、それまでの履歴を消す', function (Closure $end) {
        $user = User::factory()->create();

        $response = $end($this->followingRedirects(), $user);

        expect($response->inertiaPage())->toHaveKey('clearHistory', true);
    })->with([
        'ログアウト' => fn ($test, User $user) => $test->actingAs($user)->post(route('logout')),
        '退会' => fn ($test, User $user) => $test->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.destroy')),
        '失効したセッションの拒否' => function ($test, User $user) {
            $user->revokeSessions();
            $user->save();

            return $test->actingAs($user)->get(route('dashboard'));
        },
    ]);
});
