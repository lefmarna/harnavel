# Deployment Context

## Deployment Profile

This template defaults to an XServer shared-hosting profile.
The profile is intentionally documented separately from implementation guides so it can be swapped for other hosting targets.

## XServer Constraints

- No long-running Node.js or queue worker processes
- PHP app served via `.htaccess` rewrite strategy
- Cron batch files are plain PHP files that run under the project's PHP version. The cron entry itself is registered via XServer control panel (direct path execution, not via artisan).

Implications:
- Inertia SSR is disabled (`config/inertia.php`: `ssr.enabled=false`)
- Queue execution is fixed to synchronous mode (`config/queue.php`: `QUEUE_CONNECTION=sync`)
- Queue worker based asynchronous processing is out of scope for this template
- Build assets before deployment

Queue note:
- Do not introduce queue workers or async queue architecture in this template lineage.
- Keep background-like behavior in synchronous application flow.

## Build and Release Flow

Typical path:

1. `test_deploy` runs on `develop/main` push and PR, then executes build + tests + Chromium E2E.
2. On `main` only, build artifacts (`public/build`) are uploaded.
3. Deploy job (`needs: test`) downloads artifact, packages `.deploy`, and deploys via SSH/rsync.
4. Remote post-deploy commands run: `composer install --no-dev`, cache warmup, and `artisan migrate --force`.

Workflow note:
- Lint runs in a separate `linter` workflow.
- Current deploy gate is tied to `test_deploy` success, not directly to `linter`.

Branch policy baseline:
- Develop on feature branches from `develop`.
- Merge to `main` only after PR review and green CI checks.

Workflow ownership:

| Workflow file | Responsibility |
| --- | --- |
| `.github/workflows/lint.yml` | formatting + lint + static analysis |
| `.github/workflows/test_deploy.yml` | tests + build artifacts + production deploy job |

## Required Deployment Artifacts

- Production-ready `public/build` assets
- Correct `.htaccess` for front-controller routing and dotfile protection
- Environment variables configured on target host
- Deployment package generated with `.deployignore` exclusions

XServer `.htaccess` checklist:
- Document root must be project root (not `public/`).
- `mod_rewrite` must be enabled (access denied if disabled).
- Allow `.well-known/acme-challenge/` for SSL certificate renewal.
- Force HTTPS and redirect www to non-www (single redirect).
- Deny access to dotfiles (`.env`, etc.).
- If you need XServer cache (`Ngx_Cache_AllCacheMode`), add it with appropriate exclusions for authenticated pages.

## Environment and Security

- Store secrets in deployment environment settings
- Never commit production credentials
- `XSERVER_KNOWN_HOSTS` required (no `ssh-keyscan`)

Required CI secrets:
- `XSERVER_PRIVATE_KEY`
- `XSERVER_KNOWN_HOSTS` (workflow fails if empty)
- `XSERVER_USER`
- `XSERVER_HOST`
- `DEPLOY_DIRECTORY`

Transport details:
- SSH/Rsync uses port `10022`

## Adapting to Other Hosts

When migrating away from XServer:
- keep the no-queue-worker policy unless you intentionally fork template architecture
- re-evaluate SSR feasibility
- replace `.htaccess` specifics with target-native routing
- keep this file as the deployment source of truth

## Verification Checklist

- [ ] Build and packaging steps match the current deployment profile
- [ ] Host constraints are reflected in runtime architecture decisions
- [ ] Deployment secrets and host keys are managed outside git
- [ ] CI status interpretation is explicit (`test_deploy` gate vs separate `linter`)
