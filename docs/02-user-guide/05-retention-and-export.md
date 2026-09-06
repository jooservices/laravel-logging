# Retention And Export

Retention is command-based. The package does not install TTL indexes for
per-type retention.

## Per-type retention

Default retention is configured under `laravel-logging.retention.types`:

```php
'retention' => [
    'enabled' => true,
    'default_days' => 180,
    'chunk_size' => 500,
    'types' => [
        'activity' => 90,
        'audit' => 365,
        'security' => 365,
        'domain' => 90,
        'system' => 30,
    ],
],
```

When you run `activity-log:prune` **without** `--type`, `--days`, or `--before`,
the command runs one pass per configured type using each type's `retention_days`,
then runs any configured granular rules (see below).

Explicit overrides still work for one-off or scripted pruning:

```bash
php artisan activity-log:prune --dry-run
php artisan activity-log:prune --force
php artisan activity-log:prune --type=activity --days=90 --force
php artisan activity-log:prune --type=system --days=30 --force
php artisan activity-log:prune --type=audit --before=2026-01-01 --force
```

Prune defaults to dry-run unless `--force` is used. `--days` and `--before`
cannot be combined. Pruning uses `occurred_at` cutoffs, rejects future cutoffs,
and deletes in chunks based on `laravel-logging.retention.chunk_size`.

## Granular retention rules

v1.2 adds `laravel-logging.retention.rules` for adapter/level/action-prefix
matching:

```php
'rules' => [
    [
        'adapter' => 'system',
        'level' => 'debug',
        'action_prefix' => 'http.',
        'retention_days' => 14,
    ],
],
```

Each rule is optional on `adapter`, `level`, and `action_prefix`. Rules run
during the default prune pass (no explicit `--type`/`--days`/`--before`).
Use explicit options when you need a single targeted cutoff.

## Export

Export logs as JSONL or CSV:

```bash
php artisan activity-log:export --type=audit --action=config.updated --from=2026-01-01 --to=2026-01-31 --format=jsonl --output=storage/app/audit.jsonl
php artisan activity-log:export --type=security --format=csv --output=storage/app/security.csv
php artisan activity-log:export --format=jsonl
```

JSONL includes the full stored document array. CSV includes core top-level
fields only. Export streams records in chunks, refuses to overwrite an existing
file unless `--force` is used, and fails when the output directory is missing.
