# Project Context

## Template Purpose

This repository is a reusable Laravel + Inertia + Docker starter template.
Its primary objective is to provide a consistent baseline that can be cloned for multiple projects.

## Intended Use

Use this template when you need:
- Laravel 12 backend with Fortify authentication
- Inertia + React frontend with TypeScript strict mode
- Dockerized local development with MariaDB
- Test setup with Pest + Playwright

## System Boundaries

- Frontend: React 19 + Inertia v2
- Backend: Laravel 12 (API + Auth)
- Database: MariaDB
- Deploy: XServer (Shared Hosting)
- Infrastructure: Docker in local development

## Default Scope Assumptions

In scope by default (template provides):
- Auth (Login/Register)
- User Dashboard
- Profile Management

Out of scope by default (add per project):
<!-- Replace with project-specific out-of-scope items -->

## Design Trade-Offs

| Decision | Chose | Over | Rationale |
| --- | --- | --- | --- |
| Architecture | Monolith (Inertia) | API + SPA | Simplicity, no API design overhead |
| Hosting | XServer | Cloud/VPS | Cost efficiency |
| Auth | Session/Cookie | JWT | Native security, less complexity |

Overall: favor implementation speed and simplicity over maximal runtime flexibility.
This improves maintainability for small to medium product teams and template reuse scenarios.

## Risks and Mitigation

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Shared Hosting Limits | High | Sync jobs, Optimize queries |
| Asset compilation | Medium | Local build / Github Actions |
| SEO (SPA) | Medium | Server-side metadata in Blade |
<!-- Add project-specific risks below -->

## Unresolved

| Item | Status |
| --- | --- |
<!-- Add project-specific unresolved items -->

## Specification Workflow Entry

For feature specification work, use:
- `docs/generated/specs.md`
- `.kiro/requirements/`
- `.kiro/specs/`
