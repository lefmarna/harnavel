<?php

declare(strict_types=1);

arch('プロダクションコードにデバッグ関数を残さない')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('コントローラは Controller サフィックスを持つ')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('フォームリクエストは Request サフィックスを持ち FormRequest を継承する')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('ドメイン層はコントローラに依存しない')
    ->expect('App\Http\Controllers')
    ->not->toBeUsedIn(['App\Services', 'App\Repositories', 'App\Models', 'App\Params', 'App\ViewModels']);
