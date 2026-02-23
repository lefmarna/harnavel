# Error Handling

## Exception to HTTP Status Mapping

| Exception                 | Status | When                          |
| ------------------------- | ------ | ----------------------------- |
| `ValidationException`     | 422    | Form Request validation fails |
| `AuthenticationException` | 401    | Unauthenticated               |
| `AuthorizationException`  | 403    | Policy denies access          |
| `ModelNotFoundException`  | 404    | Resource not found            |
| `DomainException`         | 400    | Business rule violation       |
| `Throwable`               | 500    | Server error                  |

## API Error Response Format

**Standard Error** (Matches `api.md`):

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human readable message",
    "errors": { "field": ["detail"] }
  }
}
```

## Logging

- **Use**: `Log::error($message, $context)`
- **Context**: ALWAYS include identifiers (`user_id`, `request_id`).
- **Security**: **Never log** passwords, tokens, or PII.

```php
Log::error('Payment failed', [
    'user_id' => $user->id,
    'order_id' => $order->id,
    'reason' => $e->getMessage()
]);
```

## Verification Checklist

- [ ] **Status code**: Matches the error type (see mapping table above)
- [ ] **Message**: User-friendly message (translated)
- [ ] **Format**: JSON structure matches API design
- [ ] **Logging**: Context includes identifiers (user ID, request data) for debugging
- [ ] **Security**: Sensitive data excluded from logs and error responses
