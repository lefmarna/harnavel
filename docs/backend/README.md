# Backend Documentation

Laravel 12 + PHP 8.3+ + MariaDB 10.5 + Pest 4 + Fortify.

## Architecture & Design

- **[architecture.md](architecture.md)** - 3-layer pattern, directory structure, layer rules
- **[api.md](api.md)** - RESTful & Internal API endpoints
- **[database.md](database.md)** - Data strategy, migration gotchas
- **[repository-pattern.md](repository-pattern.md)** - Implementation templates for Service/Repository

## Security & Error Handling

- **[security.md](security.md)** - Auth, rate limiting, validation
- **[error-handling.md](error-handling.md)** - Error handling patterns and response formats

## Testing

- **[testing.md](testing.md)** - Test layers, TDD workflow, Pest/Playwright

## Implementation

- **[coding-guide.md](coding-guide.md)** - Day-to-day rules: PHP, testing, XServer deployment

**Commands**: `mise run test` / `mise run migrate` / `mise run fresh` ([full list](../infrastructure/commands.md))

**Reference**: [Database schema spec](schema-design-spec.md) | [Laravel Boost rules](boost.md)
