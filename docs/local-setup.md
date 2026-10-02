# Running LinkForge locally

The one-stop guide to bring the whole system up on a dev machine. If you only read
one doc, read this one. Architecture lives in [`AGENTS.md`](../AGENTS.md) and the
[ADRs](adr/); this is the runbook.

---

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 | Host dev uses XAMPP PHP 8.3. Needs `pdo_sqlsrv` (installed); **no `phpredis`/`rdkafka` needed on the host** (we use the pure-PHP `predis`, and the `sync` click driver). |
| Composer | 2.x | |
| Node.js + npm | 20+ | Frontend (Vite + React). |
| Docker Desktop | Compose v2 (`docker compose`) | Runs Kafka, Redis, the API, the worker, Prometheus, Grafana. |
| GNU make | optional | For the `make` shortcuts. On Windows: `choco install make`, or run the raw commands / use Git Bash/WSL. |
| **SQL Server** | 2019+ on the host, port **1433** | The database (ADR-0003). Not containerised — it must be running before migrations. ODBC Driver 18 required for `pdo_sqlsrv`. |

---

## 2. Databases (host SQL Server)

LinkForge uses **two** databases on the host SQL Server:

| Database | Purpose |
|---|---|
| `url_shortener` | dev / runtime |
| `url_shortener_test` | the PHPUnit suite (`DatabaseTransactions`) — see [testing.md](../backend/docs/testing.md) |

Create both once (SSMS, `sqlcmd`, or any client):

```sql
CREATE DATABASE url_shortener;
CREATE DATABASE url_shortener_test;
```

Credentials go in `backend/.env` (`DB_USERNAME` / `DB_PASSWORD`, etc.).

---

## 3. First-time backend setup

```bash
cd backend
composer install
cp .env.example .env            # then edit DB_* to match your SQL Server
php artisan key:generate
php artisan migrate --force                              # dev DB (url_shortener)
DB_DATABASE=url_shortener_test php artisan migrate --force   # test DB (once per machine)
```

> `composer setup` runs install + `.env` copy + key:generate + migrate + the frontend
> build in one shot (see `composer.json`).

### Seed an admin + access code (pick one)

- **Quick dev data:** `php artisan db:seed` → creates an **admin** `dev@example.com` /
  `password` (the `role` column defaults to `admin`) and an access code
  **`DEV-ACCESS-001`** (use it to shorten URLs).
- **Your own admin:** `php artisan app:create-admin-user` (interactive — name, email,
  password + confirmation, admin/super_admin role). **Note:** this creates only the
  admin, *not* an access code — generate one from the admin UI's Access Codes page
  before you can shorten a URL.

---

## 4. First-time frontend setup

```bash
cd frontend
npm install
```

`frontend/.env` points the SPA at the API:

```
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_SHORT_URL_BASE=http://127.0.0.1:8000
```

---

## 5. Run it

### Option A — Full stack in Docker (recommended)

```bash
make up          # or: docker compose up -d --build
make logs SERVICE=clicks-worker   # tail one service
make down
```

This starts everything except the SPA (there is no `frontend` container). Run the UI
on the host:

```bash
cd frontend && npm run dev        # http://localhost:5173
```

### Option B — Host dev (no API container)

Run just the infra you need in Docker, the app on the host:

```bash
docker compose up -d redis        # (+ kafka if testing the kafka driver)
cd backend && php artisan serve    # http://127.0.0.1:8000
cd frontend && npm run dev         # http://localhost:5173
```

On the host keep `CLICK_EVENT_DRIVER=sync` (no Kafka needed — see §7).

---

## 6. Ports

| Service | URL | Notes |
|---|---|---|
| API | http://localhost:8000 | redirects + `/api/v1/*` + `/metrics` |
| Frontend (Vite dev) | http://localhost:5173 | `npm run dev` |
| Redis | localhost:6379 | cache + metrics storage |
| Kafka | localhost:9092 | broker (host clients) |
| Kafka UI | http://localhost:8080 | topic browser |
| Prometheus | http://localhost:9090 | scrapes `api:8000/metrics` |
| Grafana | http://localhost:3000 | admin / admin → "LinkForge Overview" |
| SQL Server | localhost:1433 | host-installed (not in Compose) |

---

## 7. `CLICK_EVENT_DRIVER` (click pipeline) — ADR-0002

The redirect emits a click fire-and-forget; the transport is swappable:

| Value | When | Behaviour |
|---|---|---|
| `sync` | **host dev** (no Kafka) | project the click inline in the request. Default. |
| `kafka` | **Docker** (Compose sets this for `api`/`clicks-worker`) | produce to `url.clicked`; the `clicks:consume` worker projects asynchronously. Needs `ext-rdkafka` (in the image, not on the host). |
| `log` | load tests / analytics off | discard to the log. |

---

## 8. Observability (ADR-0004)

- API metrics: http://localhost:8000/metrics (Prometheus text format).
- Prometheus: http://localhost:9090 · Grafana: http://localhost:3000 (admin/admin) →
  dashboard **LinkForge Overview**.
- In Compose, `METRICS_STORAGE=redis` so the api + worker share one metric store; on
  the host it defaults to in-memory. Details: [observability.md](architecture/observability.md).

---

## 9. Tests

```bash
make test                 # backend + frontend
# or individually:
cd backend  && composer test   # PHPUnit on url_shortener_test (must be migrated — §2/§3)
cd frontend && npm test        # Vitest
```

---

## 10. Reset

```bash
make fresh CONFIRM=1      # DROPS all dev DB tables, wipes container volumes, rebuilds
```

`CONFIRM=1` is required because `fresh` runs `migrate:fresh` on the dev DB, which the
host SQL Server keeps even after `docker compose down -v`.

---

## 11. Troubleshooting (host gotchas)

- **`Class "Redis" not found`** → `phpredis` isn't installed; keep `REDIS_CLIENT=predis`
  in `.env`, then `php artisan config:clear`.
- **`could not find driver (sqlsrv)`** → enable `pdo_sqlsrv`/`sqlsrv` in `php.ini`
  (ODBC Driver 18 installed).
- **Kafka errors on the host** → `ext-rdkafka` only exists in the Docker image; use
  `CLICK_EVENT_DRIVER=sync` for host dev.
- **Config changes not taking effect** → always `php artisan config:clear` after editing `.env`.
- **Tests fail with a DB/connection error** → ensure `url_shortener_test` exists and is
  migrated (§2/§3).

---

## 12. Self-review checklist (a fresh machine, following only this doc)

1. [ ] Prereqs installed; SQL Server reachable on 1433.
2. [ ] `url_shortener` + `url_shortener_test` created; both migrated.
3. [ ] `backend/.env` has correct `DB_*`; `key:generate` done.
4. [ ] `php artisan db:seed` → admin `dev@example.com` / `password` + access code `DEV-ACCESS-001` created.
5. [ ] `make up` (or `docker compose up -d --build`) → api responds at :8000; Grafana at :3000 shows the dashboard.
6. [ ] `cd frontend && npm run dev` → UI at :5173; log in as `dev@example.com`, then shorten a URL with `DEV-ACCESS-001`.
7. [ ] Click the short link → 302 redirect; dashboard analytics + `/metrics` update.
8. [ ] `make test` (or `composer test` + `npm test`) → backend + frontend suites green.
