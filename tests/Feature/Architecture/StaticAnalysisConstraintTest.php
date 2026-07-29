<?php

declare(strict_types=1);

describe('静的解析の前提設定', function () {
    it('phpstan.neon.dist は parseModelCastsMethod を有効にしている', function () {
        expect(settingLine('phpstan.neon.dist', 'parseModelCastsMethod:'))
            ->toBe('parseModelCastsMethod: true');
    });
});
