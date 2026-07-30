# Operational commands

Use `mise tasks` and `pnpm run` as the command indexes. `AGENTS.md` lists the required verification
commands.

## E2E

```bash
pnpm test:e2e       # Chromium
pnpm test:e2e:all   # Chromium, Firefox, and WebKit
pnpm test:e2e:ui    # Interactive browser selection
```

Each app must use its own `E2E_PORT` in `.env`. E2E uses the dedicated `app_e2e` database.

## Interactive browser verification

Start the app and Vite, then use Playwright CLI for transient checks. Replace `NGINX_PORT` with its
value from `.env`.

```bash
npx @playwright/cli open http://localhost:NGINX_PORT
npx @playwright/cli snapshot --depth=4
npx @playwright/cli screenshot
npx @playwright/cli close
```

Prefer snapshots for behavior and screenshots for visual output. Add an E2E spec when the behavior
should remain guarded.
