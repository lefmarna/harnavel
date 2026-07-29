# Password policy defaults

Passwords are 8–64 printable ASCII characters, contain at least one letter and one number, and are
checked against HaveIBeenPwned. Mixed case and symbols are not required. ASCII and the 64-character
limit prevent bcrypt's 72-byte truncation from creating ambiguous credentials. The breach check
fails open during network failure so an external outage cannot block registration or password
changes; an application choosing different security or availability trade-offs records its own ADR.
