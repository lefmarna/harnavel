# Laravel Docker Template

Laravel + MariaDB + Docker の開発環境雛形です。このリポジトリをクローンして Laravel アプリケーションをルートに展開して使用します。

## Project Snapshot

- **Docker**: Nginx + PHP + MariaDB + Mailpit
- **Laravel**: プロジェクトルートに展開（将来的に Inertia + React + Tailwind 導入予定）
- **Purpose**: 新規 Laravel プロジェクトのスターターテンプレート

## Docs Index (Read in this order)

1. **Environment setup**: `docs/docker.md`
2. **Role guides**: `docs/roles/backend.md`, `docs/roles/frontend.md` (導入後)
3. **Agent rules & tooling**: `docs/agent/overview.md`, `docs/agent/boost.md`
4. **Spec-driven development** (only when needed): `docs/specs.md`

## Always-on Project Guides

Use the appropriate guide for your implementation context:

- **Backend work** (Laravel/PHP): `docs/roles/backend.md`
- **Frontend work** (Inertia/React/TypeScript): `docs/roles/frontend.md` (導入後)

## Specification Phase Only

Spec-driven development (Kiro-style) instructions are loaded only during specification work. When needed, work under `.kiro/specs/` and see `docs/specs.md`.

For day-to-day development, follow the always-on guides above; apply spec-driven development only during the specification phase.

## Writing Rules

- Default to English in docs.
