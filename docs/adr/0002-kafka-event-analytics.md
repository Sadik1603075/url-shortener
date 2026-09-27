# ADR-0002 — Click analytics via UrlClicked events (CQRS) over Kafka

- **Status:** Accepted
- **Date:** 2026-09-27
- **Context tags:** hot-path, analytics, infra, event-sourcing

## Context

A redirect (`GET /{code}`) is the product's hot path. It must be fast (Redis
cache) and must **never fail or block** because analytics is slow or down
(root `AGENTS.md` §1, §4). We also want click analytics — totals, a
clicks-over-time chart, per-URL rankings, and later device/referrer breakdowns.

Writing analytics synchronously in the redirect couples the hot path to the
analytics store. Instead we treat a click as a **domain event** and apply CQRS:
the redirect only *emits* the fact; a separate reader *projects* it into a query
model.

The team runs on Windows where native PHP extensions are painful (we already
swapped `phpredis` → `predis`). Getting `php-rdkafka` on the host is the friction
point, so the backend is **containerised** for the Kafka runtime (chosen in the
task discussion), while host dev uses a driver that needs no extension.

## Decision

1. **Event:** `App\Events\UrlClicked` — an immutable fact (short_code, long_url,
   occurred_at, ip, user_agent, referer), serialized as JSON.
2. **Write side:** the redirect calls `ClickEventPublisherInterface::publish()`
   **fire-and-forget**, wrapped in try/catch — a transport failure logs and the
   302 still succeeds.
3. **Transport is swappable via `CLICK_EVENT_DRIVER`:**
   - `sync` — project inline in the request (host dev, no Kafka). Default.
   - `kafka` — produce to topic `url.clicked`; the `clicks:consume` worker
     projects asynchronously (Dockerised runtime with `ext-rdkafka`).
   - `log` — discard to the log (load tests / analytics disabled).
4. **Event store (write model):** append-only `click_events` table — the durable
   log, kept in the **same SQL database** (no separate analytics DB in Phase 1).
5. **Read model (query side):** `click_daily_aggregates` (+ `short_urls.click_count`),
   written **only** by the `ClickProjector`. The dashboard reads this, never the
   event log.
6. **One projection path:** both `sync` and the Kafka consumer call the same
   `ClickProjector`, so projection logic lives in exactly one place.

```
GET /{code} ──▶ UrlRedirectService
                  ├─ Redis cache (target)                    ← hot path
                  └─ publisher.publish(UrlClicked)  (try/catch, fire-and-forget)
                         │
              sync ──────┤─────── kafka
                         ▼                 ▼
                  ClickProjector     topic: url.clicked ──▶ clicks:consume ──▶ ClickProjector
                         │                                                          │
                         ▼                                                          ▼
        click_events (log) + click_daily_aggregates + short_urls.click_count  (read model)
```

## Consequences

**Good**
- Redirect stays fast and resilient; analytics can lag or fail without user impact.
- Host dev works with zero Kafka (`sync`); production faithfully uses Kafka.
- The read model makes dashboard queries cheap (no scan of the event log).
- The event log is replayable — new projections can be rebuilt from `click_events`.

**Costs / follow-ups**
- Eventual consistency under `kafka`: counts trail until the consumer catches up.
- `ext-rdkafka` only exists in the Docker image (documented in `composer.json`
  `suggest` and the Dockerfile).
- Aggregates are per-day global; per-URL/day and device parsing are future
  projections off the same event log.
- A `/metrics` counter for published/consumed clicks is still owed (observability
  roadmap item) — today we have structured logs (`click.publish.failed`).

## How to run

See [`docs/kafka-runbook.md`](../kafka-runbook.md).
