<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('プロフィール設定画面を表示できる', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('mustVerifyEmail', true)
                ->where('status', null)
                ->where('seo.title', 'プロフィール設定')
                ->where('seo.description', '名前とメールアドレスを管理します。')
        );
});

test('プロフィール情報を更新できる', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('プロフィール更新時のメールアドレスは小文字で保存される', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Test.User@Example.COM',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->email)->toBe('test.user@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('lowercase usernamesが無効ならプロフィール更新時にメールアドレスの大文字小文字を維持する', function () {
    config(['fortify.lowercase_usernames' => false]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Test.User@Example.COM',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('Test.User@Example.COM');
    expect($user->email_verified_at)->toBeNull();
});

test('lowercase usernamesが無効ならメールアドレスの大文字小文字だけの変更ではメール認証状態を維持する', function () {
    config(['fortify.lowercase_usernames' => false]);

    $user = User::factory()->create([
        'email' => 'test.user@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'Test.User@Example.COM',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('Test.User@Example.COM');
    expect($user->email_verified_at)->not->toBeNull();
});

test('メールアドレスが変わらない場合はメール認証状態を維持する', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('アカウントを削除できる', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('アカウント削除には正しいパスワードが必要', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
