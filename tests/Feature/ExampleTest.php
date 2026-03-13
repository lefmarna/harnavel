<?php

test('成功レスポンスを返す', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
