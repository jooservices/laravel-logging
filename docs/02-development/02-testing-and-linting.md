# Testing And Linting

Pint is the primary formatter. Tune PHPCS and PHP-CS-Fixer around Pint output.

Run the local gate before committing:

```bash
git diff --check
composer validate
composer run lint:fix
composer run lint:all
composer run test
composer run check
composer audit
composer run ci
composer run post-install-cmd
```

The format sanity check is wired into `composer run lint:all`:

```bash
composer run format:sanity
```

MongoDB integration tests require a running MongoDB server:

```bash
MONGODB_URI=mongodb://localhost:27017 composer run test:integration
```

Without MongoDB, integration tests skip with an explicit message.

Testing policy for this repository:

- Use the real persistence flow through facade or manager, adapter, DTO, store,
	repository, model, and MongoDB.
- Do not mock or fake internal package services.
- Do not add `ActivityLog::fake()` or fake/assertion helpers such as
	`assertRecorded()`.
- Allowed fakes are limited to Laravel framework boundaries such as
	`Queue::fake()`, `Event::fake()`, and temporary filesystem fakes.
- When queue dispatch is faked, keep a companion real job-handle persistence
	assertion in the suite.
