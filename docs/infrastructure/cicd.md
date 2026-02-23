# CI/CD Pipeline

## Flow

```
Push (develop/main) → GitHub Actions
                         │
                         ├── Lint (Pint + Larastan/PHPStan + ESLint + Prettier)
                         ├── Test (Pest + Playwright)
                         └── Deploy (if main)
```

## Configuration Files

| File                                       | Purpose                        |
| ------------------------------------------ | ------------------------------ |
| `.github/workflows/lint.yml`               | PHP/JS linting & formatting    |
| `.github/workflows/test_deploy.yml`        | Test + deploy pipeline         |

## Deployment Targets

| Component | Target           | Method                                       |
| --------- | ---------------- | -------------------------------------------- |
| Frontend  | XServer (shared) | Built via Vite on CI, rsync to server        |
| Backend   | XServer (shared) | `mise run deploy:prepare` → deployment package |

**Auth**: SSH key via GitHub Encrypted Secrets. `XSERVER_KNOWN_HOSTS` required (no `ssh-keyscan`).

## Branching & Commits

- Feature branches off `develop`, merge to `main` for production
- PR required: CI green before merge
