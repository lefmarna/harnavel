# Runtime investigation from local agents

The server-scoped read-only API key is the safety boundary; wrappers or MCP servers would add
maintenance without strengthening it. An explicitly invoked `runtime-investigate` skill keeps
operational detail out of ordinary sessions. Remote writes stay out of scope.

Release stamps and production log-access routes are rejected to avoid production machinery for
occasional diagnostics; local agents hold no SSH keys and correlate existing delivery and Discord
evidence instead, accepting an undetermined live commit when evidence is incomplete.
