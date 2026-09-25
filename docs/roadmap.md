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
| **Phase 1** | **Full system running locally + tested + load-tested (Days 1–10)** | **Not started** |
| Phase 2 | AWS: Terraform, EKS/K8s, Jenkins, managed services | Backlog (do not start until Phase 1 signed off) |

### Already in place (Phase 0 — verified)
- Backend layering: `ShortUrlRepository`, `AccessCodeRepository` (+interfaces), `ShortUrlService`, `AuthenticationService`, `AccessCodeService`, `UrlRedirectService`.
- DTOs: `CreateShortUrlData`, `UpdateShortUrlData`, `LoginData`. Enum: `UserRole`.
- HTTP: `Api/V1/AuthController`, `Api/V1/ShortUrlController`, `RedirectController`; FormRequests; `ShortUrlResource`, `UserResource`; `EnsureUserIsAdmin` middleware; `api.php` + `web.php` routes wired.
- Redis cache abstraction (`ShortUrlCacheInterface` → `RedisShortUrlCache`).
- `ShortCodeGenerator` (currently **random** base62 — to be revisited in D2-T1 per ADR-0001).
- Models: `User` (role), `ShortUrl`, `AccessCode` + migrations.
- Frontend scaffold: `app/` (router, providers), `lib/` (apiClient, queryClient), feature folders (empty), `.env`.

### Decisions to confirm before/at the relevant task 🔒
1. **DB engine.** Current `.env` uses **SQL Server (`sqlsrv`)**. For cloud we'd typically use **RDS Postgres/MySQL**. Recommend standardising on **Postgres** for parity with common EKS setups + easy local container. → confirm at **D1-T2**.
2. **Base62 generation strategy.** Random-collision-checked (current) vs counter + reversible obfuscation (Feistel/multiplier). ADR-0001 recommends **counter + keyed obfuscation** for guaranteed uniqueness *and* non-enumerability. → confirm at **D2-T1**.
3. **Kafka distribution.** Confluent images vs Bitnami vs Redpanda for local. Recommend **Redpanda** locally (single binary, Kafka-API compatible, light) and **MSK** in cloud. → confirm at **D4-T1**.
4. **Analytics store.** Same SQL DB (separate schema/tables) vs dedicated store. Recommend **same DB, dedicated `clicks` + aggregate tables** for Phase 1. → confirm at **D5-T1**.

---

## Phase 1 — Local system (Days 1–10)

### Day 1 — Local environment & tooling foundation
Goal: `docker compose up` brings the whole topology online; test runners exist on both sides.
- [ ] **D1-T1** Root `docker-compose.yml` skeleton + `Makefile` (`make up/down/logs/test/fresh`). Services stubbed: `api`, `frontend`, `db`, `redis`. *(kafka/prometheus/grafana added in their days.)*
- [ ] **D1-T2** Choose + wire DB engine (🔒 decision 1). Containerise DB, update `.env.example`, verify `php artisan migrate` against the container.
- [ ] **D1-T3** Backend test harness: confirm PHPUnit config, add `Tests\TestCase` base, a `RefreshDatabase` example test, CI-friendly `composer test`.
- [ ] **D1-T4** Frontend test harness: add **Vitest + React Testing Library + jsdom**, `npm test` script, one smoke test.
- [ ] **D1-T5** `docs/local-setup.md`: how to run everything locally, ports, seeded admin creds.
- **Tests this day:** backend RefreshDatabase smoke; frontend render smoke.

### Day 2 — Core domain: base62 short codes
Goal: correct, non-enumerable short-code generation with full unit coverage.
- [ ] **D2-T1** ADR-0001 confirmed; implement `Support/Base62` codec (encode/decode, tested against known vectors).
- [ ] **D2-T2** Short-code counter strategy (sequence/table) + keyed obfuscation; refactor `ShortCodeGenerator` to use it behind the existing interface (no controller/service signature change).
- [ ] **D2-T3** Collision & length invariants; property tests (encode∘decode round-trip, no code shorter than min length, alphabet-only).
- **Tests:** `Base62Test`, `ShortCodeGeneratorTest` (unit).

### Day 3 — Access-code admin CRUD (backend)
Goal: admin can create/list/update/delete access codes end-to-end.
- [ ] **D3-T1** Extend `AccessCodeRepositoryInterface` + Eloquent impl: `paginate`, `findById`, `create`, `update`, `delete`.
- [ ] **D3-T2** `AccessCodeService`: generate (secure random code), list, update (activate/expire), delete.
- [ ] **D3-T3** `Api/V1/AccessCodeController` (admin-guarded) + FormRequests + `AccessCodeResource`; wire routes under `/admin/access-codes`.
- [ ] **D3-T4** Authorization: ensure all admin routes behind `auth:sanctum` + `admin`.
- **Tests:** feature tests for each endpoint (auth required, admin required, happy path, validation errors); `AccessCodeServiceTest` unit.

### Day 4 — Redis cache + Kafka click event production
Goal: redirects are cached and emit a click event without blocking.
- [ ] **D4-T1** Add Kafka (🔒 decision 3) to Docker Compose + Kafka UI; config keys in `.env.example`.
- [ ] **D4-T2** Switch backend cache path to Redis for redirects; confirm `UrlRedirectService` cache hit/miss + TTL logic; add cache-bust on update/delete (already in `ShortUrlService`).
- [ ] **D4-T3** Define `UrlClicked` domain event + payload DTO (code, timestamp, ip, user-agent, referer). Producer: `KafkaClickProducer` behind an interface; emit from redirect path **fire-and-forget**.
- [ ] **D4-T4** Graceful degradation: if Kafka is down, redirect still succeeds (log + metric, never 500).
- **Tests:** cache hit/miss unit; producer-called assertion with a fake/spy; redirect-still-works-when-producer-throws.

### Day 5 — Kafka consumer + analytics store
Goal: click events are consumed, enriched with device info, and persisted for the dashboard.
- [ ] **D5-T1** Analytics schema (🔒 decision 4): `clicks` table (short_url_id, ip, ua, browser, os, device_type, referer, country?, created_at) + migration.
- [ ] **D5-T2** Consumer worker (`php artisan clicks:consume`) reading `url.clicked`; idempotent handling.
- [ ] **D5-T3** Device enrichment: parse user-agent (browser/os/device). Repository to persist clicks.
- [ ] **D5-T4** Aggregate read models/queries for the dashboard (clicks over time, by device, top URLs).
- **Tests:** consumer handler unit (given event → row written, enrichment correct); aggregate query tests.

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
- [ ] **D8-T1** Backend `/metrics` endpoint (Prometheus format): request count/latency histograms, redirect counter, cache hit ratio, kafka produce failures, consumer lag proxy.
- [ ] **D8-T2** Structured JSON logging to stdout (request id, actor, code).
- [ ] **D8-T3** Add Prometheus + Grafana to Docker Compose; provision scrape config + a LinkForge dashboard (traffic, latency, errors, business KPIs).
- [ ] **D8-T4** `docs/architecture/observability.md`: metric names, dashboard panels, alert ideas.
- **Tests:** `/metrics` exposes expected series; a smoke check that a redirect increments its counter.

### Day 9 — Test hardening + JMeter load testing
Goal: confident coverage + a performance baseline.
- [ ] **D9-T1** Raise backend coverage on services/repos; add cross-layer integration tests (create → redirect → click persisted).
- [ ] **D9-T2** Raise frontend coverage on features; add a happy-path flow test.
- [ ] **D9-T3** JMeter plans under `load/`: (a) redirect throughput, (b) create-url with code, (c) admin login+list. Parameterised, with a `README`.
- [ ] **D9-T4** Run baseline, capture numbers (RPS, p95, error rate) into `docs/load-testing.md`; note bottlenecks.
- **Tests:** the JMeter plans themselves + a documented baseline run.

### Day 10 — Local end-to-end validation + Phase-2 skeleton
Goal: prove the whole system locally; prepare (do not deploy) the cloud jump.
- [ ] **D10-T1** Full E2E on a clean machine: `make fresh && make up`, seed admin + a code, create a short URL from the UI, click it, see analytics on the dashboard, see metrics in Grafana.
- [ ] **D10-T2** Fix anything the E2E surfaces; finalise all `docs/`.
- [ ] **D10-T3** Phase-2 **skeletons only** (empty but structured, documented): `infra/terraform/` module layout, `infra/k8s/` manifest set list, `Jenkinsfile` stages outline. No cloud calls.
- [ ] **D10-T4** Runbook: `docs/runbook.md` (start/stop, common failures, where logs/metrics live).
- **Tests:** the E2E checklist itself, executed and recorded.

---

## Phase 2 — AWS deployment (backlog; start only after Phase 1 sign-off)

> Detailed task breakdown will be expanded when Phase 1 is validated. High-level objects to produce:

- [ ] **Terraform**: VPC, subnets, EKS cluster, node groups, RDS (DB), ElastiCache (Redis), MSK (Kafka), ECR, IAM/IRSA, ALB/ingress, Route53, ACM, secrets (SSM/Secrets Manager), Prometheus/Grafana (AMP/AMG or self-managed).
- [ ] **Kubernetes**: Namespaces; Deployments (api, frontend, click-consumer worker); Services; HPA; Ingress; ConfigMaps/Secrets (external-secrets); Jobs (migrations); ServiceMonitors; PodDisruptionBudgets; resource requests/limits; readiness/liveness probes; NetworkPolicies.
- [ ] **Jenkins CI/CD**: multibranch pipeline — lint → test (BE+FE) → build images → push ECR → deploy to EKS (per env) → smoke.
- [ ] **Observability in cloud**: Prometheus scrape via ServiceMonitor, Grafana dashboards as code, log shipping to CloudWatch/Loki.
- [ ] **Load test in cloud**: re-run JMeter against a staging URL; capacity plan HPA thresholds.

---

## How to update this file
When you complete a task: tick its box, and if reality diverged from the plan, append a one-line note in italics under the task. Keep the Status snapshot honest.
