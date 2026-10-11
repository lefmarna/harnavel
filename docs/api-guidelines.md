# API guidelines

The template ships no API routes, Sanctum, or token issuance. An app that adds them owns the
following, because the template cannot know which callers, abilities, or remote hosts it needs.

- **Rate limits**: give every API route a named limiter, with separate, tighter limits on
  login, password reset, mail or SMS sending, and expensive searches. Rejections already reach
  the log and Discord ([logging guidelines](logging-guidelines.md)).
- **Authorization**: check a policy or gate on every endpoint, including ones the app's own
  frontend alone calls, and accept only the HTTP methods the endpoint needs. Scope queries to the
  caller instead of trusting identifiers from the request.
- **Tokens**: grant each token the fewest Sanctum abilities, set an expiry, and keep a way to
  revoke one token or all of a user's tokens. Revoking sessions does not revoke tokens.
- **Input**: validate with a form request and pass only validated fields to the model.
- **Outbound requests**: when a request carries a URL that the server fetches, allow only `https`,
  resolve the host and refuse private, loopback, and link-local addresses, and do not follow
  redirects. Fixed vendor endpoints need none of this.
- **Errors**: return the framework's error responses, which hide exception details outside
  `local`.
