<?php

declare(strict_types=1);

describe('ホームページ', function () {
    it('成功レスポンスを返す', function () {
        $response = $this->get(route('home'));

        $response->assertOk();
    });
});
