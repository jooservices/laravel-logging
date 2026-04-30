# Development

Quality commands:

```bash
composer validate
composer run lint:pint
composer run lint:phpcs
composer run lint:phpstan
composer run lint:phpmd
composer run test
```

Pint is the master formatter. If another style tool disagrees with Pint, adjust that tool instead of changing Pint output.

MongoDB integration tests require a running MongoDB service:

```bash
MONGODB_URI=mongodb://localhost:27017 composer run test:integration
```

Without MongoDB, integration tests skip with an explicit message. Unit tests still cover manager, registry, adapters, DTO building, sanitization, and request context behavior.
