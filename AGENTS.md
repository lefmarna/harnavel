# Project Context

A Laravel 13 + Inertia.js v3 (React 19) + Tailwind CSS v4 starter template.
Docker is used locally; production runs on XServer shared hosting.

## Architecture

- Stack: PHP 8.5 / Laravel 13 / Inertia.js v3 / React 19 / Tailwind CSS v4 / Pest v5 / Larastan v3 / MariaDB / mise.
- Infer model property types from migrations and a `casts()` method that directly returns an array literal. Add `@property` only for a proven inference gap and state the reason (TADR-0007).
- XServer has no queue workers, SSR, or long-running processes. Run jobs synchronously and prefer solutions that work in both Docker and XServer.
- Run PHP and Artisan only inside the Docker `app` container. Run Node.js, pnpm, and mise on the host.

## Layout

- Backend layers live under `app/Params`, `app/Repositories`, `app/Services`, and `app/ViewModels`; introduce them according to TADR-0005.
- Shared PHP traits and helpers live under `app/Concerns` and `app/Support`.
- Playwright specs live under `e2e/`; engineering documentation lives under `docs/`.
- Read only recent lines from `storage/logs/laravel.log`, which can grow large.

## Verification

- Start with the narrowest relevant check, then widen before finishing.
- Backend: `mise run verify:backend`.
- Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:unit`, and `pnpm test:e2e`.
- Docs: `pnpm lint:docs`.
- Interactive browser checks: follow `docs/commands.md`.
- Changes to `delivery.yml` are high risk because it uses `rsync --delete` and `migrate --force`; review its excludes, maintenance lifecycle, release files, and post-deploy commands together.
- Finish by reviewing `git diff` and confirming only intended changes remain.

## Testing

- Tests are the behavioral specification. Write them before implementation, but continue through implementation and verification unless the user explicitly requests tests only. Clarify ambiguous behavior first.
- Bug fixes start with a reproducing test. Add useful boundary, failure, and authorization cases proactively.
- Test titles are Japanese in a `describe` / `it` hierarchy. New pages need a Feature spec and a smoke E2E test.
- For every new or changed deterministic application-logic class, evaluate mutation testing and add `mutates()` when its branches are observable. Do not use mutation-ignore annotations; removing a target weakens the gate and requires explicit review.
- Run DB-backed Pest through `mise run test -- ...` or `mise run mutate -- ...`; concurrent processes are rejected.
- Run only one Playwright process at a time. A concurrent run intentionally fails on `E2E_PORT`; wait for the active run, then retry.
- Do not expose E2E setup through HTTP routes; use the dedicated E2E database and normal user flows.
- Review implementation as well as tests for authentication, secrets, CI/delivery, migrations, concurrency, outbound side effects, and test-gate changes.

## Documentation

- `README.md` is Japanese; `docs/` is English. Never edit `docs/generated/` by hand.
- This file is the agent instruction source; `CLAUDE.md` and `GEMINI.md` are one-line pointers. Tool-specific hooks and MCP wiring remain in each tool's config, while shared verification logic stays in repository scripts.
- Keep only durable knowledge: every-session constraints here, behavior in tests, domain terms in `CONTEXT.md`, hard-to-reverse decisions in ADRs, and non-inferable reference material in `docs/`.
- Before changing an area, read its glossary and relevant ADRs. `CONTEXT.md` contains domain language only; ADR policy is in `docs/adr/README.md`.
- Keep repo-owned skill bodies under `.agents/skills/<name>/SKILL.md`; their `.claude/skills` counterparts are pointers.
- Never edit Laravel Boost-generated skills by hand.

## References

- Laravel Boost: `docs/generated/boost.md`
- Testing: `docs/testing-as-spec.md`
- Commands: `docs/commands.md`
- External infrastructure state: `docs/infrastructure.md`
- Spec workflow: `docs/generated/specs.md`
- Template convergence: TADR-0003 and `.claude/skills/merge-template/SKILL.md`
- New Inertia pages: `.agents/skills/add-page/SKILL.md`
