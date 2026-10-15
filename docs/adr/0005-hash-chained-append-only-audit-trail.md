# ADR-0005: Hash-chained, append-only audit trail

**Status:** Accepted

## Context
Lending decisions get disputed and examined long after they're made. "Who did what, when, and on what basis"
must be answerable, and the record must be trustworthy even if someone with database access edits it.

## Decision
- One `audit_events` row per meaningful step (application created, document uploaded, submitted, document
  processed, decision made, decision explained), each with an actor (`user` or `system`).
- **Per-application hash chain:** each event stores `hash = sha256(canonical JSON of id, position, event, actor,
  data, prev_hash)`. Events carry an explicit `position` (1, 2, 3…, unique per application), so reordering or deleting
  an event breaks verification, not just editing one.
- **Append-only, twice over:** the Eloquent model refuses update/delete, and on PostgreSQL a trigger rejects
  `UPDATE`/`DELETE` even from raw SQL.
- Appends lock the application row so concurrent writers can't fork a chain.
- `php artisan audit:verify` and `GET /api/applications/{id}/audit` (`chain_intact`) re-verify on demand.
- **No PII in audit data:** statuses, ids, counts, codes and versions only. Redaction is recorded as counts by type.

## Consequences
- Tampering is *evident*, not *impossible*: a superuser can disable the trigger and rewrite rows, but cannot do so
  without breaking the chain unless they also recompute every later hash. Anchoring the latest hash externally
  (e.g. periodic export to write-once storage) would close that gap. Not built yet.
- Every write to the trail takes a row lock on the application. That's fine at this volume, and a known contention point at scale.
