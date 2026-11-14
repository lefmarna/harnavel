# Harnavel

[日本語](README.md) | **English**

Harnavel is a Laravel 13 + Inertia React template for developing with AI agents in Orca worktrees.
It is the author's personal foundation and changes whenever needed, with no compatibility or stability guarantees.

The **[user guide (Wiki)](https://github.com/lefmarna/harnavel/wiki/Harnavel-Guide)** covers everything from running it locally to creating a derived app and deploying to XServer.

> UI copy and code comments are in Japanese, and the deployment target is XServer, a Japanese shared-hosting service.

## Features

- **Isolated environment per task with Orca**: an application shares one MariaDB and one Mailpit, while each worktree gets its own databases and containers (Nginx / PHP) ([docs/worktree-development.md](docs/worktree-development.md)).
- **Built for XServer shared hosting**: runs without long-running processes or SSR, and replaces releases in place on the server ([docs/infrastructure.md](docs/infrastructure.md), [TADR-0006](docs/adr/template/0006-in-place-deployment.md)).
- **Tests as the specification**: behavior changes start with a failing test and are verified by mutation testing ([docs/testing-as-spec.md](docs/testing-as-spec.md)).
- **A harness for AI agents**: ships `AGENTS.md`, skills, and ADRs, and shares generic improvements with derived apps in both directions ([TADR-0003](docs/adr/template/0003-template-ownership.md)).

## Stack

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 13, PHP 8.5, Fortify     |
| Frontend       | React 19, Inertia v3, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v5, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

## Quick start

You need Docker Compose and `mise`. Worktrees are created with Orca; plain `git worktree` works too.

```bash
git clone https://github.com/lefmarna/harnavel.git
cd harnavel
mise run setup
mise run dev:info
```

Open the printed URL and sign in as the seeded user (`test@example.com` / `Test1234`). To create a derived app, ask an agent in the cloned primary checkout to run [`project-docs-init`](.agents/skills/project-docs-init/SKILL.md); [Getting Started](https://github.com/lefmarna/harnavel/wiki/Getting-Started) has the steps.

## User guide

| Page                                                                                               | Contents                                             |
| -------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| [Getting Started](https://github.com/lefmarna/harnavel/wiki/Getting-Started)                       | Run it locally, create a derived app                 |
| [Development Environment](https://github.com/lefmarna/harnavel/wiki/Development-Environment)       | Worktrees, ports, everyday commands                  |
| [Features and Customization](https://github.com/lefmarna/harnavel/wiki/Features-and-Customization) | Bundled features and what a derived app replaces     |
| [Working with AI Agents](https://github.com/lefmarna/harnavel/wiki/Working-with-AI-Agents)         | Skills, MCP, the verification hook, spec-driven work |
| [Testing and Quality](https://github.com/lefmarna/harnavel/wiki/Testing-and-Quality)               | Kinds of tests, how to run them, CI                  |
| [Deployment and Operations](https://github.com/lefmarna/harnavel/wiki/Deployment-and-Operations)   | First deployment to XServer and day-to-day operation |
| [Syncing with the Template](https://github.com/lefmarna/harnavel/wiki/Syncing-with-the-Template)   | Pulling template improvements into a derived app     |
| [Troubleshooting](https://github.com/lefmarna/harnavel/wiki/Troubleshooting)                       | Common errors and fixes                              |

## In-repository docs

These record design rationale and operational facts. AI agents read them, and derived apps inherit them.

- [AGENTS.md](AGENTS.md): entry point for AI agents
- [GLOSSARY.md](GLOSSARY.md): domain glossary
- [docs/testing-as-spec.md](docs/testing-as-spec.md): test responsibilities and mutation policy
- [docs/logging-guidelines.md](docs/logging-guidelines.md): logging and notification rules
- [docs/infrastructure.md](docs/infrastructure.md): XServer facts and server settings
- [docs/worktree-development.md](docs/worktree-development.md): shared development environment and worktrees
- [docs/branches-and-ci.md](docs/branches-and-ci.md): branches, CI, dependency and vulnerability updates
- [docs/circleci.md](docs/circleci.md): CircleCI setup
- [docs/adr/README.md](docs/adr/README.md): decision records (ADR / Template ADR)

## License

Original code is under the [MIT License](LICENSE); third-party code and documents follow [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
