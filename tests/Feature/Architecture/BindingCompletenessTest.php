<?php

declare(strict_types=1);

describe('サービスコンテナ登録の実在検証', function () {
    it('Contract は singleton 登録され対応する実装へ解決される', function (string $pattern) {
        $files = glob(app_path($pattern));

        expect($files)->not->toBeFalse()
            ->and($files)->not->toBeEmpty("No contract files matched {$pattern}.");

        /** @var list<string> $files */
        foreach ($files as $file) {
            $class = 'App\\'.str_replace(
                [app_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'],
                ['', '\\', ''],
                $file,
            );

            expect(interface_exists($class))->toBeTrue("Contract {$class} should be an interface.")
                ->and(app()->bound($class))->toBeTrue("Contract {$class} should be bound.");

            /** @var class-string $class */
            expect(app()->make($class))
                ->toBeInstanceOf($class)
                ->toBe(app()->make($class), "Binding for {$class} should be shared (singleton).");
        }
    })->with([
        'Repositories' => 'Repositories/*/*RepositoryContract.php',
        'Services' => 'Services/*/*ServiceContract.php',
    ]);
});
