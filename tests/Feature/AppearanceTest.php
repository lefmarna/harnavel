<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;

mutates(HandleAppearance::class);

describe('表示テーマ', function () {
    it('Cookie の表示テーマを初期描画のスクリプトとクラスへ反映する', function (string $appearance, bool $dark) {
        $content = $this->withUnencryptedCookie('appearance', $appearance)->get(route('home'))->getContent();

        expect($content)->toContain("const appearance = '{$appearance}';");
        $dark
            ? expect($content)->toContain('<html lang="ja" class="dark">')
            : expect($content)->not->toContain('class="dark"');
    })->with([
        'ライト' => ['light', false],
        'ダーク' => ['dark', true],
        'システム' => ['system', false],
    ]);

    it('想定外の値の Cookie はシステム設定として扱う', function () {
        $content = $this->withUnencryptedCookie('appearance', "dark\\';alert(1)//")->get(route('home'))->getContent();

        expect($content)->toContain("const appearance = 'system';")
            ->not->toContain('alert(1)');
    });

    it('Cookie がなければシステム設定として扱う', function () {
        expect($this->get(route('home'))->getContent())->toContain("const appearance = 'system';");
    });
});
