# Testing And Linting

Pint is the primary formatter. Tune PHPCS and PHP-CS-Fixer around Pint output.

`composer run check` is the local normal gate: `lint:all` plus the unit and
integration suites. `composer run ci` is the coverage gate: `lint:all` plus
`test:coverage`.

Run the local gate before committing:

```bash
git diff --check
composer validate --strict
composer run lint:fix
composer run lint:all
composer run test
composer run test:coverage
composer run check
composer audit
composer run ci
composer run post-install-cmd
```

The format sanity check is wired into `composer run lint:all`:

```bash
composer run format:sanity
```

MongoDB persistence tests require a running MongoDB server:

```bash
MONGODB_URI=mongodb://localhost:27017 composer run test
```

CI always sets `MONGODB_URI` (and `CI=true`). If MongoDB is unreachable while
`MONGODB_URI` or `CI` is set, those tests **fail** — they do not skip.

Without `MONGODB_URI` and outside CI, unreachable MongoDB causes an explicit
skip so a pure unit run can stay green when persistence is unavailable.

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
