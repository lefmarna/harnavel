# Password policy defaults

## Status

Accepted.

## Context

`App\Providers\AppServiceProvider` configures `Password::defaults()`, the single source of
truth consumed by `PasswordValidationRules` (registration, password reset, password update).
Two constraints shape the decision:

- **Hashing is bcrypt** (`config/hashing.php` absent → framework default; `.env.example`
  ships `BCRYPT_ROUNDS=12`). bcrypt only hashes the first **72 bytes** of input; anything
  beyond is silently ignored.
- **Deployment target is XServer shared hosting** — synchronous request model, no queue
  workers (see project constraints in `AGENTS.md`).

The previous default applied strict rules **only in production** (`min(12)`, `mixedCase`,
`symbols`, `uncompromised`) and `null` elsewhere. That split meant the production-only branch
was never exercised by tests (it carried `@codeCoverageIgnoreStart`), conflicting with the
move toward mutation testing (see [`TADR-0001`](0001-development-workflow.md)).

## Decision

Apply **one identical policy in every environment**:

- `min(8)`, `max(64)`
- `letters()` + `numbers()` — composition is relaxed; `mixedCase()` / `symbols()` are **not**
  required (NIST SP 800-63B de-emphasises composition rules).
- `uncompromised()` — checked against HaveIBeenPwned in **all** environments. It is the
  load-bearing control that compensates for the relaxed composition (it rejects
  breach-listed but otherwise-compliant passwords such as `password1`).
- `rules(['regex:/^[\x21-\x7E]+\z/'])` — printable ASCII only; spaces, Unicode (incl.
  Japanese), and control characters are rejected.

Supporting choices:

- The ASCII restriction guarantees **1 character == 1 byte**, so `max(64)` is a real byte
  cap that sits safely under bcrypt's 72-byte limit. `max` must stay within **[60, 72]**: 60
  to keep the "a bcrypt hash submitted as a password" tests passing (those submit a 60-char
  hash), 72 to avoid bcrypt truncation.
- Tests stub HaveIBeenPwned (`Http::fake` for `api.pwnedpasswords.com/*`, scoped to all
  `Feature` tests in `tests/Pest.php`) so the suite stays offline and deterministic.
  `tests/Feature/Auth/PasswordPolicyTest.php` resets the HTTP client fake before specifying
  each rule, including a faked "pwned" response, giving full mutation coverage of
  `Password::defaults()`.
- The regex violation gets a user-facing message via `lang/ja/validation.php`
  (`custom.password.regex`); password field names are localised via `attributes`.

## Consequences and accepted risks

- **Deviates from NIST on character set (intentional).** NIST recommends accepting all
  printable Unicode and spaces. We reject them to keep `max(64)` a valid byte cap under
  bcrypt and to avoid Unicode-normalisation login failures (NFC/NFD) and copy-paste
  whitespace surprises. Allowing Unicode would require a byte-based length cap plus NFC
  normalisation — a separate, larger change.
- **Runtime dependency on HaveIBeenPwned.** `NotPwnedVerifier` is **fail-open**: on a network
  error/timeout it reports the exception and treats the password as not compromised, so an
  outage never locks users out — it only silently disables the breach check for that request.
  The default timeout is 30s; not hardened here (can be shortened later by rebinding
  `UncompromisedVerifier` in the container).
- Derived projects inherit these defaults and **may override** them in their own
  `AppServiceProvider`; record such overrides as an App ADR.
