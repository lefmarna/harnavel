# XServer production observability

Each web request gets a server-generated ID for log and response correlation; incoming IDs are
ignored, and sensitive request data is not recorded. Full-page failures use dependency-free Blade,
while Inertia transitions may use the Inertia error page. Because XServer has no workers, unhandled
500-series failures may notify Discord only after the response and within the request process;
notifications are bounded, omit sensitive data, and never make their own failure fatal.
