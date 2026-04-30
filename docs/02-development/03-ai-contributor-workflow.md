# AI Contributor Workflow

Before non-trivial changes:

1. Inspect `git status`, `composer.json`, docs, config, tests, and source files.
2. Read relevant `.github/skills/*/SKILL.md` files.
3. Compare tooling and docs decisions with `jooservices/dto`.
4. Compare repository and persistence decisions with `jooservices/laravel-repository`.
5. Stop on unclear APIs, conflicting package behavior, or unsupported feature requests.

Any code, config, tooling, docs, workflow, architecture, or user-facing behavior
change must update matching docs, `AGENTS.md`, and `.github/skills` before
commit.

Do not add `ActivityLog::fake()`, fake assertion helpers, fake repositories, or
other internal fake test layers unless the testing policy is explicitly changed.
Repository construction must stay aligned with
`jooservices/laravel-repository`, and `ActivityLogRepository` remains an
internal implementation detail until a separate product decision approves a
public abstraction.

Never bypass CaptainHook with `--no-verify`. Commit only after the full local
gate passes, and leave the working tree clean.
