# Retention And Export

Retention is command-based in v1. The package does not install TTL indexes for
per-type retention.

Default retention is configured under `laravel-logging.retention.defaults`.

```bash
php artisan activity-log:prune --dry-run
php artisan activity-log:prune --type=activity --days=90
php artisan activity-log:prune --type=system --days=30 --force
```

Production pruning requires `--force` unless `--dry-run` is used. Pruning uses
`occurred_at` cutoffs.

Export logs as JSONL or CSV:

```bash
php artisan activity-log:export --type=audit --action=config.updated --from=2026-01-01 --to=2026-01-31 --format=jsonl --output=storage/app/audit.jsonl
php artisan activity-log:export --type=security --format=csv --output=storage/app/security.csv
```

JSONL includes the full stored document array. CSV includes core top-level
fields only.
