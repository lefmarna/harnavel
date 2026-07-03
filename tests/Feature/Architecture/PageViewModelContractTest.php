<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Inertia\ProvidesInertiaProperties;

describe('ViewModel の Inertia 契約', function () {
    it('PageViewModel で終わるクラスは ProvidesInertiaProperties を実装する', function () {
        $pageViewModels = collect(File::allFiles(app_path('ViewModels')))
            ->map(fn ($file): string => 'App\\ViewModels\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            ))
            ->filter(fn (string $class): bool => str_ends_with($class, 'PageViewModel'))
            ->values();

        expect($pageViewModels)->not->toBeEmpty();

        $violations = $pageViewModels
            ->reject(fn (string $class): bool => is_subclass_of($class, ProvidesInertiaProperties::class))
            ->values()
            ->all();

        expect($violations)->toBe([]);
    });
});
