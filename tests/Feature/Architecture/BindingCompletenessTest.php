<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;

describe('サービスコンテナ登録の実在検証', function () {
    it('app/Repositories 配下の全 RepositoryContract が singleton 登録されている', function () {
        foreach (bindingCompletenessContractClasses(app_path('Repositories/*/*RepositoryContract.php')) as $contract) {
            expect(app()->bound($contract))
                ->toBeTrue("Contract {$contract} should be bound.");

            assertBindingCompletenessSingletonBinding($contract);
        }
    });

    it('app/Services 配下の全 ServiceContract が singleton 登録されている', function () {
        foreach (bindingCompletenessContractClasses(app_path('Services/*/*ServiceContract.php')) as $contract) {
            expect(app()->bound($contract))
                ->toBeTrue("Contract {$contract} should be bound.");

            assertBindingCompletenessSingletonBinding($contract);
        }
    });

    it('登録された実装クラスは対応する Contract を実装している', function () {
        foreach (bindingCompletenessAllContractClasses() as $contract) {
            expect(app()->make($contract))
                ->toBeInstanceOf($contract);
        }
    });
});

/**
 * @return list<class-string>
 */
function bindingCompletenessAllContractClasses(): array
{
    return [
        ...bindingCompletenessContractClasses(app_path('Repositories/*/*RepositoryContract.php')),
        ...bindingCompletenessContractClasses(app_path('Services/*/*ServiceContract.php')),
    ];
}

/**
 * @return list<class-string>
 */
function bindingCompletenessContractClasses(string $pattern): array
{
    $files = glob($pattern);

    expect($files)->not->toBeFalse();

    /** @var list<string> $files */
    $files = array_values($files);

    expect($files)->not->toBeEmpty("No contract files matched {$pattern}.");

    return array_map(static function (string $file): string {
        $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file);
        $class = 'App\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);

        expect(interface_exists($class))
            ->toBeTrue("Contract {$class} should be an interface.");

        /** @var class-string $class */
        return $class;
    }, $files);
}

/**
 * @param  class-string  $contract
 *
 * @throws BindingResolutionException
 */
function assertBindingCompletenessSingletonBinding(string $contract): void
{
    $first = app()->make($contract);
    $second = app()->make($contract);

    expect($first)
        ->toBe($second, "Binding for {$contract} should be shared (singleton).");
}
