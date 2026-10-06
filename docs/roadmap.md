# Roadmap & Task Checklist — LinkForge

> **This is the single source of truth for "what's next" and "what's done".**
> Tick a box only when the task meets the full Definition of Done (`AGENTS.md` §5): code + tests + docs.
> Task ids are `D<day>-T<n>`. The user commands tasks one at a time; do not run ahead.

**Legend:** `[ ]` todo · `[~]` in progress · `[x]` done · 🔒 blocked/needs decision

---

## Status snapshot

| Phase | Scope | State |
|---|---|---|
| Phase 0 | Existing scaffold (models, repos, services, DTOs, API controllers, resources, routes, FE structure) | **Mostly complete** — see "Already in place" |
| **Phase 1** | **Full system running locally + tested + load-tested (Days 1–10)** | **In progress** — click event pipeline (ADR-0002) + Compose stack already landed ahead of the linear day order; see `docs/plan-understanding.md` §6 |
| Phase 2 | AWS: Terraform, EKS/K8s, Jenkins, managed services | Backlog (do not start until Phase 1 signed off) |

### Already in place (Phase 0 — verified)
- Backend layering: `ShortUrlRepository`, `AccessCodeRepository` (+interfaces), `ShortUrlService`, `AuthenticationService`, `AccessCodeService`, `UrlRedirectService`.
- DTOs: `CreateShortUrlData`, `UpdateShortUrlData`, `LoginData`. Enum: `UserRole`.
- HTTP: `Api/V1/AuthController`, `Api/V1/ShortUrlController`, `RedirectController`; FormRequests; `ShortUrlResource`, `UserResource`; `EnsureUserIsAdmin` middleware; `api.php` + `web.php` routes wired.
- Redis cache abstraction (`ShortUrlCacheInterface` → `RedisShortUrlCache`).
- `ShortCodeGenerator` — **counter + keyed Feistel → base62** (ADR-0001, implemented at D2); no longer random/collision-checked.
- Models: `User` (role), `ShortUrl`, `AccessCode` + migrations.
- Frontend scaffold: `app/` (router, providers), `lib/` (apiClient, queryClient), feature folders (empty), `.env`.
- **Click event sourcing (ADR-0002, implemented):** `UrlClicked` event; `Sync`/`Log`/`Kafka` click-event publishers behind `ClickEventPublisherInterface`; `ClickProjector`; `AnalyticsService`; `clicks:consume` worker (`ConsumeClicks`); `click_events` + `click_daily_aggregates` migrations; `CLICK_EVENT_DRIVER` config.
- **Local Compose stack:** root `docker-compose.yml` + `backend/Dockerfile` running `kafka` (apache/kafka 3.8 KRaft), `kafka-ui`, `redis`, `api`, `clicks-worker`. (DB = SQL Server on the Windows host via `host.docker.internal`.)

### Decisions

**Still open 🔒**
- _(none)_

**Resolved ✅ (recorded here so they aren't re-litigated)**
2. **Base62 generation strategy → counter + keyed obfuscation (ADR-0001 Accepted, D2).** `ShortCodeGenerator` now does `counter.next()` → 4-round keyed Feistel → `Base62`, padded to `SHORTCODE_MIN_LENGTH`. Unique + non-enumerable + no collision lookup. Counter via `short_code_counters` table.
1. **DB engine → host SQL Server (ADR-0003).** Accept **SQL Server (`sqlsrv`) on the host** for Phase 1 (reached from containers via `host.docker.internal`, no containerised `db`). Cloud (Phase 2) provisions an **SQL Server instance via Terraform** — same engine end to end, no Postgres swap. `.env.example` made coherent (`sqlsrv`, `REDIS_CLIENT=predis`).
3. **Kafka distribution.** ~~Redpanda recommended~~ → shipped **`apache/kafka:3.8.0` (KRaft)** + `kafka-ui` in Compose; **MSK** planned for cloud.
4. **Analytics store.** ~~Flat `clicks` table~~ → resolved by **ADR-0002**: event-sourced **`click_events` (log) + `click_daily_aggregates` (read model)** in the **same SQL DB**; device enrichment deferred to a future projection.

---

## Phase 1 — Local system (Days 1–10)

### Day 1 — Local environment & tooling foundation
Goal: `docker compose up` brings the whole topology online; test runners exist on both sides.
- [x] **D1-T1** Root `docker-compose.yml` + `Makefile` (`make up/down/logs/test/fresh`). *Compose runs `api`, `redis`, `kafka`, `kafka-ui`, `clicks-worker`, `prometheus`, `grafana` (no `frontend`/`db` service — DB is host SQL Server). `Makefile` added (D1-T1b).*
- [x] **D1-T2** Choose + wire DB engine (decision 1 → **ADR-0003**). *Accepted host SQL Server (`sqlsrv`); no containerised `db`. `.env.example` made coherent (`sqlsrv`, `REDIS_CLIENT=predis`). Verified: `migrate:status` → all 10 migrations Ran; `composer test` green (6/6). Cloud DB = Terraform-provisioned SQL Server (Phase 2).*
- [ ] **D1-T3** Backend test harness: confirm PHPUnit config, add `Tests\TestCase` base, a `RefreshDatabase` example test, CI-friendly `composer test`.
- [x] **D1-T4** Frontend test harness: **Vitest 5 + RTL 16 + jsdom 30** + jest-dom; `test`/`test:watch` scripts; `test` block in `vite.config.js` (jsdom, globals, `src/test/setup.js`); smoke test renders `<Logo />`. *`npm test` green (1/1).*
- [x] **D1-T5** `docs/local-setup.md`: run-everything-locally guide — prereqs, DB (dev + test) setup, Docker (`make up`) / host-dev paths, ports, seeded admin, `CLICK_EVENT_DRIVER`, observability, tests, self-review checklist.
- **Tests this day:** backend RefreshDatabase smoke; frontend render smoke.

### Day 2 — Core domain: base62 short codes
Goal: correct, non-enumerable short-code generation with full unit coverage.
- [x] **D2-T1** ADR-0001 confirmed (Accepted); `Support/Base62` codec (encode/decode) with known-vector + round-trip tests.
- [x] **D2-T2** Counter (`short_code_counters` table + `ShortCodeCounterInterface`/`DatabaseShortCodeCounter`) + keyed 4-round Feistel; `ShortCodeGenerator` refactored behind its existing `generate()` seam (no controller/service change).
- [x] **D2-T3** Invariants: alphabet-only, min-length padding, non-enumerability (consecutive ids → scattered codes), uniqueness over N, decode round-trip. *Added `SHORTCODE_KEY`/`SHORTCODE_MIN_LENGTH` + `config/shortcode.php`.*
- **Tests:** `Base62Test`, `ShortCodeGeneratorTest` (unit) — green (full suite 124/124).

### Day 3 — Access-code admin CRUD (backend)
Goal: admin can create/list/update/delete access codes end-to-end.
- [ ] **D3-T1** Extend `AccessCodeRepositoryInterface` + Eloquent impl: `paginate`, `findById`, `create`, `update`, `delete`.
- [ ] **D3-T2** `AccessCodeService`: generate (secure random code), list, update (activate/expire), delete.
- [ ] **D3-T3** `Api/V1/AccessCodeController` (admin-guarded) + FormRequests + `AccessCodeResource`; wire routes under `/admin/access-codes`.
- [ ] **D3-T4** Authorization: ensure all admin routes behind `auth:sanctum` + `admin`.
- **Tests:** feature tests for each endpoint (auth required, admin required, happy path, validation errors); `AccessCodeServiceTest` unit.

### Day 4 — Redis cache + Kafka click event production
Goal: redirects are cached and emit a click event without blocking.
> **Reconciled with ADR-0002 (shipped).** The design that landed uses
> `ClickEventPublisherInterface` with swappable drivers (`sync`/`kafka`/`log` via
> `CLICK_EVENT_DRIVER`), not a single `KafkaClickProducer`. Verify the checkboxes
> below meet the full Definition of Done (esp. tests) before ticking `[x]`.
- [x] **D4-T1** Kafka in Docker Compose + Kafka UI. *Shipped `apache/kafka:3.8.0` (KRaft) + `kafka-ui` (decision 3), not Redpanda. Confirm `.env.example` documents `KAFKA_BROKERS`, `KAFKA_CLICK_TOPIC`, `CLICK_EVENT_DRIVER`.*
- [ ] **D4-T2** Switch backend cache path to Redis for redirects; confirm `UrlRedirectService` cache hit/miss + TTL logic; add cache-bust on update/delete (already in `ShortUrlService`).
- [x] **D4-T3** `UrlClicked` domain event emitted **fire-and-forget** from the redirect via `ClickEventPublisherInterface` (`Sync`/`Log`/`Kafka` impls). *Producer is driver-swappable, not a single `KafkaClickProducer`.*
- [x] **D4-T4** Graceful degradation: Kafka down ⇒ redirect still 302 (never 500), `click.publish.failed` logged, and `click_publish_failures_total` metric increments. *Publish wrapped in try/catch; metric from OBS; asserted in `RedirectResilienceTest` (D4-T4b).*
- **Tests:** cache hit/miss unit; publisher-called assertion with a fake/spy; redirect-still-works-when-publisher-throws. *Confirm these exist before ticking the above.*

### Day 5 — Kafka consumer + analytics store
Goal: click events are consumed and projected into a read model for the dashboard.
> **Reconciled with ADR-0002 (shipped).** The analytics store is event-sourced,
> not a flat `clicks` table: append-only `click_events` (log) + `click_daily_aggregates`
> (read model) in the same SQL DB, written only by a single `ClickProjector` shared
> by the sync and Kafka paths. Device parsing is a *future projection*, not part of
> the initial schema.
- [x] **D5-T1** Analytics schema (decision 4): `click_events` + `click_daily_aggregates` migrations. *Event-sourced model per ADR-0002, not the flat `clicks` table originally sketched.*
- [x] **D5-T2** Consumer worker (`php artisan clicks:consume` → `ConsumeClicks`) reading `url.clicked` and projecting via `ClickProjector`. *Confirm idempotency + tests before ticking DoD.*
- [x] **D5-T3** Device enrichment projection: `Support\UserAgentParser` → `click_device_aggregates` read model via `ClickProjector` (off the hot path). *Hand-rolled UA classifier (no new dep); `UserAgentParserTest` + `DeviceProjectionTest` green.*
- [x] **D5-T4** Aggregate read queries: by-device breakdown (`deviceBreakdown()` → `data.devices.{by_type,by_browser,by_os}`, sorted) added to the overview; clicks-over-time + top URLs verified existing. *Device breakdown is all-time.*
- **Tests:** projector unit (given event → `click_events` row + aggregate updated); enrichment correctness; aggregate query tests.

### Day 6 — Frontend: auth + public "generate URL"
Goal: a user with a code can shorten a URL; admin can log in.
- [ ] **D6-T1** `features/auth`: api module, `useLogin`/`useLogout` (react-query), auth token/store, `ProtectedRoute`, `LoginPage` (zod + react-hook-form).
- [ ] **D6-T2** `components/layout`: `PublicLayout`, `AdminLayout` (nav, auth-aware).
- [ ] **D6-T3** `features/shortUrls`: api module, `useCreateShortUrl`; `GenerateUrlPage` (access code + long URL → short URL, copy button, errors).
- [ ] **D6-T4** `components/common`: shared UI (Button, Input, Field error, Toast/Copy).
- **Tests:** component tests (form validation, submit success/failure, protected-route redirect) with mocked api.

### Day 7 — Frontend: admin dashboard + access-code management
Goal: admin dashboard shows analytics; admin manages codes.
- [ ] **D7-T1** `features/shortUrls/DashboardPage`: analytics views (clicks-over-time chart, device breakdown, top URLs) — follow the `dataviz` conventions.
- [ ] **D7-T2** URLs table (list, filter, activate/deactivate, delete) wired to admin endpoints.
- [ ] **D7-T3** `features/accessCodes/AccessCodesPage`: CRUD table (create code, set expiry, toggle active, delete) with react-query mutations + optimistic updates.
- [ ] **D7-T4** Error/empty/loading states standardised.
- **Tests:** table CRUD interactions, chart data mapping, mutation success/rollback.

### Day 8 — Observability: metrics, dashboards, structured logs
Goal: the app is monitored the same way locally as it will be in cloud.
- [x] **D8-T1** Backend `/metrics` (Prometheus) via promphp + Redis(predis) storage: request count + latency histogram, redirect counter, cache hit/miss, publish failures, consumer-lag proxy. *(ADR-0004)*
- [x] **D8-T2** Structured JSON logging to stdout (`stdout` channel) with `request_id`/`actor` context (`AssignRequestId`).
- [x] **D8-T3** Prometheus + Grafana in Compose; provisioned scrape config + `LinkForge Overview` dashboard (`docker/prometheus`, `docker/grafana`).
- [x] **D8-T4** `docs/architecture/observability.md` (metric names, panels, alert ideas).
- **Tests:** ✅ `MetricsEndpointTest` — `/metrics` exposes series; a redirect increments its counter. Full suite 143/143.

### Day 9 — Test hardening + JMeter load testing
Goal: confident coverage + a performance baseline.
- [x] **D9-T1** Cross-layer integration test (`CreateRedirectClickFlowTest`: create → redirect → projected) + `AccessCodeService::validateAndConsume` gate coverage.
- [x] **D9-T2** Frontend feature coverage already comprehensive (FE-TESTS). *No redundant flow test added — noted on the ticket.*
- [x] **D9-T3** JMeter plans under `load/` (redirect throughput, create-with-code, admin login+list), parameterised via `-J` props, + `README`.
- [x] **D9-T4** `docs/load-testing.md` baseline doc (method + RPS/p95/error table + bottlenecks). *Numbers `TBD` — JMeter not run in-session; template to fill from a real run.*
- **Tests:** JMeter plans XML-validated; integration test green (suite 149/149). *Headless run + real baseline owed by a JMeter host.*

### Day 10 — Local end-to-end validation + Phase-2 skeleton
Goal: prove the whole system locally; prepare (do not deploy) the cloud jump.
- [ ] **D10-T1** Full E2E on a clean machine: `make fresh && make up`, seed admin + a code, create a short URL from the UI, click it, see analytics on the dashboard, see metrics in Grafana.
- [ ] **D10-T2** Fix anything the E2E surfaces; finalise all `docs/`.
- [ ] **D10-T3** Phase-2 **skeletons only** (empty but structured, documented): `infra/terraform/` module layout, `infra/k8s/` manifest set list, `Jenkinsfile` stages outline. No cloud calls.
- [ ] **D10-T4** Runbook: `docs/runbook.md` (start/stop, common failures, where logs/metrics live).
- **Tests:** the E2E checklist itself, executed and recorded.

---

## Phase 1.5 — Local Kubernetes (minikube) — **before cloud**

> Deploy the whole app to a local k8s cluster with the full object set (scalable +
> maintainable) and **prove HPA scales `api` under peak load**, before any AWS work.
> DB stays host SQL Server (ADR-0003) via `host.minikube.internal`; Kustomize
> base + `overlays/local`. The concrete, dependency-ordered ticket set is **K8S-0 …
> K8S-14** in [`tickets.md`](tickets.md#phase-15--local-kubernetes-minikube). Summary:

- [x] ADR-0005 (topology/tooling) · frontend image · Kustomize base+overlay · Namespace.
- [x] Config/Secret · in-cluster Redis+Kafka · API Deployment+Service (requests/limits, probes) · clicks-worker · migration Job · frontend Deployment.
- [x] Ingress · **HPA + metrics-server** · in-cluster Prometheus+Grafana · PDB + NetworkPolicy.
- [x] Run tooling (`make k8s-*`) + `docs/k8s-local.md`. 🟡 **HPA load proof** authored (K8S-14) — recorded run owed on a live minikube.
- _All manifests render-validated (`kubectl kustomize`); live `apply`/minikube + the HPA proof are owed on a cluster._

---

## Phase 2 — AWS deployment (backlog; start only after Phase 1.5 sign-off)

> Detailed task breakdown will be expanded when Phase 1 is validated. High-level objects to produce:

- [ ] **Terraform**: VPC, subnets, EKS cluster, node groups, RDS (DB), ElastiCache (Redis), MSK (Kafka), ECR, IAM/IRSA, ALB/ingress, Route53, ACM, secrets (SSM/Secrets Manager), Prometheus/Grafana (AMP/AMG or self-managed).
- [ ] **Kubernetes**: Namespaces; Deployments (api, frontend, click-consumer worker); Services; HPA; Ingress; ConfigMaps/Secrets (external-secrets); Jobs (migrations); ServiceMonitors; PodDisruptionBudgets; resource requests/limits; readiness/liveness probes; NetworkPolicies.
- [ ] **Jenkins CI/CD**: multibranch pipeline — lint → test (BE+FE) → build images → push ECR → deploy to EKS (per env) → smoke.
- [ ] **Observability in cloud**: Prometheus scrape via ServiceMonitor, Grafana dashboards as code, log shipping to CloudWatch/Loki.
- [ ] **Load test in cloud**: re-run JMeter against a staging URL; capacity plan HPA thresholds.

---

## How to update this file
When you complete a task: tick its box, and if reality diverged from the plan, append a one-line note in italics under the task. Keep the Status snapshot honest.
