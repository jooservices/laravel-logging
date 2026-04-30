# Repository Quality

- Use PHP 8.5, Laravel 12, `mongodb/laravel-mongodb`, `jooservices/dto`, and `jooservices/laravel-repository`.
- Keep persistence flowing through `ActivityLogData` -> `MongoLogStore` -> `ActivityLogRepository` -> `ActivityLogRecord`.
- Keep adapter resolution dynamic through the registry.
- Do not parse string identities; use external reference methods for type/id pairs.
- `save()` is sync; queued logs use `queue(...)->dispatch()`. `sync()->dispatch()` records immediately.
- Pint wins over PHPCS/php-cs-fixer style disagreements.
- Run `composer validate`, `composer audit`, and `composer run check`.
