---
name: add-page
description: Laravel + Inertia React の新規ページを追加するときに使う。route、controller、PageViewModel、Wayfinder、React page、Pest Feature spec、Playwright smoke E2E を一貫して作成し、props 境界を検証する。
disable-model-invocation: true
argument-hint: "<page-name>"
---

# Add Page

Laravel + Inertia React の新規ページを、template の層規約と検証規約に沿って追加する。

## 事前確認

- `AGENTS.md`、`docs/testing-as-spec.md`、TADR-0005 を読む。
- 既存の近いページ、route、controller、ViewModel、Feature spec、E2E spec を読む。
- 挙動を持つページは、先に `describe` / `it(...)->todo()` の spec ツリーを作り、承認後に実装する。
- PHP / artisan / Pest は Docker app container 内で実行する。Node / pnpm / mise はホストで実行する。

## 実装手順

1. route を追加する。
   - `routes/web.php` か既存の route ファイルに置く。
   - URL 文字列を React 側へ直書きする前提にしない。
2. controller を追加または既存 controller に action を追加する。
   - 単純表示なら controller なしの route closure も許容する。
   - 書き込みや複数モデル連携がある場合は TADR-0005 に従い Service / Repository / Params の要否を決める。
3. PageViewModel を用意する。
   - 固有 props を持つページは `app/ViewModels/**/...PageViewModel.php` を作り、`PageViewModel` を継承する。
   - title / description だけの静的ページは `StaticPageViewModel` を使う。
   - fragment ViewModel が props を組み立て済みのページは `DynamicPageViewModel` を使う（props の shape は fragment 側の PHPDoc が担う）。`Inertia::render` に生の配列を直接渡さない。
   - `toInertiaProperties(RenderContext $context)` の array-shape PHPDoc を React props 型と鏡合わせにする。
4. `Inertia::render('path/to/page', $viewModel)` で ViewModel オブジェクトを直接渡す。
5. route を変更したら `mise run wayfinder` を実行する。
6. React page を `resources/js/pages/**` に追加する。
   - props 型は ViewModel の array-shape PHPDoc と一致させる。
   - Links / forms / navigation は `@/actions` または `@/routes` の Wayfinder 関数を使う。
   - URL のハードコードは避ける。
7. Pest Feature spec を追加する。
   - タイトルは日本語で `describe` / `it` 階層にする。
   - `$response->assertInertia(...)` で component と page-specific props を検証する。
   - props shape は `assertInertiaShape($page, 'component/name', ['prop', 'nested.key'])` を使う。
   - 値が仕様である props は `where()` などで値も検証する。
8. Playwright smoke E2E を `e2e/tests/` に追加または既存 smoke に追記する。
   - 新ページは JavaScript error なしで表示できることを最低限確認する。
   - 認証が必要なページは、local / testing 限定の `/_e2e/auth/login-verified` または
     `/_e2e/auth/login-unverified` でログイン状態を作ってから確認する。
9. 検証する。
   - Backend: `mise run verify:backend`
   - Frontend: `pnpm format:check`、`pnpm lint:check`、`pnpm types:check`
   - E2E: 追加・変更した spec の最小範囲から始め、必要に応じて `pnpm test:e2e --project=chromium`
   - 最後に `git diff` で意図した変更だけか確認する。

## Feature Spec 例

```php
describe('レポート', function () {
    it('一覧画面を表示できる', function () {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index'));

        $response
            ->assertOk()
            ->assertInertia(
                fn ($page) => assertInertiaShape($page, 'reports/index', [
                    'reports',
                    'seo.title',
                    'seo.description',
                ])
                    ->where('seo.title', 'レポート')
            );
    });
});
```
