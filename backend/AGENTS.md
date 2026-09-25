# AGENTS.md — Backend (Laravel API)

> Rules for the Laravel 13 / PHP 8.3 API. Read the **root `AGENTS.md`** first for product context and global principles. This file is backend-specific.
> Deeper references live in `backend/docs/`.

## Stack
- Laravel 13, PHP 8.3, Laravel Sanctum (token auth), Redis (`predis`/`phpredis`), Kafka (Phase 1 Day 4+), PHPUnit 12.
- DB engine per root decision (see `docs/roadmap.md` — SQL Server today; Postgres proposed for parity).

## Layered architecture (strict)

```
HTTP Request
  → Route (routes/api.php · routes/web.php)
  → Middleware (auth:sanctum, admin)
  → FormRequest            ← ALL validation + authorize()
  → Controller (thin)      ← orchestrate only; no business rules, no queries
  → Service                ← business logic, transactions, orchestration of repos/cache/events
  → Repository (interface) ← ALL persistence; returns Models
  → Model                  ← data + trivial domain helpers (isValid()), relations, casts
  → Resource               ← ALL output shaping (never return a raw Model/array)
DTOs cross every service boundary (never pass loose arrays between layers).
```

### Hard rules
- **Controllers never** touch the DB, build queries, or contain `if` business logic. They call one service method and return a Resource/response.
- **Services never** read `$request`. They receive **DTOs** and scalars. They own `DB::transaction`.
- **Repositories never** contain business rules. They are the only place Eloquent query builders live. They implement an **interface** bound in a ServiceProvider.
- **Models** hold fillable/casts/relations and *tiny* invariants (`isValid()`), nothing more.
- **Infrastructure behind interfaces:** cache (`ShortUrlCacheInterface`), event publisher (`ClickEventPublisherInterface`), repositories. Bind in `RepositoryServiceProvider` / `AppServiceProvider`.
- **Enums** for closed sets (`UserRole`). No magic strings.
- **The redirect path is sacred** — Redis + fire-and-forget event only; no synchronous analytics writes; never 500 because Kafka/DB-analytics is down.

## Directory map (`app/`)
```
Cache/            interface + Redis impl for short-url cache
Console/Commands/ artisan commands (CreateAdminUser; clicks:consume [D5])
DTOs/             immutable readonly data carriers (Auth/, ShortUrl/, …)
Enums/            UserRole, …
Events/           domain events (UrlClicked [D4])            ← add
Http/
  Controllers/Api/V1/   versioned API controllers
  Controllers/          RedirectController (web redirect)
  Middleware/           EnsureUserIsAdmin (alias: admin)
  Requests/Api/V1|Admin FormRequests
  Resources/            JsonResources
Messaging/        Kafka producer/consumer + interfaces [D4/D5]  ← add
Models/           Eloquent models
Providers/        AppServiceProvider, RepositoryServiceProvider
Repositories/     Contracts/ + Eloquent/
Services/         business logic, grouped by domain (Auth/, ShortUrl/, AccessCode/)
Support/          pure helpers (ShortCodeGenerator, Base62 [D2])
```

## Conventions (summary — full detail in `docs/conventions.md`)
- PSR-12 + `pint`. Constructor property promotion, `readonly` DTOs, typed everything, return types always.
- One class per file; namespace mirrors path.
- API responses always `{ "data": ... }` (or paginated collection); errors use Laravel's JSON exception rendering (already enabled in `bootstrap/app.php`).
- Migrations reversible; index foreign keys and lookup columns (`short_code`, `code` unique).
- No business logic in migrations/seeders beyond fixtures.

## Testing (hard rule — full detail in `docs/testing.md`)
- **Every task ships tests.** Unit tests for Services/Support/DTOs (no DB, mock repos/cache/publisher). Feature tests for HTTP endpoints (auth gates, validation, happy path) using `RefreshDatabase`.
- Run: `composer test` (wraps `php artisan test`).
- Redirect resilience must be tested: producer throws ⇒ still 302.

## Commands
- `php artisan migrate` / `migrate:fresh --seed`
- `php artisan make:admin` → `CreateAdminUser` (seed an admin)
- `php artisan clicks:consume` → Kafka consumer worker (Day 5+)
- `./vendor/bin/pint` → format

## Config keys you must document in `.env.example` when you add them
`REDIS_*`, `KAFKA_BROKERS`, `KAFKA_CLICK_TOPIC`, `SHORTCODE_KEY`, `SHORTCODE_MIN_LENGTH`, analytics/consumer group ids.

## When adding infra
New infra dependency ⇒ (1) ADR in `docs/adr/`, (2) interface + impl behind it, (3) a local Docker Compose service, (4) graceful degradation on the hot path, (5) a metric.
