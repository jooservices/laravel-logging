# Copilot Instructions For `jooservices/laravel-logging`

Read [AGENTS.md](../AGENTS.md) as the primary repository policy.

Before generating or editing code:

- inspect the real implementation and tests before deciding behavior
- keep MongoDB-only persistence through store, repository, and model
- keep adapter resolution dynamic through the registry
- preserve synchronous `save()` and queued `queue(...)->dispatch()` semantics
- update docs and AI guidance when behavior, commands, config, or workflow changes
- run the full composer quality gate before committing

Use the package-specific skills in `.github/skills/` for focused work.
