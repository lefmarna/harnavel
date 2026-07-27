<?php

declare(strict_types=1);

describe('XServer 前提の環境設定', function () {
    it('.env.example の QUEUE_CONNECTION は sync である', function () {
        expect(settingLine('.env.example', 'QUEUE_CONNECTION='))
            ->toBe('QUEUE_CONNECTION=sync');
    });
});
