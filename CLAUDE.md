# Claude Guidance

Follow `AGENTS.md`. Preserve the MongoDB-only, DTO-first, registry-based
adapter architecture. Do not add hard-coded manager methods for built-in
adapters. Keep operation commands, sanitization, payload limits, docs, and tests
in sync. `composer run check` is the normal local gate, and `composer run ci`
is the coverage gate. Use author `Viet Vu <jooservices@gmail.com>`, keep files
readable, and verify CaptainHook before committing.
