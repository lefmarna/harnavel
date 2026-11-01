# Password validation trade-offs

Restricting passwords to 8–64 printable ASCII characters without spaces deliberately departs from
ASVS 5.0 V6.2.5. Every accepted password fits bcrypt's 72-byte input, so no byte limit surfaces to
users and nothing is silently truncated; Unicode normalization (recommended, not required, by NIST
SP 800-63B-4) and full-width input stay out of the authentication path; and the rule matches what
Japanese services commonly accept. The cost is no passphrases with spaces or non-ASCII text.

Breach lookups fail open so a third-party outage cannot block registration or password changes.
