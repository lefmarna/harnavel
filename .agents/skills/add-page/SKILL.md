---
name: add-page
description: Use when adding a new Laravel + Inertia React page. Create the route, controller, PageViewModel, Wayfinder, React page, Pest Feature spec, and Playwright smoke E2E consistently, and verify the props boundary.
disable-model-invocation: true
argument-hint: "<page-name>"
---

# Add Page

Add a new Laravel + Inertia React page according to the template's layer and verification rules.

## Preflight

- Read `AGENTS.md`, `docs/testing-as-spec.md`, and TADR-0005.
- Read existing nearby pages, routes, controllers, ViewModels, Feature specs, and E2E specs.
- For pages with behavior, first create the `describe` / `it(...)->todo()` spec tree, then implement after approval.
- Run PHP / artisan / Pest inside the Docker app container. Run Node / pnpm / mise on the host.

## Implementation Steps

1. Add the route.
   - Put it in `routes/web.php` or an existing route file.
   - Do not assume the React side will hardcode the URL string.
2. Add a controller or add an action to an existing controller.
   - A route closure is acceptable for simple display-only pages.
   - If the page writes data or coordinates multiple models, use TADR-0005 to decide whether Service / Repository / Params are needed.
3. Prepare a PageViewModel.
   - For pages with custom props, create `app/ViewModels/**/...PageViewModel.php` and extend `PageViewModel`.
   - Use `StaticPageViewModel` for static pages that only need title / description.
   - Use `DynamicPageViewModel` when fragment ViewModels already build the props. In that case, the fragment-side PHPDoc owns the props shape. Do not pass raw arrays directly to `Inertia::render`.
   - Mirror the array-shape PHPDoc of `toInertiaProperties(RenderContext $context)` with the React props type.
4. Pass the ViewModel object directly to `Inertia::render('path/to/page', $viewModel)`.
5. If routes changed, run `mise run wayfinder`.
6. Add the React page under `resources/js/pages/**`.
   - Match the props type to the ViewModel array-shape PHPDoc.
   - Use Wayfinder functions from `@/actions` or `@/routes` for links, forms, and navigation.
   - Avoid hardcoded URLs.
7. Add a Pest Feature spec.
   - Titles must be Japanese and use a `describe` / `it` hierarchy.
   - Verify the component and page-specific props with `$response->assertInertia(...)`.
   - Use `assertInertiaShape($page, 'component/name', ['prop', 'nested.key'])` for the props shape.
   - For props whose values are part of the spec, verify the values with `where()` or similar assertions.
8. Add a Playwright smoke E2E test under `e2e/tests/`, or append to an existing smoke test.
   - Import `test` / `expect` from `../support/fixtures` and detect rendering without JavaScript errors through the fixture.
   - For pages that require authentication, create the logged-in state with local / testing-only routes such as `/_e2e/auth/login-verified` or `/_e2e/auth/login-unverified` before checking the page.
9. Verify.
   - Backend: `mise run verify:backend`
   - Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`
   - E2E: start with the smallest scope for the added or changed spec, then run `pnpm test:e2e --project=chromium` if needed.
   - Finally, run `git diff` and confirm only intended changes are present.

## Feature Spec Example

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
