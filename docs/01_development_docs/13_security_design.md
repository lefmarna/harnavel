# Security Design

This document defines the application security strategy and implementation.
It ensures protection against common web vulnerabilities.

## 1. Authentication (AuthN)

- **Mechanisms**: Laravel Fortify (Session-based).
- **Passwords**: Hashed with Argon2id (`config/hashing.php`).
- **Rate Limiting**: Applied to login/register endpoints (60/min).
- **2FA**: Optional (Totp, Backup codes).

## 2. Authorization (AuthZ)

- **Policies**: Use Laravel Policies (`UserPolicy`, `PostPolicy`) for resource access.
- **Middleware**: Use `auth:sanctum` or `auth` middleware for protected routes.
- **Roles/Permissions**: (e.g., Spatie Permission) if complex role hierarchy is needed.

## 3. Vulnerability Protection

### CSRF (Cross-Site Request Forgery)

- **Protection**: Laravel `VerifyCsrfToken` middleware enabled.
- **Frontend**: Include `@csrf` token in meta tags or use Inertia form helpers (automatic).

### XSS (Cross-Site Scripting)

- **Protection**: Blade `{{ $var }}` escapes HTML by default.
- **Frontend**: React escapes variables by default.
- **Allowed HTML**: Use `Purifier` (HTMLPurifier) for rich text input.

### SQL Injection

- **Protection**: Use Eloquent/PDO parameterized queries.
- **Raw SQL**: Avoid `DB::raw()` with user input.

### File Uploads

- **Protection**: Validate file types (MIME), size (max 10MB), and extension.
- **Storage**: Store outside `public/` directory (use `Storage::disk('private')`).
- **Scan**: Scan uploaded files for malware (ClamAV) if possible.

## 4. Sensitive Data

- **Environment**: Store secrets in `.env` (API keys, DB password).
- **Logs**: Do not log sensitive data (passwords, tokens).
- **Encryption**: Encrypt sensitive columns (`encrypted` cast).

## Verification Checklist

- [ ] **CSRF**: Test form submission without token (should fail 419).
- [ ] **XSS**: Test input `<script>alert(1)</script>` (should be escaped).
- [ ] **SQL Injection**: Test input `' OR 1=1 --` (should not break query).
- [ ] **File Upload**: Test uploading executable file (`.php`, `.exe`) (should be rejected).
- [ ] **Permissions**: Test accessing admin route as user (should be 403 Forbidden).
- [ ] **Secrets**: Check for hardcoded secrets (`grep -r "SECRET" src/`).