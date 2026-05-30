# Commands Reference

Use this file when you need the repository's actual command names instead of guessing from the codebase.

## Setup (Initial)

```bash
mise run setup          # Full project setup
mise run setup:tools    # Install tools & pnpm dependencies
mise run setup:backend  # Setup backend (requires containers)
```

## Docker

```bash
mise run build          # Build containers (no cache)
mise run up             # Start containers
mise run stop           # Stop containers
mise run down           # Stop and remove containers
mise run restart        # Restart containers
mise run ps             # List containers
mise run logs           # View app logs
mise run app            # Access app container shell
mise run destroy        # Remove all containers and volumes
```

## Frontend Development

```bash
pnpm dev                # Start Vite dev server
pnpm build              # Build for production
pnpm format:check       # Check Prettier formatting
pnpm lint               # Run ESLint with auto-fix
pnpm lint:check         # Run ESLint (check only)
pnpm types:check        # TypeScript type check
pnpm format             # Format with Prettier
pnpm fix                # Run lint + format
```

## Backend Development

```bash
mise run migrate        # Run database migrations
mise run seed           # Run database seeders
mise run fresh          # Fresh migration with seed
mise run rollback       # Rollback last migration
mise run status         # Migration status
mise run test           # Run Pest tests
mise run mutate         # Mutation testing on pure-logic layers (Services/Actions/Repositories)
mise run pint           # Run Laravel Pint on changed PHP files
mise run analyse        # Run Larastan/PHPStan static analysis
mise run verify:backend # Run Pint + Larastan + Pest
mise run verify:backend:full # Run Pint + Larastan + Pest + Chromium E2E
mise run tinker         # Laravel tinker
mise run route          # List routes
mise run clear          # Clear all caches
mise run composer:install  # Composer install
mise run composer:update   # Composer update
```

## E2E Testing

```bash
pnpm test:e2e           # Run Playwright E2E tests
pnpm test:e2e:ui        # Run with UI mode (interactive debugging)
pnpm test:e2e:headed    # Run with browser visible
```

## Interactive Browser Verification

Use Playwright CLI for ad hoc browser checks while an agent is changing UI behavior. Start the
local app and Vite server first with the Docker and frontend commands above.

```bash
npx @playwright/cli open http://localhost:8080 # Start a browser on the local app
npx @playwright/cli snapshot --depth=4         # Inspect page structure and element refs
npx @playwright/cli screenshot                 # Capture visuals when layout or paint matters
npx @playwright/cli close                      # End the browser session
```

Prefer snapshots for navigation, form interaction, and semantic checks. Use screenshots after the
snapshot when the change needs visual confirmation. Playwright CLI artifacts stay in
`.playwright-cli/` and are ignored by Git.

Playwright CLI checks are transient. Add or update an E2E spec when a bug fix or user flow should
keep failing in future sessions, then run the narrowest relevant `pnpm test:e2e` command.

## Documentation

```bash
pnpm lint:docs          # Validate internal markdown links in docs/ + root markdown entrypoints
```

`pnpm lint:docs` includes `docs/`, `README.md`, `AGENTS.md`, and `CLAUDE.md` when present.

## Git Hooks

```bash
pnpm exec lefthook run pre-commit # Fix/format staged files, then run local checks (includes Docker-backed backend analysis)
```

## Deployment

```bash
mise run deploy:build   # Build frontend assets
mise run deploy:package # Create deployment package
mise run deploy:prepare # Full deployment preparation
```
