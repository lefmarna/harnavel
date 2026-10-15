# Password validation trade-offs

Passwords are 8 to 64 printable ASCII characters without spaces, rejected when breached, and hashed
with bcrypt. This deliberately departs from ASVS 5.0 V6.2.5, which permits any characters.

The restriction is a design choice for compatibility, simplicity, and usability rather than a hard
technical limit. Every accepted password stays within bcrypt's 72-byte input, so no byte limit
surfaces to users and nothing is silently truncated. Unicode normalization, which NIST SP 800-63B-4
recommends but does not require, and input-method differences such as full-width characters stay out
of the authentication path. The rule also matches what Japanese services commonly accept. The cost
is a smaller character set and no passphrases with spaces or non-ASCII text.

Breach lookups fail open so a third-party outage cannot block registration or password changes;
applications choosing a different security/availability balance record their own ADR.
