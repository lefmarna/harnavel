# Tests are the behavioral specification

Tests, not separate behavioral design documents, are the canonical specification: they are written
before implementation and let reviewers judge expected behavior from cases and assertions alone.
This keeps the specification executable and avoids drift between prose and code, accepting the
cost of designing tests as readable contracts. Implementation still needs direct review for
concerns tests cannot make complete, including authentication, secrets, delivery, migrations,
concurrency, outbound side effects, and gate changes.

Configuration, tooling, and CI wiring are not behavior and carry no checks of their own. Restating
configuration in code is worth its maintenance cost only when the failure it prevents is both
irreversible and silent: `rsync --delete` reaching production `.env`, `.user.ini`, or
`public/storage`, a PHP series the test run never exercised, or a release that deploys before its
gates, replaces live files outside maintenance, or never lifts it again. Because `/up` is exempt
from maintenance, every one of those still reports a green deploy. The deployment gate holds that
order and those guarantees, and drops the retry counts, timeouts, and payload literals it once
transcribed; nothing else in the CI graph is checked, because a miswired workflow fails in view.
