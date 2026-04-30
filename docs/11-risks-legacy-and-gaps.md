# Risks And Gaps

- Queue logging is available only through `queue(...)->dispatch()`. `save()` remains synchronous by design.
- MongoDB indexes are created by `php artisan activity-log:indexes`; the service provider does not create indexes during normal requests.
- Integration tests need MongoDB. They skip locally when MongoDB is unavailable, and CI provides a MongoDB service.
- v1 does not provide SQL storage, a UI dashboard, nested payload indexes, automatic model observers, or observability-stack integrations.
