# Project Context

A Laravel 13 + Inertia.js v3 (React 19) + Tailwind CSS v4 starter template.
Two environments: Docker locally (nginx + PHP-FPM + MariaDB), XServer shared hosting in production.

## Architecture

- Stack: PHP 8.5 / Laravel 13 / Inertia.js v3 (React 19) / Tailwind CSS v4 / Pest v4 / Larastan v3 / Pint v1 / MariaDB / mise.
- Model property types are inference-first: migrations + a `casts()` method that directly returns an array literal + Larastan's `parseModelCastsMethod`. Retain `@property` PHPDoc only for a proven inference gap, with the reason; rationale in TADR-0007 (`docs/adr/template/0007-inference-first-model-types.md`).
- XServer has no queue workers, SSR, or long-running processes. Run jobs synchronously (`dispatchSync()` or `QUEUE_CONNECTION=sync`), and prefer solutions that work in both Docker and XServer.
- Run `php` / `php artisan` only inside the Docker app container (`docker compose exec app ...`, or `docker compose exec -T app ...` non-interactively) — never on the host.
- Node.js, pnpm, and mise run on the host, not in the container.

## Layout

- `app/Params`, `app/Repositories`, `app/Services`, `app/ViewModels` — backend layer objects; `app/Concerns`, `app/Support` — shared traits and helpers. When to introduce each backend layer is decided by TADR-0005 (`docs/adr/template/0005-layer-decision-rules.md`).
- `e2e/` Playwright E2E tests; `.docker/` nginx/PHP config; `docs/` engineering docs.
- `storage/logs/laravel.log` can grow large — read recent entries only (`tail -n 200`).

## Verification

- Start with the narrowest check for the changed area, then widen before finishing.
- Backend: `mise run verify:backend`.
- Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:unit`, `pnpm test:e2e --reporter=dot`.
- Docs: `pnpm lint:docs`.
- Browser checks: follow the Playwright CLI loop in `docs/commands.md`.
- `delivery.yml` deploys to XServer with `rsync --delete` and `migrate --force`, so editing it can delete live server data — review the rsync excludes, `.deployignore`, develop-only `.htaccess`/robots, release-critical checks, post-deploy commands, and the release health check together.
- Before finishing, run `git diff` to confirm only intended changes. Done when the changed area's checks pass and the requested behavior is confirmed.

## Testing

- Tests are the behavioral spec: author `it('...')->todo()` cases and get them approved before implementing (`docs/testing-as-spec.md`).
- New features go test-first (red → green via the `tdd` skill); bug fixes start with a test that reproduces the issue.
- Test titles are Japanese, in a `describe`/`it` hierarchy. New pages need at least a smoke E2E test.

## Documentation

- `README.md` is Japanese; `docs/` is English. `docs/generated/` is auto-generated — never edit by hand.
- This file is the single agent entry point; `CLAUDE.md` / `GEMINI.md` are one-line pointers to it. Put content here, never in a pointer file; supporting a new agent tool means adding another pointer, nothing else.
- Any agent (Claude Code, Codex, Gemini, ...) works this repo: durable knowledge lives in versioned repo Markdown, never a single tool's private memory. Placement: constraint every session needs → here; behavior → test; domain term → `CONTEXT.md`; durable decision with rationale → ADR; how-to/reference → `docs/`; everything else stays in code or is not kept.
- Before planning, specifying, designing, or refactoring an area, read its glossary and ADRs. `CONTEXT.md` is the domain glossary — domain language only, no implementation details (use `CONTEXT-MAP.md` if present); ADRs live in `docs/adr/` (ADR-NNNN) and `docs/adr/template/` (TADR-NNNN) — policy in `docs/adr/README.md`.
- Use glossary terms and surface ADR conflicts instead of overriding them silently. After completing work, organize only ADRs and `CONTEXT.md` (TADR-0001); when and how to author, squash, or delete an ADR is defined in `docs/adr/README.md`.
- Repo-owned skills a non-Claude agent may need for ordinary implementation work are mirrored to both `.claude/skills/<name>/` and `.agents/skills/<name>/` (`add-page`, `adr-compact`, `update-laravel-packages`); `pnpm lint:docs` keeps the shared content equivalent. Claude-specific repository operations (`absorb-from-apps`, `merge-template`, `project-docs-init`) live in `.claude/skills/` only and are followed by path.

## Reference Docs

- Laravel Boost guidelines: `docs/generated/boost.md`
- Testing as specification: `docs/testing-as-spec.md`
- Commands: `docs/commands.md`
- Infrastructure: `docs/infrastructure.md`
- Spec workflow: `docs/generated/specs.md`
- Cross-app convergence: upstream (apps → template) `/absorb-from-apps`, downstream (template → app) `/merge-template`; decision model in TADR-0003 (`docs/adr/template/0003-shared-surface-convergence.md`). These procedures are plain Markdown under `.claude/skills/<name>/SKILL.md` — non-Claude agents follow them by path.
- New Inertia pages: follow `.claude/skills/add-page/SKILL.md` (mirrored at `.agents/skills/add-page/SKILL.md`) so route, PageViewModel, Wayfinder, React props, Feature spec, and smoke E2E stay aligned.
