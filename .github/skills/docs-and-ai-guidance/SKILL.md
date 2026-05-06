---
name: docs-and-ai-guidance
description: "Use when updating README, docs, AGENTS.md, Copilot instructions, skills, prompts, or contributor-facing workflow text."
---

# Docs And AI Guidance Skill

## Required sync rule

Before every commit, if code/config/tooling/docs behavior changed, update the relevant docs, `AGENTS.md`, and AI skills in the same change.

## Documentation sources

- `README.md` for concise package usage and development commands.
- `docs/` for architecture, usage, MongoDB schema, development, risks, and changelog.
- `AGENTS.md` for repository-wide AI contributor policy.
- `.github/skills/*/SKILL.md` for task-specific AI workflows.
- `.github/instructions/repo-quality.instructions.md` for short GitHub instruction context.

## Rules

- Do not add fake badges or mention unconfigured services.
- Keep `activity-log:doctor`, `activity-log:indexes`, `activity-log:prune`,
  `activity-log:export`, payload limits, sanitization, and the full-flow testing
  policy synchronized with the code and tests.
- Do not document unsupported repository customization or fake testing helpers.
- Keep docs concise and aligned with executable commands.
- Document limitations instead of smoothing them over.
- Queue docs must say `save()` is sync and async logging uses `queue(...)->dispatch()`.
- Contributor docs must say to run the full gate, commit after pass, and leave a clean tree.

## Definition of done

- Runtime claims match source and tests.
- Command names match `composer.json`.
- AI guidance contains the latest package rules and stop conditions.
