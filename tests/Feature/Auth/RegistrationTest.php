<?php

test('登録画面を表示できる', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
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
