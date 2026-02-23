# API Design

## Routing

- Current version: `/api/v1` (Public) or `/app` (Inertia)
- Route file: `routes/api.php` or `routes/web.php`
- Controllers: `app/Http/Controllers/`

## OpenAPI Workflow

This is the source-of-truth for Public API types.

**Modify API schema:**

1. Add OpenAPI attributes to controller methods
2. Run `mise run openapi` (if available) to generate docs

**Internal API (Inertia):**

Props are typed via TypeScript interfaces and validated via Wayfinder.

## Auth

Sanctum with stateful cookies (Inertia) + token-based (API). CSRF protection enabled.

## Validation & Responses

- Input: Form Request classes (`app/Http/Requests/`), never inline
- Output: API Resources (`app/Http/Resources/`) or `Inertia::render()`
- Errors: See [error-handling.md](error-handling.md) for response formats and status code mapping

## Verification Checklist

- [ ] **OpenAPI**: Attributes present on all controller methods
- [ ] **Input**: Form Request class for validation
- [ ] **Output**: API Resource or Inertia Props for formatting
- [ ] **Auth**: Endpoints consistently protected (see [security.md](security.md))
