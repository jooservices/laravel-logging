# jooservices/laravel-logging

[![CI](https://github.com/jooservices/laravel-logging/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/laravel-logging/actions/workflows/ci.yml)
[![Coverage (develop)](https://codecov.io/gh/jooservices/laravel-logging/branch/develop/graph/badge.svg)](https://codecov.io/gh/jooservices/laravel-logging/branch/develop)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/laravel-logging/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/laravel-logging)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![GitHub Release](https://img.shields.io/github/v/release/jooservices/laravel-logging?display_name=tag)](https://github.com/jooservices/laravel-logging/releases)
[![Packagist Version](https://img.shields.io/packagist/v/jooservices/laravel-logging)](https://packagist.org/packages/jooservices/laravel-logging)
[![Total Downloads](https://img.shields.io/packagist/dt/jooservices/laravel-logging)](https://packagist.org/packages/jooservices/laravel-logging)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

`jooservices/laravel-logging` stores structured activity, audit, security,
domain, and system logs in MongoDB for Laravel applications.

> **v4.0.0** requires `jooservices/dto` ^3 and
> `jooservices/laravel-repository` ^4. See [UPGRADE.md](UPGRADE.md) and the
> [changelog](CHANGELOG.md).

## Features

- Structured records through activity, audit, security, domain, and system adapters.
- MongoDB persistence, indexes, and query helpers.
- Optional queue dispatch, model audit logging, retention, and JSONL/CSV export.

## Requirements

- PHP ^8.5
- Laravel 12 or 13
- `mongodb/laravel-mongodb` ^5.7 and a configured MongoDB connection
- `jooservices/dto` ^3 and `jooservices/laravel-repository` ^4

## Installation

```bash
composer require jooservices/laravel-logging
php artisan vendor:publish --tag=laravel-logging-config
php artisan activity-log:indexes
```

Set the MongoDB connection and package collection in your environment as
needed:

```env
ACTIVITY_LOG_CONNECTION=mongodb
ACTIVITY_LOG_COLLECTION=activity_logs
MONGODB_URI=mongodb://localhost:27017
```

## Quick start

```php
use JOOservices\LaravelLogging\Facades\ActivityLog;

ActivityLog::system()
    ->commandStarted('scheduler:run')
    ->save();
```

`save()` persists synchronously and returns an `ActivityLogRecord`. See
[Queueing](docs/02-user-guide/03-queueing.md) for dispatching work to a queue.

## Design notes

- This package stores structured application records; it is not a replacement
  for Laravel Log, Monolog, Sentry, OpenTelemetry, Loki, ELK, or a full
  observability stack.
- Model audit logging is opt-in through the `LogsActivity` trait.
- For admin timelines or compliance event streams, see the
  [ecosystem decision tree](docs/02-user-guide/07-ecosystem-decision-tree.md).

## Documentation

- [Documentation index](docs/README.md)
- [Installation](docs/01-getting-started/01-installation.md)
- [Basic usage](docs/02-user-guide/01-basic-usage.md)
- [Custom adapters](docs/02-user-guide/02-custom-adapters.md)
- [Queueing](docs/02-user-guide/03-queueing.md)
- [Querying](docs/02-user-guide/04-querying.md)
- [Retention and export](docs/02-user-guide/05-retention-and-export.md)
- [Model auditing and domain mappers](docs/02-user-guide/06-model-auditing-and-domain-mappers.md)
- [Adapter cookbook](docs/02-user-guide/08-adapter-cookbook.md)
- [Upgrade guide](UPGRADE.md)
- [Changelog](CHANGELOG.md)
- [Workflow reference](WORKFLOWS.md)

## Development

The Makefile runs the package's Docker Compose toolchain:

```bash
make build
make install
make lint-all
make test
make ci
```

Integration tests use MongoDB. See [testing and linting](docs/04-development/02-testing-and-linting.md)
and the [contributing guide](CONTRIBUTING.md) for setup and quality checks.

## Community

- [Contributing guide](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Support](SUPPORT.md)
- [Governance](GOVERNANCE.md)

## License

MIT — see [LICENSE](LICENSE).
