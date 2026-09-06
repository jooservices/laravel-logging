# GitHub Actions workflow flow

This document describes the workflows in `.github/workflows/`. Jobs run on
GitHub-hosted `ubuntu-latest` runners with PHP 8.5 (`shivammathur/setup-php`)
and a MongoDB 8 service where integration tests need a real database.

## Workflows

| Workflow | File | Trigger | Role |
| --- | --- | --- | --- |
| CI | `ci.yml` | PR → `master`, `develop` | Validate → lint → security → tests → Coverage upload |
| CI post-merge | `ci-post-merge.yml` | Push to `master`, `develop` | Sanity validate/tests/coverage |
| Commitlint | `commitlint.yml` | PR | Validate commit messages |
| Semantic PR | `semantic-pr.yml` | PR title events | Validate PR Title |
| CodeQL | `codeql.yml` | Push/PR/schedule | Analyze GitHub Actions |
| Workflow audit | `workflow-audit.yml` | `.github/**` changes | actionlint + zizmor |
| PR labeler | `pr-labeler.yml` | PR | Path labels |
| Scorecard | `scorecard.yml` | Schedule / push `master` | OpenSSF Scorecard |
| Link check | `link-check.yml` | Schedule / manual | Markdown links |
| Stale | `stale.yml` | Daily | Inactive issues/PRs |
| Release | `release.yml` | Tag `v*.*.*` | Changelog + GitHub Release + Packagist |

Secret scanning has no dedicated workflow: it runs inside the CI
`Security (Secrets)` matrix leg with the Gitleaks OSS CLI and `.gitleaks.toml`.

## Required checks (non-POC)

Lock these contexts on `master` and `develop` after a green PR verifies names:

- `Coverage upload`
- `Validate`
- `Lint (Pint)` · `Lint (PHPCS)` · `Lint (PHPStan)` · `Lint (PHPMD)` · `Lint (PHP-CS-Fixer)` · `Lint (Format sanity)`
- `Security (Dependencies)` · `Security (Secrets)` · `Security (SAST)`
- `Test (Laravel 12)` · `Test (Laravel 13)`
- `Validate commit messages`
- `Validate PR Title`
- `Analyze GitHub Actions`

## Coverage gate

`Coverage upload` enforces the repository coverage floor with
`scripts/check-coverage.php` (minimum 90% line coverage on the Laravel 12
clover report) before any Codecov or SonarQube upload.

## Branch model

Long-lived branches: `master` (production) and `develop` (integration). Feature
work PRs into `develop`. Releases and hotfixes follow workspace branch policy.
