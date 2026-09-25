# ADR-0002: Kafka-based click analytics (event sourcing of redirects)

- **Status:** Proposed (confirm at roadmap tasks D4-T1 / D5-T1)
- **Deciders:** Staff engineer (repo owner)
- **Context date:** Phase 1, Day 4–5

## Context

We must track **how many people clicked** each short URL and **collect device info** (browser, OS, device type, referer, IP). The redirect is the hot path: it must stay fast and must not fail because an analytics write failed.

Writing rich analytics synchronously inside the redirect would (a) add latency to every click, (b) couple availability of redirects to availability of the analytics store, and (c) make bursty traffic hammer the DB.

## Options

**A. Synchronous DB insert on redirect.** Simple, but slow and fragile on the hot path. Rejected.

**B. Laravel queue (database/Redis) job per click.** Decouples timing, but ties us to Laravel's queue semantics and doesn't give us a durable, replayable event log or an easy path to multiple independent consumers (e.g. real-time dashboards + batch rollups) in cloud.

**C. Kafka event `url.clicked` + dedicated consumer (recommended).** The redirect **produces** a fact; one or more **consumers** react. Durable, replayable, horizontally scalable, and maps cleanly to MSK in Phase 2. Matches the stated stack (Kafka for event sourcing).

## Decision

Adopt **Option C**.

- **Topic:** `url.clicked` (partitioned by short_code for ordering per link; tune partitions in Phase 2).
- **Event payload (versioned):**
  ```json
  {
    "version": 1,
    "short_code": "b3Kf9Qx",
    "occurred_at": "2026-01-01T12:00:00Z",
    "ip": "203.0.113.4",
    "user_agent": "Mozilla/5.0 ...",
    "referer": "https://example.com"
  }
  ```
- **Producer:** `KafkaClickProducer` behind an interface (`ClickEventPublisherInterface`), emitted **fire-and-forget** from `UrlRedirectService`. If produce fails → log + increment a failure metric, **never** break the redirect (302 still returns).
- **Consumer:** `php artisan clicks:consume` worker. Enriches user-agent → `{browser, os, device_type}`, then persists to `clicks`. Handling is **idempotent** (safe to reprocess on replay).
- **Local:** Redpanda (Kafka-API compatible, single container). **Cloud:** MSK.

## Consequences

- Redirect latency is bounded by Redis + a non-blocking produce, not by analytics writes.
- Analytics can be scaled/replayed independently; new consumers (e.g. real-time counters) can be added without touching the hot path.
- We accept **eventual consistency**: the dashboard reflects clicks a short time after they happen. The row's `click_count` gives an immediate coarse count; the `clicks` table gives the rich, slightly-delayed truth.
- The event schema is a **contract**. Version it; additive changes only without a migration/consumer update.
- Requires running a long-lived consumer process (a Compose service locally; a Deployment in K8s).
