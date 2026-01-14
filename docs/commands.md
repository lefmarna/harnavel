# Commands Reference

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
mise run down           # Remove containers
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
pnpm lint               # Run ESLint (check only)
pnpm lint:fix           # Run ESLint with auto-fix
pnpm lint:typecheck     # TypeScript type check
pnpm format             # Format with Prettier
pnpm fix                # Run lint:fix + format
```

## Backend Development

```bash
mise run migrate        # Run database migrations
mise run seed           # Run database seeders
mise run fresh          # Fresh migration with seed
mise run rollback       # Rollback last migration
mise run status         # Migration status
mise run test           # Run Pest tests
mise run tinker         # Laravel tinker
mise run route          # List routes
mise run clear          # Clear all caches
mise run composer:install  # Composer install
mise run composer:update   # Composer update
```

## Code Quality

```bash
mise run fix            # Fix code (ESLint + Prettier)
```

## Deployment

```bash
mise run deploy:build   # Build frontend assets
mise run deploy:package # Create deployment package
mise run deploy:prepare # Full deployment preparation
```

## Verification

```bash
# Frontend
pnpm lint && pnpm lint:typecheck

# Backend
mise run test

# Full
pnpm lint && pnpm lint:typecheck && mise run test
```
