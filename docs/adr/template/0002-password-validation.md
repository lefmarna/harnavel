# Password validation trade-offs

Printable ASCII and a bounded byte length avoid bcrypt truncation ambiguity. Breach lookups fail
open so a third-party outage cannot block registration or password changes; applications choosing
a different security/availability balance record their own ADR.
