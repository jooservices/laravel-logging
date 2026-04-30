# MongoDB Schema

Default collection: `activity_logs`.

Persisted fields:

- `uuid`, `type`, `adapter`, `level`, `action`, `message`
- `actor_type`, `actor_id`
- `subject_type`, `subject_id`
- `causer_type`, `causer_id`
- `source`, `source_type`
- `request_id`, `correlation_id`, `trace_id`
- `ip_address`, `user_agent`
- `properties`, `context`, `changes`
- `occurred_at`, `created_at`, `updated_at`

Actor, subject, and causer IDs are stored as strings when present.

Run this command after installation to create supported indexes:

```bash
php artisan activity-log:indexes
```

The package indexes top-level classification, identity, actor, subject, causer, trace, and time fields. It does not index arbitrary nested `properties`, `context`, or `changes` keys in v1.
