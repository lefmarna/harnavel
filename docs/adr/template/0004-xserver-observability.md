# XServer production observability

Failure display must survive frontend dependency failures; correlation must not trust client IDs.
XServer's lack of workers makes alerting request-local best effort: bound its cost, exclude sensitive
data, and never worsen the original failure.
