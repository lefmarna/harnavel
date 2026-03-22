<?php

use Inertia\Testing\AssertableInertia as Assert;

test('登録画面を表示できる', function () {
    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('auth/register')
                ->where('seo.title', '新規登録')
                ->where('seo.description', '必要事項を入力して新しいアカウントを作成します。')
        );
});

test('新規ユーザーを登録できる', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

test('登録時のメールアドレスは小文字で保存される', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'Test.User@Example.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'test.user@example.com',
    ]);
});

test('lowercase usernamesが無効なら登録時にメールアドレスの大文字小文字を維持する', function () {
    config(['fortify.lowercase_usernames' => false]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'Test.User@Example.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'Test.User@Example.COM',
    ]);
});
