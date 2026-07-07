<?php

declare(strict_types=1);

use App\ViewModels\DynamicPageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

describe('DynamicPageViewModel', function () {
    it('fragment の props と SEO を合成して Inertia props を返せる', function () {
        Route::middleware('web')->get('/_spec/dynamic-page', fn () => Inertia::render(
            'dashboard',
            new DynamicPageViewModel(
                title: 'テストページ',
                description: 'テストページの説明。',
                props: ['items' => [['name' => 'alpha'], ['name' => 'beta']]],
            ),
        ));

        $response = $this->get('/_spec/dynamic-page');

        $response
            ->assertOk()
            ->assertInertia(
                fn ($page) => assertInertiaShape($page, 'dashboard', [
                    'items',
                    'seo.title',
                    'seo.description',
                ])
                    ->where('items.0.name', 'alpha')
                    ->where('items.1.name', 'beta')
                    ->where('seo.title', 'テストページ')
                    ->where('seo.description', 'テストページの説明。')
                    ->missing('seo.robots')
            );
    });

    it('noIndex の場合 seo.robots に noindex, nofollow が付く', function () {
        Route::middleware('web')->get('/_spec/dynamic-page-noindex', fn () => Inertia::render(
            'dashboard',
            new DynamicPageViewModel(
                title: '非公開ページ',
                description: '検索対象外のページ。',
                noIndex: true,
            ),
        ));

        $response = $this->get('/_spec/dynamic-page-noindex');

        $response
            ->assertOk()
            ->assertInertia(
                fn ($page) => assertInertiaShape($page, 'dashboard', [
                    'seo.title',
                    'seo.robots',
                ])
                    ->where('seo.robots', 'noindex, nofollow')
            );
    });
});
