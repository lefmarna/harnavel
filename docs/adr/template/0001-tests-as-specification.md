# Tests are the behavioral specification

Tests, not separate behavioral design documents, are the canonical specification: they are written
before implementation and let reviewers judge expected behavior from cases and assertions alone.
This keeps the specification executable and avoids drift between prose and code, accepting the
cost of designing tests as readable contracts. Implementation still needs direct review for
concerns tests cannot make complete, including authentication, secrets, delivery, migrations,
concurrency, outbound side effects, and gate changes.
