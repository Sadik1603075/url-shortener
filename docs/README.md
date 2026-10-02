# LinkForge Docs Index

Start here. These docs exist so you can regain context without re-reading the codebase.

## Cross-cutting
- **[local-setup.md](local-setup.md)** — run everything locally (start here to boot the stack).
- **[roadmap.md](roadmap.md)** — the 10-day plan (source of truth for what's next/done); normalized tickets in [tickets.md](tickets.md), ground-truth scan in [task-checklist.md](task-checklist.md).
- [architecture/system-overview.md](architecture/system-overview.md) — components, request flows, data model, local↔cloud parity.
- [architecture/observability.md](architecture/observability.md) — metrics, dashboards, structured logs, alerts.
- [load-testing.md](load-testing.md) — JMeter plans ([`load/`](../load/)) + performance baseline.
- [adr/0001-base62-non-enumerable-codes.md](adr/0001-base62-non-enumerable-codes.md) — short-code design.
- [adr/0002-kafka-event-analytics.md](adr/0002-kafka-event-analytics.md) — click analytics via Kafka.
- [adr/0003-database-engine.md](adr/0003-database-engine.md) — host SQL Server (Phase 1).
- [adr/0004-observability.md](adr/0004-observability.md) — Prometheus + Grafana + structured logs.

## Backend (`../backend/docs/`)
- `architecture.md` — layers + request lifecycles.
- `conventions.md` — coding standards.
- `api.md` — endpoint reference (kept current).
- `testing.md` — test strategy (test-per-task).
- `domain-model.md` — entities, fields, invariants.

## Frontend (`../frontend/docs/`)
- `architecture.md` — feature-sliced structure.
- `conventions.md` — coding standards.
- `testing.md` — Vitest + RTL strategy.

## Rules
- Root `../AGENTS.md` (engineering rules) + `../CLAUDE.md` (agent behaviour).
- `../backend/AGENTS.md`, `../frontend/AGENTS.md` (directory-specific).

## Maintenance
Docs describe **current reality**; mark future work "Planned". Update the relevant doc as part of each task, then tick the roadmap box. Keep docs short and skimmable.
