# Development

Quality commands:

```bash
composer validate
composer run lint:fix
composer run lint:all
composer run test
composer run check
composer audit
composer run ci
```

Pint is the master formatter. If another style tool disagrees with Pint, adjust that tool instead of changing Pint output.

MongoDB integration tests require a running MongoDB service:

```bash
MONGODB_URI=mongodb://localhost:27017 composer run test:integration
```

Without MongoDB, integration tests skip with an explicit message. Unit tests still cover manager, registry, adapters, DTO building, sanitization, and request context behavior.

CaptainHook installs through Composer. The pre-commit hook runs PHP linting,
Pint, PHPCS, and PHPStan; the pre-push hook runs the full `composer check`
gate.

AI contributors must also read `AGENTS.md` and the relevant `.github/skills/*/SKILL.md` files before non-trivial work. If code, config, tooling, workflow, architecture, or user-facing behavior changes, update the matching docs and AI guidance before committing. Commit only after the full local gate passes and leave the working tree clean.
