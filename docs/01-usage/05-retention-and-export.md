# Retention And Export

Retention is command-based in v1. The package does not install TTL indexes for
per-type retention.

Default retention is configured under `laravel-logging.retention.types`, with
`laravel-logging.retention.default_days` used when pruning all types together or
when a specific type has no override.

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

Export logs as JSONL or CSV:

```bash
php artisan activity-log:export --type=audit --action=config.updated --from=2026-01-01 --to=2026-01-31 --format=jsonl --output=storage/app/audit.jsonl
php artisan activity-log:export --type=security --format=csv --output=storage/app/security.csv
php artisan activity-log:export --format=jsonl
```

JSONL includes the full stored document array. CSV includes core top-level
fields only. Export streams records in chunks, refuses to overwrite an existing
file unless `--force` is used, and fails when the output directory is missing.
