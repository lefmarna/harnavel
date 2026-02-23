# Security Design

## Authentication

Laravel Fortify with session cookies. Session-based via `web` guard.

- **CSRF**: Handled automatically via Inertia. Session expiry (419) returns user-friendly error.
- **Password**: Argon2id hashing (`config/hashing.php`).
- **2FA**: TOTP-based two-factor authentication via Fortify.

## Rate Limiting

Defined in `FortifyServiceProvider`:

| Limiter      | Scope                  | Limit  |
| ------------ | ---------------------- | ------ |
| `login`      | email + IP             | 5/min  |
| `two-factor` | session login ID       | 5/min  |

## Validation

- **Always Form Request classes** (`app/Http/Requests/`), never inline
- Custom rules in `app/Rules/`
- Validation errors: 422 with `{ message, errors: { field: [...] } }`

## Sensitive Data

- Secrets in `.env` only, accessed via `config()` (see also [architecture constraints](architecture.md#project-specific-constraints))
- **Never log**: passwords, tokens, or sensitive user data

## Verification Checklist

- [ ] **CSRF**: Form submission fails without token (419)
- [ ] **Rate limit**: Applied to all auth-related endpoints
- [ ] **Validation**: Form Request used (no inline validation in controller)
- [ ] **Secrets**: No secrets in committed files
- [ ] **Logging**: Passwords excluded from session flash and log context
