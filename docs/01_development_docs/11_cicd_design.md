# CI/CD Design

This document defines the Continuous Integration and Continuous Deployment pipeline.
It ensures code quality and automates deployment.

## 1. Pipeline Diagram

```mermaid
graph TD
    Start((Push)) --> CI[CI Pipeline]
    CI --> Lint[Lint (PHP/JS) ✅]
    CI --> Test[Tests (Pest/Vitest/Playwright) ✅]
    CI --> Build[Build (Vite) ✅]
    
    Test --> Deploy[Deployment Pipeline (Main Branch)]
    Deploy --> Envoyer[Envoyer/Ploi/Forge]
    Envoyer --> ZeroDowntime[Atomic Deployment 🚀]
    ZeroDowntime --> Migration[DB Migration 🗄️]
```

## 2. GitHub Actions Workflows

### A. `.github/workflows/lint.yml` (Pull Request)

- **Triggers**: `pull_request`, `push` to `main`.
- **Jobs**:
  - `php-lint`: PHP CS Fixer, PHPStan.
  - `js-lint`: ESLint, Prettier.
  - `type-check`: TSC (`npm run type-check`).

### B. `.github/workflows/test.yml` (Pull Request)

- **Triggers**: `pull_request`, `push` to `main`.
- **Jobs**:
  - `phpunit`: Pest (Unit/Feature tests).
  - `playwright`: E2E tests (Chrome/Firefox).

### C. `.github/workflows/deploy.yml` (Push to Main)

- **Triggers**: `push` to `main` (if tests pass).
- **Jobs**:
  - `deploy`: Trigger webhook (Envoyer/Forge).
  - `notify`: Slack/Discord notification.

## 3. Deployment Strategy

- **Zero Downtime**: Use atomic deployments (symlink swap).
- **Database Migrations**: Run safely *after* code deployment (or with caution).
- **Assets**: Build frontend assets (Vite) on CI or build server.
- **Cache**: Clear/cache config, routes, and views post-deployment (`php artisan optimize`).

## Verification Checklist

- [ ] **Linting**: Do `lint.yml` checks pass locally (`npm run lint`, `vendor/bin/php-cs-fixer fix`)?
- [ ] **Tests**: Do `test.yml` checks pass locally (`npm test`, `php artisan test`)?
- [ ] **Secrets**: Are `APP_KEY`, `DB_PASSWORD`, `DEPLOY_WEBHOOK` set in GitHub Secrets?
- [ ] **Permissions**: Does the deployment user have SSH access?
- [ ] **Rollback**: Is rollback possible via the deployment tool (Envoyer)?