# LinkForge — Whole-System Architecture

> A single-file tour of **how every part of LinkForge fits together**, plus a short
> description of each piece. Reflects the **currently implemented reality** of the
> repo (not just the aspirational roadmap). For deeper dives see `docs/architecture/`,
> `backend/docs/`, `frontend/docs/`, and the ADRs in `docs/adr/`.
>
> Generated 2026-09-30.

---

## 1. What LinkForge is

**LinkForge** is a **private** URL shortener. Unlike a public shortener, nobody can
create a link without a **secret access code** issued by an admin. Every redirect is
served fast from a Redis cache and produces a **click event** that flows through an
analytics pipeline (Kafka in production, in-process in dev) into a dashboard.

Three non-negotiable product properties drive the whole design:

| Property | Meaning | Where it's enforced |
|---|---|---|
| **Private by default** | You cannot create a short URL without a valid, active, non-expired access code. | `AccessCodeService::validateAndConsume` gates `POST /api/v1/urls`. |
| **Non-enumerable codes** | Short codes can't be guessed by counting up. Security requirement. | `Support/ShortCodeGenerator` (base62); see `docs/adr/0001`. |
| **Redirect is a cold-blooded hot path** | Redirects must be fast and must **never** fail/block on analytics. | Redis cache + fire-and-forget event emission in `UrlRedirectService`. |

---

## 2. The system at a glance

```
                      ┌──────────────┐
     Browser ───────► │  Frontend    │   React 19 + Vite SPA
                      │  (LinkForge) │
                      └──────┬───────┘
                             │  REST / JSON  (Bearer token for admin)
                      ┌──────▼────────────────────────────────┐
                      │            Backend API                  │  Laravel 12 / PHP 8.3
                      │  Route → FormRequest → Controller       │
                      │        → Service → Repository           │
                      │  Sanctum auth · DTOs · Resources        │
                      └───┬───────────┬───────────┬─────────────┘
                          │           │           │
                 ┌────────▼──┐   ┌────▼────┐   ┌──▼──────────────┐
                 │  SQL DB   │   │  Redis  │   │  Kafka          │
                 │ SQL Server│   │ cache:  │   │  topic:         │
                 │ (source   │   │ code →  │   │  url.clicked    │
                 │  of truth)│   │ longUrl │   └──┬──────────────┘
                 └───────────┘   └─────────┘      │
                          ▲                   ┌────▼──────────────┐
                          │                   │  clicks:consume   │  Laravel worker
                          │                   │  (ConsumeClicks)  │
                          │                   └────┬──────────────┘
                          │                        │
                          │                  ┌─────▼───────────┐
                          └──────────────────┤  ClickProjector │  CQRS projection
                                             └─────┬───────────┘
                                                   ▼
                          click_events (log) + click_daily_aggregates (read model)
                                             + short_urls.click_count
```

The **redirect** and the **analytics projection** are deliberately decoupled by an
event (`UrlClicked`). The redirect only *emits the fact*; a separate reader
*projects* it. This is textbook **CQRS + event sourcing** (see `docs/adr/0002`).

---

## 3. The four core flows

### Flow 1 — Create a short URL (public, but code-gated)
```
POST /api/v1/urls { long_url, access_code, expires_at? }
  → CreateShortUrlRequest / StoreShortUrlRequest   (validate shape)
  → ShortUrlController@store
  → AccessCodeService.validateAndConsume(code)      → owner user_id
        (rejects with 422 if missing / inactive / expired; stamps last_used_at)
  → ShortUrlService.create(userId, CreateShortUrlData)   [DB::transaction]
        → ShortCodeGenerator.generate()   (base62, non-enumerable)
        → ShortUrlRepository.create(...)
  → 201 { data: ShortUrlResource }
```

### Flow 2 — Redirect (the hot path)
```
GET /{code}   (routes/web.php, code constrained to [A-Za-z0-9]+)
  → RedirectController → UrlRedirectService.redirect(code, ClickContext)
      1. Redis GET short_url:{code}
           hit  → emit UrlClicked (fire-and-forget) → 302 away   ✅ fast path
      2. miss → ShortUrlRepository.findByShortCode
           invalid / expired  → 404
      3. Redis SET (ttl derived from expires_at)     (populate cache)
      4. emit UrlClicked (fire-and-forget)
      5. 302 away
```
Emission is wrapped in `try/catch`: if the transport (Kafka/DB) is down, it logs
`click.publish.failed` and the redirect **still succeeds**. The per-URL `click_count`
counter is bumped inside the projection, not on the request thread under Kafka.

### Flow 3 — Analytics ingestion (async, CQRS)
```
publisher.publish(UrlClicked)
   ├─ driver=sync  → ClickProjector.project()  inline (dev; no Kafka needed)
   └─ driver=kafka → topic url.clicked → clicks:consume worker → ClickProjector.project()

ClickProjector.project(event)   [DB::transaction]
   → analytics.recordEvent(event)          append to click_events   (the log)
   → analytics.incrementDaily(date)        bump click_daily_aggregates (read model)
   → shortUrls.incrementClickCountByCode() bump short_urls.click_count
```
Both transports funnel through the **same** `ClickProjector`, so projection logic
lives in exactly one place. The dashboard reads the aggregates, never the raw log.

### Flow 4 — Admin (authenticated)
```
POST /api/v1/auth/login  → AuthenticationService (verify creds + admin role) → Sanctum token
Bearer token → /api/v1/admin/*   (auth:sanctum + admin middleware)
   • analytics/overview               → AnalyticsService.overview()
   • urls   index/show/update/destroy → ShortUrlService (update/delete bust Redis cache)
   • access-codes CRUD + /{id}/send   → AccessCodeService (+ email the code)
```

---

## 4. Backend anatomy (Laravel)

Strict layering — each layer has one job and is forbidden from doing the next
layer's job. Data crossing a boundary is a **DTO**, never a loose array.

| Layer | Location | Responsibility | Example |
|---|---|---|---|
| **Route** | `routes/api.php`, `routes/web.php` | URL → controller, attach middleware | `POST /api/v1/urls` |
| **Middleware** | `app/Http/Middleware` | Cross-cutting gates | `EnsureUserIsAdmin` (`admin` alias) |
| **FormRequest** | `app/Http/Requests` | Validation + `authorize()` | `StoreShortUrlRequest`, `LoginRequest` |
| **Controller** | `app/Http/Controllers` | Orchestrate one service call, return a Resource | `ShortUrlController`, `AnalyticsController` |
| **Service** | `app/Services/<Domain>` | Business logic, transactions, coordinate repos/cache/events | `ShortUrlService`, `UrlRedirectService`, `AccessCodeService`, `AuthenticationService`, `AnalyticsService` |
| **Repository** | `app/Repositories` (Contracts + Eloquent) | **All** persistence; returns Models/paginators | `EloquentShortUrlRepository`, `EloquentClickAnalyticsRepository` |
| **Model** | `app/Models` | fillable, casts, relations, tiny invariants (`isValid()`) | `ShortUrl`, `AccessCode`, `User`, `ClickEvent`, `ClickDailyAggregate` |
| **Resource** | `app/Http/Resources` | Output shaping (JSON) | `ShortUrlResource`, `AccessCodeResource`, `UserResource` |
| **DTO** | `app/DTOs` | Immutable data across boundaries | `CreateShortUrlData`, `LoginData`, `ClickContext` |
| **Support** | `app/Support` | Pure helpers, no I/O | `ShortCodeGenerator`, `AccessCodeGenerator` |
| **Event** | `app/Events` | Immutable domain fact | `UrlClicked` |
| **Messaging** | `app/Messaging` | Event transport + projection | publishers + `ClickProjector` |
| **Console** | `app/Console/Commands` | Long-running / CLI jobs | `ConsumeClicks`, `CreateAdminUser` |

### Dependency inversion (the seams)
Infrastructure collaborators are always behind an interface, bound in a provider,
so everything is swappable and testable:

| Interface | Default binding | Bound in |
|---|---|---|
| `ShortUrlRepositoryInterface` | `EloquentShortUrlRepository` | `RepositoryServiceProvider` |
| `AccessCodeRepositoryInterface` | `EloquentAccessCodeRepository` | `RepositoryServiceProvider` |
| `ClickAnalyticsRepositoryInterface` | `EloquentClickAnalyticsRepository` | `RepositoryServiceProvider` |
| `ShortUrlCacheInterface` | `RedisShortUrlCache` | `AppServiceProvider` |
| `ClickEventPublisherInterface` | `Sync` / `Kafka` / `Log` (by `CLICK_EVENT_DRIVER`) | `MessagingServiceProvider` |

### The click-event publishers (swappable transport)
Selected at runtime by `CLICK_EVENT_DRIVER`:

- **`SyncClickEventPublisher`** — projects inline in the request via `ClickProjector`.
  Default; the host dev runtime needs no Kafka and no `ext-rdkafka`.
- **`KafkaClickEventPublisher`** — produces JSON to topic `url.clicked` (keyed by
  short code for per-URL ordering), fire-and-forget on librdkafka's background
  thread. Only used in the Dockerised backend (which ships `ext-rdkafka`).
- **`LogClickEventPublisher`** — discards to the log; used for load tests / when
  analytics is intentionally disabled.

---

## 5. Frontend anatomy (React SPA)

Feature-sliced React 19 + Vite. **Server state lives in react-query only**; forms
use react-hook-form + zod; auth token lives in `localStorage`.

| Layer | Location | Responsibility |
|---|---|---|
| **Composition root** | `src/main.jsx`, `src/app/` | `main.jsx` mounts `<App/>` in `<Providers/>`; `router.jsx` builds the route tree; `providers.jsx` wraps in `QueryClientProvider`. |
| **Framework glue** | `src/lib/` | `apiClient` (axios + Bearer-token interceptor, 401 → clears token), `queryClient`, `utils`. |
| **Layout** | `src/components/layout/` | `PublicLayout`, `AdminLayout` (+ `Sidebar`, `Topbar`) — auth-aware chrome. |
| **Common UI** | `src/components/common/` | `Logo`, `NotFound`, reusable presentational bits. |
| **Feature** | `src/features/<name>/` | Each feature = pages + `api.js` + react-query `hooks.js` + zod `schema.js`. |

### Routes & pages (implemented)
```
PublicLayout
  /                     GenerateUrlPage      ← enter access code + long URL → short link
/login                  LoginPage            ← admin email/password
ProtectedRoute → /admin AdminLayout
  index / (urls)        DashboardPage        ← analytics overview + charts
  /admin/urls           ShortUrlsPage        ← URL table (list/manage)
  /admin/access-codes   AccessCodesPage      ← access-code CRUD
*                       NotFound
```

### Features
- **`auth`** — `LoginPage`, `ProtectedRoute`, `authStore`, `api.js` (login/logout).
  Token + user cached in `localStorage`; `ProtectedRoute` redirects to `/login`.
- **`shortUrls`** — `GenerateUrlPage` (public create), `DashboardPage`,
  `ShortUrlsPage`; `StatsCard` + `UrlTable` components; react-query hooks.
- **`accessCodes`** — `AccessCodesPage` CRUD table with react-query mutations.
- **`analytics`** — `api.js` + `hooks.js` + `ActivityChart` fed by
  `/admin/analytics/overview`.

### Data flow
```
Component → useX hook (react-query) → feature api.js → apiClient (axios) → backend
Form input → react-hook-form + zodResolver → mutation hook → api.js
Server 422 errors → mapped back onto form fields
```

---

## 6. Data model

```
User 1───* AccessCode          User 1───* ShortUrl
ShortUrl.short_code ──(key)──► click_events / click_daily_aggregates (by short_code / date)
```

| Table | Key fields | Role |
|---|---|---|
| **users** | `id, name, email(unique), password, role` | Admins (`role`: user \| admin \| super_admin). Sanctum `HasApiTokens`. |
| **access_codes** | `id, user_id, code(unique), is_active, expires_at, last_used_at` | The **gate** for creating URLs. `isValid()` = active & not expired. Can carry an `email` + `description` and be emailed to a recipient. |
| **short_urls** | `id, user_id, short_code(unique), long_url, is_active, click_count, expires_at, last_accessed_at` | The links. `isValid()` = active & not expired. `click_count` is the coarse counter. |
| **click_events** | append-only log of `UrlClicked` facts | **Write model** (event store). Replayable — new projections can be rebuilt from it. |
| **click_daily_aggregates** | per-day click rollup | **Read model** — what the dashboard queries. Written **only** by `ClickProjector`. |
| **personal_access_tokens** | Sanctum tokens | Bearer auth for admin API. |
| **cache / jobs** | Laravel framework tables | Cache store / queue plumbing. |

> Note: an older `backend/docs/domain-model.md` still describes a flat `clicks`
> table as "Planned (D5)". That design was superseded by ADR-0002's event-sourced
> `click_events` + `click_daily_aggregates`, which is what actually ships.

---

## 7. Infrastructure & runtime (local Phase 1)

Everything runs from the root `docker-compose.yml`, with **SQL Server on the
Windows host** (reached from containers via `host.docker.internal`).

| Service | Image / command | Purpose |
|---|---|---|
| **kafka** | `apache/kafka:3.8.0` (KRaft, no ZooKeeper) | Event bus, topic `url.clicked`. |
| **kafka-ui** | `provectuslabs/kafka-ui` | Inspect topics/messages at `localhost:8080`. |
| **redis** | `redis:7-alpine` | Hot cache `short_url:{code} → long_url`. |
| **api** | `./backend` Dockerfile | The Laravel HTTP API (`localhost:8000`), `CLICK_EVENT_DRIVER=kafka`. |
| **clicks-worker** | `php artisan clicks:consume` | Long-lived consumer projecting Kafka events. |
| *(host)* **SQL Server** | on the Windows host, port 1433 | Source of truth (`sqlsrv` driver). |

**Dev vs Docker split:** on the host, `CLICK_EVENT_DRIVER=sync` (no Kafka,
no `ext-rdkafka` needed — analytics project inline). In Docker,
`CLICK_EVENT_DRIVER=kafka` and the worker does the projection asynchronously.
This is deliberate: Windows makes native PHP extensions painful, so Kafka lives
only in the container image.

Key config keys (see `backend/.env.example`, `backend/config/kafka.php`):
`CLICK_EVENT_DRIVER`, `KAFKA_BROKERS`, `KAFKA_CLICK_TOPIC`, `REDIS_HOST`,
`REDIS_CLIENT=predis`, `DB_CONNECTION=sqlsrv`.

---

## 8. Cross-cutting concerns

- **Security** — private creation (access code required, fail closed on
  missing/expired/invalid); admin RBAC via `auth:sanctum` + `admin` middleware;
  non-enumerable base62 codes; no secrets in git (`.env` local only).
- **Caching** — only the redirect lookup is cached; the cache is busted when an
  admin updates or deletes a URL.
- **Resilience** — the redirect hot path degrades gracefully: a Kafka/DB outage
  logs `click.publish.failed` and the 302 still happens.
- **Consistency** — under `kafka`, click counts are **eventually** consistent
  (they trail until the consumer catches up); under `sync` they're immediate.
- **Config** — twelve-factor; all hosts/ports/secrets from env, documented in
  `*.env.example`.
- **Observability** — *owed*: a `/metrics` (Prometheus) endpoint + Grafana
  dashboards are planned (roadmap Day 8). Today there are structured logs only.

---

## 9. Architecture decisions (ADRs)

| ADR | Decision | Why it matters |
|---|---|---|
| **0001** | Base62, **non-enumerable** short codes | Codes must not be guessable by counting — a security property, not cosmetics. |
| **0002** | Click analytics via `UrlClicked` events (CQRS) over Kafka | Decouples the hot-path redirect from analytics; event log is replayable; one projection path for sync + Kafka. |
| **0003** | DB engine = **host SQL Server** (`sqlsrv`) | Same engine local → cloud (Terraform-provisioned SQL Server in Phase 2); no Postgres swap. |

---

## 10. Where the codebase is headed

- **Phase 1 (Days 1–10)** — the whole topology runs locally under Docker Compose,
  fully tested (unit + feature + component) and load-tested (JMeter). Much of the
  domain, API, event pipeline, Compose stack, and frontend pages are already in
  place; still owed are the base62 counter strategy (D2), device-enrichment
  projection (D5), `/metrics` + Grafana (D8), and JMeter baselines (D9).
- **Phase 2 (after Phase 1 sign-off)** — AWS: Terraform IaC, EKS/Kubernetes,
  Jenkins CI/CD, managed RDS/ElastiCache/MSK. The Compose topology is a 1:1
  conceptual mirror of the target K8s topology, so the jump is mostly translation.

**Source of truth for "what's next":** `docs/roadmap.md`.

---

## 11. Reading map — where to go next

| To understand… | Read… |
|---|---|
| Big-picture data flow | `docs/architecture/system-overview.md` (and this file) |
| Master rules & conventions | `AGENTS.md` |
| Backend layering & lifecycles | `backend/AGENTS.md`, `backend/docs/architecture.md` |
| Backend data model | `backend/docs/domain-model.md` |
| Frontend structure | `frontend/AGENTS.md`, `frontend/docs/architecture.md` |
| Why codes look the way they do | `docs/adr/0001-base62-non-enumerable-codes.md` |
| Why Kafka / how analytics works | `docs/adr/0002-kafka-event-analytics.md`, `docs/kafka-runbook.md` |
| DB engine choice | `docs/adr/0003-database-engine.md` |
| The task checklist | `docs/roadmap.md` |
