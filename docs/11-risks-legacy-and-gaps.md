# Risks, Legacy, And Gaps Roadmap

This roadmap tracks improvements that fit the MongoDB-backed structured
logging scope. It rejects storage expansion, dashboards, analytics products,
and observability platform replacement work.

## Must Do

### Raise Coverage To 90%

- What: Add focused tests until statement coverage reaches at least 90%, then
  enforce the threshold in CI.
- Why: CI now runs `composer run ci`, but the current measured statement
  coverage is 82.09%, below the desired DTO-style threshold.
- How: Cover low-risk gaps in command failures, query filters, adapter edge
  cases, sanitizer patterns, payload document trimming, and service-provider
  configuration validation.
- Benefit: Makes coverage enforcement meaningful without faking quality.
- Risk: Broad tests can become brittle if they assert MongoDB implementation
  details instead of public package behavior.
- Tests required: PHPUnit coverage tests through real package services and
  MongoDB-backed integration paths where persistence behavior is involved.
- Priority: must do.

### Harden Export Failure Coverage

- What: Cover export write failures, date boundaries, chunk overrides, and
  JSON summary behavior more completely.
- Why: Export is a production operation and must not silently overwrite or
  emit ambiguous output.
- How: Add integration tests around invalid date ranges, output stream errors,
  forced overwrite summaries, and CSV/JSONL field stability.
- Benefit: Reduces release risk for audit and security log extraction.
- Risk: Filesystem assertions can become environment-sensitive.
- Tests required: PHPUnit integration tests with temporary filesystem paths and
  real MongoDB records.
- Priority: must do.

## Should Do

### Query API Edge Coverage

- What: Expand coverage for external identity filters, pagination defaults,
  date boundaries, and combined actor/subject/causer filters.
- Why: Query APIs reuse logging identity semantics and are easy to regress with
  small refactors.
- How: Add MongoDB integration tests that persist records through adapters and
  query them through the facade.
- Benefit: Keeps reporting and retention callers on stable semantics.
- Risk: Overly broad fixtures can make tests hard to read.
- Tests required: Integration tests using `ActivityLog::query()` and
  `ActivityLog::records()`.
- Priority: should do.

### Doctor Diagnostic Depth

- What: Add explicit diagnostics for malformed adapter config, bad mapper
  config, disabled retention, and missing expected indexes.
- Why: The doctor command is the package readiness entrypoint for production
  operations.
- How: Extend command checks without mutating data, and document warning versus
  failure behavior.
- Benefit: Makes configuration mistakes easier to find before deployment.
- Risk: Diagnostics can drift if they duplicate framework validation too
  aggressively.
- Tests required: Integration tests around `activity-log:doctor --json`,
  `--strict`, and `--check-indexes`.
- Priority: should do.

### Laravel 13 Compatibility Follow-Up

- What: Revisit Laravel 13 support after `jooservices/laravel-repository`
  publishes compatible Illuminate constraints.
- Why: Laravel 13 is the current line, but repository 1.1.0 requires
  Illuminate 12 components.
- How: Update constraints, run Composer with all dependencies, and execute the
  full MongoDB-backed test suite.
- Benefit: Keeps the package aligned with current Laravel package conventions.
- Risk: MongoDB Laravel or repository behavior may change across major
  framework versions.
- Tests required: Full `composer run ci` on Laravel 12 and Laravel 13
  dependency sets.
- Priority: should do.

## Optional

### Additional Built-In Domain Mapper Examples

- What: Add documentation-only examples for common domain event mapper shapes.
- Why: Users may need clearer patterns without adding more built-in behavior.
- How: Extend docs with small mapper examples that preserve the registry path.
- Benefit: Improves adoption while keeping runtime scope stable.
- Risk: Examples can be mistaken for guaranteed domain conventions.
- Tests required: Documentation snippet review; runtime tests only if examples
  are promoted into executable fixtures.
- Priority: optional.

### Retention Dry-Run Reporting Detail

- What: Report sample cutoff metadata and type coverage in prune dry-runs.
- Why: Operators benefit from more confidence before deleting records.
- How: Add non-destructive summary fields to the existing command output.
- Benefit: Safer maintenance workflows.
- Risk: Output changes may affect consumers that parse command text.
- Tests required: Command integration tests for text and JSON output.
- Priority: optional.

## Rejected Out Of Scope

### Alternate Storage Backends

- What: SQL, file, Loki, Elasticsearch, or other storage backends.
- Why: v1 is explicitly MongoDB-only.
- How: Do not implement in this package scope.
- Benefit: Keeps architecture and persistence tests focused.
- Risk: Adding this would split the store/repository/model contract and weaken
  package clarity.
- Tests required: Not applicable.
- Priority: rejected.

### Dashboard, Analytics, Or Observability Replacement

- What: UI dashboards, analytics/reporting products, or replacements for
  Laravel Log, Monolog, Sentry, OpenTelemetry, Loki, or ELK.
- Why: The package stores durable structured application records; it is not an
  observability platform.
- How: Keep those concerns in consuming applications or dedicated tools.
- Benefit: Preserves package boundaries.
- Risk: Implementing these would pull the package away from its operational
  logging contract.
- Tests required: Not applicable.
- Priority: rejected.

### Fake Testing APIs

- What: `ActivityLog::fake()`, `assertRecorded()`, or internal fake services.
- Why: Repository policy requires full-flow behavior through DTO, store,
  repository, model, and MongoDB persistence.
- How: Use Laravel boundary fakes only, such as queues, events, or temporary
  filesystem paths.
- Benefit: Keeps tests honest about persistence behavior.
- Risk: Fake APIs would hide integration regressions.
- Tests required: Not applicable.
- Priority: rejected.
