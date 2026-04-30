---
name: development-workflow
description: "Use when starting implementation, preparing commits, updating workflow rules, or deciding the local quality gate."
---

# Development Workflow Skill

## Before coding

1. Run `git status`.
2. Read `AGENTS.md` and any relevant `.github/skills/*/SKILL.md` files.
3. Inspect implementation, config, docs, and tests before changing behavior.
4. Check `jooservices/dto` for tooling/docs/AI standards when those areas change.
5. Check `jooservices/laravel-repository` before changing repository construction or persistence.

## During coding

- Keep changes minimal and package-specific.
- Do not assume missing behavior or silently invent APIs.
- Update tests with behavior changes.
- Update docs when code, config, tooling, commands, workflows, or user-facing behavior changes.
- Update `AGENTS.md` and `.github/skills` when contributor workflow or package rules change.

## Before commit

Run:

```bash
composer validate
composer run lint:fix
composer run lint:all
composer run test
composer run check
composer audit
composer run ci
```

If a configured command cannot run in the local environment, stop and report the exact reason.

## Commit rules

- Commit only after required checks pass.
- Use author `Viet Vu <jooservices@gmail.com>`.
- Use a short meaningful Conventional Commit message.
- Leave the working tree clean after successful work.
