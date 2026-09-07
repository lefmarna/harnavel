# Runtime investigation from local agents

The server-scoped read-only key is the safety boundary; wrappers or MCP add maintenance without
strengthening it. An explicitly invoked `runtime-investigate` skill keeps operational detail out of
ordinary sessions.

Release stamps and new application-log access paths add production machinery for occasional
diagnostics. Local agents hold no SSH keys and use existing delivery and Discord evidence,
accepting an undetermined live commit when evidence is incomplete. Writes remain out of scope.
