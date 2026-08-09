# Password validation trade-offs

Passwords are 8–64 printable ASCII characters with at least one letter and one number; mixed case
and symbols are optional. ASCII and the 64-character limit avoid bcrypt's 72-byte truncation
ambiguity. HaveIBeenPwned checks fail open so a third-party outage cannot block registration or
password changes; an application choosing another security/availability trade-off records its own
ADR.
