---
name: release-management
description: "Use when preparing, validating, tagging, or publishing jooservices/laravel-logging releases."
---

# Release Management Skill

## Repository Truth

- Package: `jooservices/laravel-logging`
- Versioning follows semantic versioning: `MAJOR.MINOR.PATCH`, tagged as `vX.Y.Z`.
- Normal work starts from `develop` and opens PRs to `develop`.
- Release work starts from latest `develop` on `release/<version>` and opens a PR to `master`.
- Never commit directly to `master` or `develop`; all updates to those branches must go through pull requests.
- Stop and ask if release intent, branch state, changelog entries, or compatibility impact is unclear.

## Version Decision

- Patch: compatible bug fixes, documentation corrections, CI maintenance, dependency patch updates.
- Minor: backward-compatible logging features, adapter additions, optional MongoDB/query improvements.
- Major: breaking public API changes, persistence format changes without compatibility, dropped PHP/Laravel/MongoDB support.

Do not widen Composer constraints or drop supported PHP, Laravel, MongoDB, DTO, or repository package versions without explicit approval.

## Preflight

1. Inspect tags and releases:
   - `git tag --sort=-version:refname`
   - `gh release list --repo jooservices/laravel-logging`
2. Inspect `README.md`, `composer.json`, `composer.lock`, release workflow files if present, and docs under `docs/`.
3. Confirm `master` and `develop` are synchronized according to approved Git flow.
4. Validate locally:
   - `composer validate --strict`
   - `composer lint:all`
   - `composer test`

## Release Flow

1. Checkout latest `develop`.
2. Create `release/<version>`.
3. Update changelog/release metadata if the repo has it; add a changelog entry if one is introduced.
4. Open PR from `release/<version>` to `master`.
5. Merge only after checks pass, required reviews are approved, no requested changes remain, no unresolved review threads remain, and the branch is mergeable.
6. Tag from `master` with `vX.Y.Z`.
7. Create or verify the GitHub release.
8. Merge `master` back into `develop` through a pull request and normal review/check gates.
9. Clean up only safely merged branches.

## Failure Rules

- Do not force push protected branches.
- Do not bypass failing checks or review feedback.
- If GitHub release, Packagist publishing, or branch protection status cannot be verified, stop and report.
