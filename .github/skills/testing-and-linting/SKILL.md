---
name: testing-and-linting
description: "Use when adding tests, fixing quality failures, or changing composer scripts, Pint, PHPCS, PHPStan, PHPMD, PHPUnit, CaptainHook, or CI."
---

# Testing And Linting Skill

## Tool roles

- `Pint` is the primary formatter.
- `PHP-CS-Fixer` is limited to narrow PHPDoc cleanup.
- `PHPCS` checks structural style around Pint.
- `PHPStan` checks static correctness.
- `PHPMD` checks maintainability.
- `PHPUnit` covers unit and MongoDB integration behavior.

## Commands

```bash
composer validate
composer run lint:fix
composer run lint:all
composer run test
composer run check
composer audit
composer run ci
```

## Testing expectations

- Unit-test adapter, registry, DTO-building, sanitization, request context, and queue dispatch behavior.
- Integration-test MongoDB persistence through the facade/store/repository/model flow.
- Keep MongoDB integration tests reliable and skip clearly when MongoDB is unavailable.
- Do not leave composer scripts, hooks, or CI steps pointing at missing tools.

## Failure playbook

- Formatting fails: run `composer run lint:fix`, then re-run `composer run lint:all`.
- Static analysis fails: fix types or real code flow; avoid broad suppressions.
- MongoDB tests fail from missing service: report the environment evidence unless CI/service config is the intended change.
- CaptainHook changes must reference only installed tools and existing composer scripts.
