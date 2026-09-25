# System Overview

> Cross-cutting architecture reference. Read this to understand how the pieces fit before diving into `backend/` or `frontend/`.

## Components

| Component | Tech | Responsibility |
|---|---|---|
| Frontend SPA | React 19, Vite 8, react-query, react-router 7, react-hook-form, zod | Public "generate URL" page (code-gated); admin login, dashboard, access-code + URL management. |
| Backend API | Laravel 13, PHP 8.3, Sanctum | REST API; auth; short-URL create/redirect; access-code CRUD; analytics read models; `/metrics`. |
| Primary DB | SQL DB (see ADR/decision) | Source of truth: users, access_codes, short_urls, clicks. |
| Redis | predis/phpredis | Hot cache: `short_code → long_url` (+TTL from expiry). |
| Kafka | Redpanda local / MSK cloud | Event bus; topic `url.clicked`. Decouples redirect from analytics. |
| Click consumer | Laravel worker command | Consumes `url.clicked`, enriches device info, writes `clicks`. |
| Prometheus | — | Scrapes `/metrics`. |
| Grafana | — | Dashboards over Prometheus. |

## Request flows

### 1. Create short URL (public, code-gated)
```
POST /api/v1/urls { long_url, access_code, expires_at? }
  → StoreShortUrlRequest (validate)
  → AccessCodeService.validateAndConsume(code)  → owner user_id  (reject if invalid/expired)
  → ShortUrlService.create(userId, dto)
      → ShortCodeGenerator.generate()  (base62, non-enumerable — ADR-0001)
      → ShortUrlRepository.create(...)
  → 201 ShortUrlResource
```

### 2. Redirect (hot path)
```
GET /{code}
  → RedirectController → UrlRedirectService.redirect(code)
      → Redis GET short_url:{code}         (hit → 302 away, done)
      → miss → Repository.findByShortCode   (invalid/expired → 404)
             → Redis SET (ttl)              (populate cache)
             → increment click_count        (source-of-truth counter)
      → emit UrlClicked to Kafka            (fire-and-forget; never blocks/500s)
      → 302 away
```
The click *count* on the row is a cheap increment; **rich analytics** (device, browser, referer) come from the Kafka pipeline so the redirect stays fast and resilient.

### 3. Analytics ingestion (async)
```
Kafka topic url.clicked
  → clicks:consume worker
      → parse user-agent → {browser, os, device_type}
      → ClickRepository.record(...)
  → dashboard queries aggregate clicks (over time / by device / top URLs)
```

### 4. Admin
```
POST /api/v1/auth/login → Sanctum token (admin role required)
Bearer token → /api/v1/admin/*  (auth:sanctum + admin middleware)
  - urls: index/show/update/destroy
  - access-codes: index/store/show/update/destroy   (Day 3)
  - analytics: read models                            (Day 5/7)
```

## Data model (target)

- **users** — `id, name, email, password, role` (`user|admin|super_admin`).
- **access_codes** — `id, user_id, code(unique), is_active, expires_at, last_used_at`.
- **short_urls** — `id, user_id, short_code(unique), long_url, is_active, click_count, expires_at, last_accessed_at`.
- **clicks** *(Day 5)* — `id, short_url_id, ip, user_agent, browser, os, device_type, referer, country?, created_at`.

## Cross-cutting concerns

- **Config**: twelve-factor; all hosts/ports/secrets from env. `*.env.example` documents every key.
- **Caching**: only the redirect lookup is cached; cache is busted on URL update/delete.
- **Resilience**: infra dependencies (Kafka especially) degrade gracefully on the hot path.
- **Observability**: `/metrics` (Prometheus) + structured JSON logs to stdout; Grafana dashboards provisioned as code.
- **Security**: private creation (access code required), admin RBAC, non-enumerable codes, no secrets in git.

## Local vs Cloud parity

| Concern | Local (Phase 1) | Cloud (Phase 2) |
|---|---|---|
| Orchestration | Docker Compose | Kubernetes (EKS) |
| DB | container | RDS |
| Redis | container | ElastiCache |
| Kafka | Redpanda container | MSK |
| Metrics | Prometheus container | Prometheus/AMP |
| Dashboards | Grafana container | Grafana/AMG |
| CI/CD | local `make` + tests | Jenkins → ECR → EKS |
| IaC | Compose file | Terraform |

The Compose topology is intentionally a 1:1 conceptual mirror of the K8s topology so Phase 2 is mostly translation, not redesign.
