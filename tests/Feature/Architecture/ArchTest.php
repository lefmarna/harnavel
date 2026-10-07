<?php

declare(strict_types=1);

arch('PHP の危険な構文とデバッグ関数を使わない')
    ->preset()
    ->php();

arch('セキュリティ上危険な関数を使わない')
    ->preset()
    ->security();

arch('Laravel のデバッグ関数を残さない')
    ->expect(['dd'])
    ->not->toBeUsed();

arch('App 配下は env() を直接呼ばない')
    ->expect(['env'])
    ->not->toBeUsedIn('App');

arch('App 配下は ShouldQueue を実装しない')
    ->expect('App')
    ->not->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('App 配下は strict types を宣言する')
    ->expect('App')
    ->toUseStrictTypes();

arch('コントローラは Controller サフィックスを持つ')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('フォームリクエストは Request サフィックスを持ち FormRequest を継承する')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('Actions・Services・Repositories・Models・Params・ViewModels はコントローラに依存しない')
    ->expect('App\Http\Controllers')
    ->not->toBeUsedIn(['App\Actions', 'App\Services', 'App\Repositories', 'App\Models', 'App\Params', 'App\ViewModels']);

arch('Params は final readonly class で Params サフィックスを持つ')
    ->expect('App\Params')
    ->classes()
    ->toBeReadonly()
    ->toBeFinal()
    ->toHaveSuffix('Params');

arch('Params は HTTP リクエストやセッションに依存しない')
    ->expect('App\Params')
    ->not->toUse([
        'Illuminate\Contracts\Session',
        'Illuminate\Foundation\Http\FormRequest',
        'Illuminate\Http\Request',
        'Illuminate\Session',
        'Session',
        'session',
    ]);

arch('モデルはサービス・リポジトリ・HTTP 層に依存しない')
    ->expect('App\Models')
    ->not->toUse(['App\Services', 'App\Repositories', 'App\Http']);

arch('フォームリクエストはコントローラからのみ使用される')
    ->expect('App\Http\Requests')
    ->toOnlyBeUsedIn('App\Http\Controllers');
