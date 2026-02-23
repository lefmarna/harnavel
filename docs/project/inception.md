# Inception Deck

Project constraints, architectural trade-offs, and risk assessment. For what the product does, see [concept.md](concept.md).

## Scope Boundaries

| In Scope              | Out of Scope        | Unresolved      |
| --------------------- | ------------------- | --------------- |
| Auth (Login/Register) | CMS / Blog features | 3rd Party OAuth |
| User Dashboard        | Microservices       |                 |
| Profile Management    | Static Site Gen     |                 |

## System Boundaries

- **Frontend**: React 19 + Inertia v2 (Monorepo-ish structure)
- **Backend**: Laravel 12 (API + Auth)
- **Database**: MariaDB 10.5
- **Deploy**: XServer (Shared Hosting)

## Technical Constraints

1.  **XServer** - No queue workers (Sync jobs only), `.htaccess` routing.
2.  **Stack** - PHP 8.3+, TypeScript Strict Mode.
3.  **Monolith** - Single repo, simple deployment.

## Trade-offs

| Decision     | Chose              | Over      | Rationale                             |
| ------------ | ------------------ | --------- | ------------------------------------- |
| Architecture | Monolith (Inertia) | API + SPA | Simplicity, no API design overhead    |
| Hosting      | XServer            | Cloud/VPS | Cost efficiency                       |
| Auth         | Session/Cookie     | JWT       | Native security, less complexity      |

## Risks

| Risk                  | Impact | Mitigation                    |
| --------------------- | ------ | ----------------------------- |
| Shared Hosting Limits | High   | Sync jobs, Optimize queries   |
| Asset compilation     | Medium | Local build / Github Actions  |
| SEO (SPA)             | Medium | Server-side metadata in Blade |
