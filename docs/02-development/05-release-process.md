# Release Process

The repository's release automation is defined in `.github/workflows/release.yml`.

## Branch policy

- normal work starts from latest `develop` and targets `develop`
- release work starts from latest `develop` on `release/<version>`
- release pull requests target `master`
- after the release is merged into `master`, back-merge `master` into `develop` through a normal pull request

## Trigger

Push a semantic version tag matching:

```text
v*.*.*
```

Example:

```bash
git tag v1.0.0
git push origin v1.0.0
```

## Workflow stages

### 1. Validate release

The workflow provisions MongoDB, then runs:

- `composer validate --strict`
- `composer audit --no-dev`
- `composer check`

### 2. Create GitHub release

The workflow generates GitHub release notes for the pushed tag.

### 3. Publish to Packagist

When `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` are configured, the workflow refreshes the `jooservices/laravel-logging` package on Packagist.

## Practical maintainer checklist

Before tagging:

- confirm the intended release content has already merged to `master` through a reviewed release pull request
- confirm `master` and `develop` are synchronized according to the approved Git flow
- confirm `composer lint:all`, `composer test`, `composer check`, and `composer audit` pass locally
- update release-facing docs and any release/changelog notes when behavior or workflow changed

If the release source commit, semantic version level, or compatibility impact is unclear, stop and ask instead of guessing.
