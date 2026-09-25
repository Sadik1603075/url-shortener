# AGENTS.md — LinkForge (Private URL Shortener)

> Master guide for any AI agent or engineer working in this monorepo.
> Read this first, then the directory-level `AGENTS.md` for the part you are touching.
> This file is the **source of truth for conventions, architecture, and workflow**.

---

## 1. What we are building

**LinkForge** is a **private** URL-shortening service.

| Capability | Actor | Notes |
|---|---|---|
| Shorten a long URL using a **secret access code** | User (unauthenticated, code-gated) | No public sign-up. A valid access code is required to create a short URL. |
| Generate / list / update / delete access codes | Admin | Codes are the only way users can create short URLs. |
| Redirect `GET /{code}` → original URL | Anyone with the link | Cached in Redis; emits a click event. |
| Track click events + device analytics | Admin | Every redirect produces a Kafka event; consumed into an analytics store; visualised on an admin dashboard. |

Short codes follow a **base62 architecture** (see `docs/adr/0001-base62-non-enumerable-codes.md`).

### Non-negotiable product properties
- **Private by default** — creation is impossible without a valid, active, non-expired access code.
- **Short codes must not be enumerable** — this is a security requirement, not a nicety (see ADR-0001).
- **Redirects are hot-path** — they must be fast (Redis cache), and must never block on analytics (fire-and-forget via Kafka).

---

## 2. Target architecture (the "whole system")

```
                          ┌─────────────┐
        Browser ────────► │  Frontend   │  React 19 + Vite (SPA)
                          │  LinkForge  │
                          └──────┬──────┘
                                 │ REST (JSON, Bearer for admin)
                          ┌──────▼──────────────────────────────┐
                          │            Backend API               │  Laravel 13 / PHP 8.3
                          │  Controllers → Services → Repos      │
                          │  Sanctum auth · FormRequests · DTOs  │
                          └───┬──────────┬──────────┬────────────┘
                              │          │          │
                     ┌────────▼──┐  ┌────▼────┐  ┌──▼────────────┐
                     │  SQL DB   │  │  Redis  │  │  Kafka (prod) │
                     │ (source   │  │ (cache: │  │  topic:       │
                     │  of truth)│  │  code → │  │  url.clicked  │
                     └───────────┘  │  longUrl)│ └──┬────────────┘
                                    └─────────┘    │
                                              ┌────▼─────────────┐
                                              │  Click Consumer  │  Laravel worker
                                              │  (device parse)  │
                                              └────┬─────────────┘
                                                   │
                                              ┌────▼─────────┐
                                              │ analytics DB │  clicks + aggregates
                                              └──────────────┘

    Observability (cross-cutting):  App → /metrics → Prometheus → Grafana dashboards
    Logging: structured JSON logs → stdout → (local: Loki/console · cloud: CloudWatch)
```

**Runtime pieces we run locally (Phase 1) and in cloud (Phase 2):** API, Frontend, SQL DB, Redis, Kafka (+ZooKeeper/KRaft), Kafka click-consumer worker, Prometheus, Grafana.

### Phase boundaries
- **Phase 1 (Days 1–10): everything runs on the local machine via Docker Compose**, fully tested (unit + feature + component) and load-tested (JMeter). This mirrors the production topology so the jump to cloud is mechanical.
- **Phase 2 (after Phase 1 is validated): AWS.** Terraform IaC, EKS/Kubernetes objects, Jenkins CI, managed Redis/MSK/RDS. Phase 2 tasks are enumerated in `docs/roadmap.md` but **not started until the user confirms Phase 1 works**.

---

## 3. Repository layout

```
url-shortener/
├── AGENTS.md                 ← you are here (master rules)
├── CLAUDE.md                 ← Claude operating protocol (how an agent should behave)
├── docs/                     ← cross-cutting docs (architecture, roadmap, ADRs)
│   ├── roadmap.md            ← THE 10-day task checklist — update it as you finish work
│   ├── architecture/
│   │   └── system-overview.md
│   └── adr/                  ← Architecture Decision Records
├── backend/                  ← Laravel API  (own AGENTS.md + docs/)
│   ├── AGENTS.md
│   └── docs/
└── frontend/                 ← React SPA    (own AGENTS.md + docs/)
    ├── AGENTS.md
    └── docs/
```

**Docs are load-bearing.** They exist so an agent can regain full context by reading `docs/` instead of re-scanning the entire codebase. See §6.

---

## 4. Engineering principles (apply everywhere)

These are staff-level defaults. Deviations must be justified in an ADR.

1. **Dependency Inversion at the seams.** Depend on interfaces, not concretions, wherever the collaborator is I/O or infrastructure (persistence, cache, event bus). Bind interface→implementation in a service provider. *Already done for `ShortUrlRepositoryInterface`, `AccessCodeRepositoryInterface`, `ShortUrlCacheInterface`.*
2. **Single Responsibility / thin edges.** Controllers orchestrate, they don't decide. Business rules live in **Services**. Persistence lives in **Repositories**. Validation lives in **FormRequests**. Serialization lives in **Resources**. Data crossing a boundary is a **DTO**, never a loose array.
3. **Domain events for side effects.** A click is a fact that happened; recording analytics is a reaction. Emit an event; react asynchronously. Never couple the redirect hot-path to analytics writes.
4. **Fail closed on the security path.** Missing/expired/invalid access code → reject. Non-admin on an admin route → 403. Never "assume safe".
5. **Idempotent & explicit persistence.** Migrations are forward-only and reversible. No implicit schema drift.
6. **Everything is testable because everything is injected.** If a class is hard to test, its dependencies are wrong — fix the design, don't mock the language.
7. **Observability is not optional.** New hot-path code ships with a metric and structured logs.
8. **Twelve-factor config.** All environment-specific values come from env/secrets. No hardcoded hosts, no secrets in git.

---

## 5. Definition of Done (every task)

A task in `docs/roadmap.md` is **not** done until **all** of these are true:

- [ ] Code follows the layering and conventions in the relevant `AGENTS.md`.
- [ ] **Tests written and passing** for the unit of work (see the testing doc for the layer).
- [ ] Public behaviour is documented in the relevant `docs/` file (API endpoint, event schema, config key…).
- [ ] `docs/roadmap.md` checkbox ticked, with a one-line note if anything deviated from plan.
- [ ] No secrets, no debug dumps, no commented-out dead code left behind.
- [ ] Lint/format clean (`pint` backend, `eslint` frontend).

> **Test-after-each-task is a hard rule.** We do not batch testing to the end. Each task delivers its own tests.

---

## 6. Documentation discipline (token & context economy)

The `docs/` trees exist to **avoid re-reading the whole codebase every session**.

- **Before starting a task:** read the roadmap entry + the one or two `docs/` files it references. Do **not** grep the world.
- **While working:** if you discover a fact worth keeping (an env key, a gotcha, an interface contract), record it in the right doc.
- **After a task:** update the doc that describes that area. Docs describe **current reality**, not aspirations — mark future work as "Planned".
- Keep docs **short, structured, and skimmable** (tables, bullets, code fences). A doc that is cheaper to re-derive than to read has failed.

---

## 7. Local-first workflow

Everything must run on `docker compose up` (added in Day 1). The canonical commands live in the root `Makefile` (Day 1 deliverable). Until then:

- Backend: `cd backend && composer install && php artisan migrate && php artisan serve`
- Frontend: `cd frontend && npm install && npm run dev`

Never assume a service (Redis/Kafka) is reachable — read its host/port from config and fail with a clear message.

---

## 8. Git & change hygiene

- Small, reviewable commits scoped to one roadmap task. Reference the task id (e.g. `[D2-T3] add base62 codec`).
- Conventional-ish subjects: `feat|fix|refactor|test|docs|chore(scope): summary`.
- Never commit `.env`, credentials, or `vendor/`, `node_modules/` (already git-ignored).
- Branch per task off `main`; open a PR; CI (Phase 2 Jenkins) must be green before merge.

---

## 9. Where to look next

| I'm working on… | Read… |
|---|---|
| The plan / what's next | `docs/roadmap.md` |
| Big-picture data flow | `docs/architecture/system-overview.md` |
| Why short codes are designed this way | `docs/adr/0001-base62-non-enumerable-codes.md` |
| Why Kafka for clicks | `docs/adr/0002-kafka-event-analytics.md` |
| Backend code rules | `backend/AGENTS.md` + `backend/docs/` |
| Frontend code rules | `frontend/AGENTS.md` + `frontend/docs/` |
