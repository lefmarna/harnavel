# Laravel Docker Template

Laravel 12 + MariaDB + Docker development environment with React/Inertia frontend.

## Project Document Guide

Comprehensive documentation is available in the `docs/` directory. Here is when to reference each document:

### 📋 Project Concept & Requirements
- **`docs/00_project/01_project_concept.md`** - Business requirements and functional specs.
- **`docs/00_project/02_inception_deck.md`** - Project vision and goals.
- **Use when**: Understanding business logic, features, or project goals.

### 🏗️ Technical Design Documents

#### System Architecture
- **`docs/01_development_docs/91_backend_implementation_guide.md`** - **Detailed Backend Implementation Guide (Must Read)**.
- **`docs/01_development_docs/01_architecture_design.md`** - DDD & Clean Architecture details.
- **`docs/01_development_docs/99_laravel_boost_guidelines.md`** - Auto-generated Laravel/PHP coding rules.
- **Use when**: Understanding layers, adding features, refactoring.

#### Database Design
- **`docs/01_development_docs/02_database_design.md`** - ER diagrams and table definitions.
- **Use when**: Writing migrations, adding tables/columns.

#### API Design
- **`docs/01_development_docs/03_api_design.md`** - RESTful & Internal API endpoints.
- **Use when**: Implementing endpoints, understanding request/response formats.

#### Frontend Design
- **`docs/01_development_docs/90_frontend_implementation_guide.md`** - **Detailed Frontend Implementation Guide (Must Read)** (Wayfinder, Tailwind v4).
- **`docs/01_development_docs/04_screen_transition_design.md`** - Screen flows and UI structure.
- **`docs/01_development_docs/10_frontend_design.md`** - Component patterns and frontend architecture.
- **Use when**: Creating pages, implementing UI components, navigation.

#### SEO Requirements
- **`docs/01_development_docs/05_seo_requirements.md`** - SEO strategy (Meta tags, Sitemap).
- **Use when**: Implementing public pages, meta tags.

#### Error Handling
- **`docs/01_development_docs/06_error_handling_design.md`** - Error handling patterns.
- **Use when**: Implementing error responses, custom exceptions.

#### Type Definitions
- **`docs/01_development_docs/07_type_definitions.md`** - PHP & TypeScript type system.
- **Use when**: Creating new types, understanding domain models.

#### Development Setup
- **`docs/01_development_docs/08_development_setup.md`** - Environment setup & workflow.
- **`docs/01_development_docs/97_docker_architecture.md`** - Docker services & environment variables.
- **`docs/01_development_docs/98_commands_reference.md`** - Full CLI command reference (mise, pnpm, docker).
- **Use when**: Setting up local environment, understanding commands.

#### Test Strategy
- **`docs/01_development_docs/09_test_strategy.md`** - Testing approach (Pest, Playwright).
- **Use when**: Writing tests, understanding test structure.

#### CI/CD
- **`docs/01_development_docs/11_cicd_design.md`** - GitHub Actions & Deployment.
- **Use when**: Modifying workflows, understanding deployment.

#### E2E Testing
- **`docs/01_development_docs/12_e2e_test_design.md`** - E2E scenarios & implementation.
- **Use when**: Implementing E2E tests, browser automation.

#### Security
- **`docs/01_development_docs/13_security_design.md`** - Auth, Policies, CSRF, XSS.
- **Use when**: Implementing security, auth, file uploads.

#### Performance
- **`docs/01_development_docs/14_performance_optimization.md`** - Caching, Query optimization.
- **`docs/01_development_docs/15_performance_monitoring.md`** - Monitoring & Observability.
- **Use when**: Optimizing speed, setting up monitoring.

### 🎨 Design System
- **`docs/02_design_system/00_basic_design.md`** - Overview & Quick Start.
- **`docs/02_design_system/01_design_principles.md`** - Principles, Colors, Typography.
- **`docs/02_design_system/02_component_design.md`** - Component usage (Shadcn UI).
- **`docs/02_design_system/03_animation_system.md`** - Animation patterns.
- **`docs/02_design_system/04_layout_system.md`** - Layout & Grid system.
- **Use when**: UI implementation, styling, animation.

### 📚 Library Docs
- **`docs/03_library_docs/01_radix_ui_patterns.md`** - Radix UI patterns.
- **`docs/03_library_docs/02_inertia_patterns.md`** - Inertia.js patterns.
- **`docs/03_library_docs/03_pest_testing_patterns.md`** - Pest testing patterns.
- **Use when**: Using specific library features.

## Quick Reference Map

| Task | Primary Doc |
|------|-------------|
| New Feature | Architecture → DB → API → Frontend |
| New API Endpoint | API Design → Error Handling → Types |
| DB Change | Database Design → Types |
| UI Implementation | Frontend → Design System → Component Design |
| Styling | Design Principles → Component Design |
| Testing | Test Strategy → Library Docs (Pest) |
| E2E Testing | E2E Design → Test Strategy |
| Security | Security Design → Test Strategy |
| Performance | Optimization → Monitoring |
| CI/CD | CI/CD Design → Setup |

## After Task Completion (Verification)

After completing an implementation task, **always** run verification commands based on the scope of changes (see `docs/01_development_docs/98_commands_reference.md` for details). If any check fails, fix the issue automatically and re-run verification until all checks pass.

- **Frontend changes** (`.ts`, `.tsx`, `.css`, etc.):

```bash
pnpm fix                          # Layout & Lint fix
pnpm lint:typecheck               # TypeScript check
pnpm test:e2e --project=chromium  # E2E tests
```

- **Backend changes** (`.php`):

```bash
mise run test                     # PHP tests
pnpm test:e2e --project=chromium  # E2E tests
```

- **Both frontend and backend changes**:
  
```bash
pnpm fix && pnpm lint:typecheck && mise run test && pnpm test:e2e --project=chromium
```

## Project Context (Optional Reference)

For deeper project background when needed:

- `.kiro/steering/product.md` - Product vision and scope
- `.kiro/steering/tech.md` - Technical stack details
- `.kiro/steering/structure.md` - Repository structure details

## Specification Phase Only

Spec-driven development instructions are loaded only during specification work. Use `.kiro/requirements/` for cc-sdd (requirements definition) and `.kiro/specs/` for design/implementation specs. See `docs/00_project/03_spec_driven_development_guide.md`.

For day-to-day development, follow the always-on guides above; apply cc-sdd only during the specification phase.

# Writing Rules

- Default to English in docs.
- **README.md**: For humans (project overview, setup instructions).
- **docs/**: For AI agents (guidelines, command references).
- Apply progressive disclosure: keep entry points brief, details in sub-docs.
- Planning and design documents must be saved under `docs/plans/` with date-based filenames (e.g., `2025-05-20-task-name.md`).

# Review gate (codex-review)

At key milestones—after updating specs/plans, after major implementation steps (≥5 files / public API / infra-config), and before commit/PR/release—run the codex-review SKILL and iterate review→fix→re-review until clean.
