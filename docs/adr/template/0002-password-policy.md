# Password policy defaults

## Status

Accepted.

## Context

The template uses bcrypt, which ignores input beyond 72 bytes. It also needs one policy that is
predictable across environments and can be overridden explicitly by derived projects.

## Decision

Apply the same default password policy in every environment:

- length from 8 through 64 characters;
- at least one letter and one number, without mixed-case or symbol requirements;
- rejection of passwords found by HaveIBeenPwned; and
- printable ASCII only, excluding spaces, Unicode, and control characters.

The ASCII restriction makes one character equal one byte, so the 64-character maximum remains
below bcrypt's 72-byte limit and prevents silent truncation.

The breach check is fail-open on network failure so an external outage cannot prevent users from
registering or changing a password. Derived projects may override these defaults, but must record
the different security trade-off in an App ADR.

## Considered alternatives

- **Require mixed case and symbols.** Rejected because composition rules add user burden without
  providing the value of length and breach-list screening.
- **Accept printable Unicode and spaces.** Rejected because this would require an explicit
  byte-length limit and Unicode normalization to avoid bcrypt truncation and normalization-related
  login failures.
- **Fail closed when HaveIBeenPwned is unavailable.** Rejected because availability of an external
  service must not lock users out of account creation or password changes.

## Consequences and accepted risks

- The character-set restriction intentionally deviates from NIST guidance that recommends
  accepting printable Unicode and spaces.
- A HaveIBeenPwned outage temporarily weakens breach screening, although the remaining password
  rules continue to apply.
