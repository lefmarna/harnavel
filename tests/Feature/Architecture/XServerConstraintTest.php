<?php

declare(strict_types=1);

describe('XServer 前提の環境設定', function () {
    it('.env.example の QUEUE_CONNECTION は sync である', function () {
        $envExample = file_get_contents(base_path('.env.example'));

        expect($envExample)->not->toBeFalse();

        $queueConnection = collect(explode("\n", (string) $envExample))
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => str_starts_with($line, 'QUEUE_CONNECTION='));

        expect($queueConnection)->toBe('QUEUE_CONNECTION=sync');
    });
});
