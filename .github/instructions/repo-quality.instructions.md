# Repository Quality

- Use PHP 8.5, Laravel 12, `mongodb/laravel-mongodb`, `jooservices/dto`, and `jooservices/laravel-repository`.
- Read `AGENTS.md` and relevant `.github/skills/*/SKILL.md` files before non-trivial changes.
- Keep persistence flowing through `ActivityLogData` -> `MongoLogStore` -> `ActivityLogRepository` -> `ActivityLogRecord`.
- Keep `ActivityLogRepository` constructor-injected with `ActivityLogRecord` and aligned with `jooservices/laravel-repository`.
- Treat `ActivityLogRepository` as internal-only unless a later approved change explicitly opens it as an extension point.
- Keep adapter resolution dynamic through the registry.
- Do not parse string identities; use external reference methods for type/id pairs.
- `save()` is sync; queued logs use `queue(...)->dispatch()`. `sync()->dispatch()` records immediately.
- Operation commands are `activity-log:indexes`, `activity-log:doctor`,
  `activity-log:prune`, and `activity-log:export`; docs must match source.
- Sanitization runs before payload limiting for stored payload fields.
- Do not implement `ActivityLog::fake()` or fake/assertion helpers.
- Tests must use full-flow MongoDB persistence and must not mock internal package services.
- Pint wins over PHPCS/php-cs-fixer style disagreements.
- Update docs and AI guidance when code, config, tooling, workflow, architecture, or behavior changes.
- Run `composer validate --strict`, `composer run lint:fix`, `composer run lint:all`, `composer run test`, `composer run check`, `composer audit`, and `composer run ci` before commit.
- Install and verify CaptainHook locally; never bypass hooks with `--no-verify`.
- Commit only after checks pass, use author `Viet Vu <jooservices@gmail.com>`, and leave the working tree clean.
