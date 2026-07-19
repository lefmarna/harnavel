<?php

declare(strict_types=1);

describe('静的解析の前提設定', function () {
    // フラグを外しても level: max は green のまま型推論だけが劣化するため、テストで固定する（TADR-0007）。
    it('phpstan.neon.dist は parseModelCastsMethod を有効にしている', function () {
        $config = file_get_contents(base_path('phpstan.neon.dist'));

        expect($config)->not->toBeFalse();

        $flagLine = collect(explode("\n", (string) $config))
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => str_starts_with($line, 'parseModelCastsMethod:'));

        expect($flagLine)->toBe('parseModelCastsMethod: true');
    });
});
