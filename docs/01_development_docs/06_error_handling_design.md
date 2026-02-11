# Error Handling Design

This document defines the system's error handling, validation, and logging policies.
Use this guide to ensure robust application behavior.

## 1. Exception Types & HTTP Status

### Common Exceptions (Use these first)

- **ValidationException** (422): Input validation errors.
- **AuthenticationException** (401): Not authenticated.
- **AuthorizationException** (403): Not authorized (Forbidden).
- **ModelNotFoundException** (404): Resource not found.
- **DomainException** (422/400): Business rule violation.

### Custom Exceptions

- **PaymentFailedException** (402/400).
- **ServiceUnavailableException** (503).
- **TooManyRequestsException** (429).

## 2. Handling Strategy

### API

- **Return**: JSON with `success: false` and `error` object.
- **Log**: Log details with context (user ID, request data).

### Inertia/Web

- **Return**: Redirect back with `withErrors()` or `with('error', '...')`.
- **Log**: Same as API.

## 3. Logging (Monolog)

- **Use**: `Log::error()`, `Log::warning()`, `Log::info()`.
- **Context**: ALWAYS include context array: `Log::error('Payment failed', ['order_id' => $order->id, 'reason' => $e->getMessage()])`.

## Verification Checklist

- [ ] **Status Code**: Is the HTTP status appropriate for the error?
- [ ] **Message**: Is the error message user-friendly (or developer-friendly in debug)?
- [ ] **Logging**: Is sufficient context logged for debugging?
- [ ] **Security**: Are sensitive details (passwords, keys) excluded from logs/responses?
- [ ] **Consistency**: Does the error response match API design?