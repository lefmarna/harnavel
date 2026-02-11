# API Design

This document defines the RESTful and Internal API design standards.

## 1. RESTful Endpoints

- **Naming**: Resource-based (e.g., `POST /api/v1/users`).
- **Verbs**: Use HTTP methods correctly (GET, POST, PUT, DELETE).
- **Versioning**: Always include version prefix `/v1/`.
- **Query Params**: Use `?include=posts` for Eager Loading, `?sort=-created_at` for sorting.

## 2. Response Format (JSON)

Standardized structure for consistency.

```json
{
  "success": true,
  "data": { ... },
  "meta": { "pagination": { ... } } // If needed
}
```

## 3. Error Responses

- **Status Code**: Use semantic HTTP codes (400, 401, 403, 404, 422, 500).
- **Body**:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The email field is required.",
    "errors": { "email": ["Required"] } // For 422
  }
}
```

## 4. Internal API (Inertia Props)

- **Naming**: Props should match Model attributes or DTO fields.
- **Typing**: Use Typescript Interfaces for all props.
- **Structure**: Avoid deep nesting. Flatten data if possible.

## Verification Checklist

- [ ] **Method**: Is the correct HTTP verb used?
- [ ] **Status**: Are 201 (Created) and 204 (No Content) used correctly?
- [ ] **Format**: Does the response follow the standard JSON structure?
- [ ] **Validation**: Are request inputs validated using FormRequest?
- [ ] **Error Handling**: Are exceptions caught and returned as JSON errors?