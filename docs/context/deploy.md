# Deployment Context

## Hosting Profile

This project targets XServer shared hosting.

## XServer Constraints

- No long-running Node.js or queue worker processes
- PHP app served via `.htaccess` rewrite strategy
- Cron batch files are plain PHP files that run under the project's PHP version. The cron entry itself is registered via XServer control panel (direct path execution, not via artisan).

## Implications

- Inertia SSR is disabled (`config/inertia.php`: `ssr.enabled=false`)
- Queue execution is fixed to synchronous mode (`config/queue.php`: `QUEUE_CONNECTION=sync`)
- Queue worker based asynchronous processing is out of scope under this hosting profile
- Build assets before deployment
- Do not introduce queue workers unless the hosting model is intentionally changed.

## Required Artifacts

- Production-ready `public/build` assets
- Correct `.htaccess` for front-controller routing and dotfile protection
- Environment variables configured on target host
- Deployment package generated with `.deployignore` exclusions

## XServer `.htaccess` Checklist

- Document root must be project root (not `public/`).
- `mod_rewrite` must be enabled (access denied if disabled).
- Allow `.well-known/acme-challenge/` for SSL certificate renewal.
- Force HTTPS and redirect www to non-www (single redirect).
- Deny access to dotfiles (`.env`, etc.).
- If you need XServer cache (`Ngx_Cache_AllCacheMode`), add it with appropriate exclusions for authenticated pages.

## Required Secrets and Access

- Store secrets in deployment environment settings
- Never commit production credentials
- `XSERVER_KNOWN_HOSTS` required (no `ssh-keyscan`)

## Required CI Secrets

- `XSERVER_PRIVATE_KEY`
- `XSERVER_KNOWN_HOSTS` (workflow fails if empty)
- `XSERVER_USER`
- `XSERVER_HOST`
- `DEPLOY_DIRECTORY`

## Transport

- SSH/Rsync uses port `10022`.
