# Upgrade guide

## v1.x → v4.0.0 (dto ^3 / repository ^4)

### Composer

```bash
composer require jooservices/dto:^3.0 jooservices/laravel-repository:^4.0
```

Laravel `^12|^13` remains the supported framework range.

### Behaviour changes to review

1. **Model audit defaults** — `ActivityLogOptions::make()` now uses `logOnlyDirty`,
   skips empty change sets, and excepts Laravel-sensitive attribute names. Override
   explicitly if you relied on full-attribute dumps.
2. **HTTP middleware** — `http.queue` defaults to `true`; auth/reset paths are ignored.
3. **`wherePromoted`** — only fields listed in `laravel-logging.promoted_fields` are allowed.
4. **TTL indexes** — enabling TTL no longer creates a second ascending `occurred_at`
   index; re-run `php artisan activity-log:indexes` after toggling TTL.
5. **CSV export** — the text column header is `message` (was `description`).
6. **Query internals** — public `ActivityLog::query()` API is unchanged; filters
   now go through `laravel-repository` `Filter` / `HasOrder` / `HasIteration`.
7. **DTO boundary** — prefer `ActivityLogData::from($document)` / `$data->with(...)`
   / `$data->toArray()`; `toPersistenceArray()` was removed.
8. **Sanitize/limit** — store `prepare()` is the choke point. `save()` / sync
   `dispatch()` prepare on persist; async `dispatch()` prepares **before** the job
   is queued so Redis/SQS hold redacted bags. Adapter `toData()` stays raw.
9. **Adapter reuse** — after `save()` / `dispatch()`, mutable builder state is cleared.
   Prefer a fresh facade resolve per write.

### Config merge

Publish or merge `config/laravel-logging.php` for:

- expanded `sanitization.sensitive_keys` / `value_patterns`
- `ttl.*` and `http.*` defaults
- default `promoted_fields` for `batch_id` / `workflow_id`
