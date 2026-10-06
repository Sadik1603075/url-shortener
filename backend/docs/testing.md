# Backend Testing Strategy

> **Every roadmap task ships its own tests in the same change.** No batching to the end.

## Tooling
- **PHPUnit 12** (already a dev dependency). Run via `composer test` — which first runs `config:clear`, `cache:clear`, `route:clear`, `view:clear`, `optimize:clear` (so no stale cached config/routes leak into a run) and then `php artisan test`.
- **Feature tests use `DatabaseTransactions`** (each test wrapped in a transaction and rolled back), running against a **dedicated SQL Server database `url_shortener_test`** — real-engine parity with dev/prod (ADR-0003), not sqlite. `phpunit.xml` sets `DB_CONNECTION=sqlsrv` + `DB_DATABASE=url_shortener_test`; host/creds come from `.env`.
- **First-time setup of the test DB** (once per machine — `DatabaseTransactions` does *not* migrate, it only wraps): create the database, then migrate it:
  - `CREATE DATABASE [url_shortener_test]` on the SQL Server instance (e.g. via SSMS/sqlcmd, or a one-off PDO script against `master`).
  - `DB_DATABASE=url_shortener_test php artisan migrate --force` (re-run after adding migrations).
- Mockery for test doubles of interfaces. Unit tests take **no DB** (mock the repo/cache/generator; stub `DB::transaction`).
- Faker + model factories for fixtures.

## Test pyramid

| Level | What | Where | Doubles |
|---|---|---|---|
| **Unit** | Services, Support (Base62, ShortCodeGenerator), DTOs | `tests/Unit` | mock repos/cache/publisher; no DB |
| **Feature** | HTTP endpoints end-to-end through the container | `tests/Feature` | SQL Server test DB (`DatabaseTransactions`), fake Kafka publisher |
| **Integration** | Cross-layer flows (create → redirect → click persisted) — `tests/Feature/Integration/CreateRedirectClickFlowTest.php` | `tests/Feature` | SQL Server test DB + in-memory cache (sync driver) |

Aim: services and Support near-100%; every endpoint has at least happy-path + auth + validation-failure tests.

## What each layer must test

### Services (unit)
- `ShortUrlService` (`tests/Unit/ShortUrl/ShortUrlServiceTest.php`, ✅): create calls generator + repo; update sends only non-null attrs (keeps `is_active=false`) + busts cache; delete busts cache then deletes; findOrFail 404s. Repo/generator/cache are Mockery mocks; `DB::transaction` is stubbed (`DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb())`) so it stays a true unit test. **Don't** override `tearDown()` with a manual `Mockery::close()` — Laravel's base `TestCase` already closes Mockery *and* counts its expectations as assertions; closing early yields "risky, no assertions".
- `AccessCodeService` (`tests/Unit/AccessCode/AccessCodeServiceTest.php`, ✅): the gate — `validateAndConsume` valid code → returns owner id + marks used; invalid/expired → `ValidationException` (fail-closed, not marked used). Admin CRUD (`tests/Unit/AccessCode/AccessCodeServiceTest.php`, ✅): generate builds an active code (mocked generator + repo `create`); paginate delegates; update sends only provided fields, clears `expires_at` when it's explicitly provided as null, and is a no-op when nothing changed; delete delegates; findOrFail throws `ModelNotFoundException` when missing.
- `AuthenticationService`: bad creds → exception; non-admin → exception; success → token.
- `UrlRedirectService` (`tests/Unit/ShortUrl/UrlRedirectServiceTest.php`, ✅): cache hit → redirect without touching the repo; miss → load from repo + `put` (TTL `null` when no expiry, a positive TTL ≤ remaining when it expires); unknown/expired/inactive → 404 (no emit/cache write); **publisher throws ⇒ still redirects + logs `click.publish.failed`** (resilience). Cache/repo/publisher are strict Mockery mocks.
- `AnalyticsService` (`tests/Unit/Analytics/AnalyticsServiceTest.php`, ✅): `overview()` assembles totals, zero-filled continuous day series (newest last), mapped `top_urls`, and the **`devices` breakdown** (by_type/by_browser/by_os, each sorted by clicks desc) from mocked repos; time frozen with `travelTo` for deterministic dates.

### Support (unit)
- `Base62` (`tests/Unit/Support/Base62Test.php`, ✅): encode/decode round-trip (property), known vectors, leading-zero padding decodes cleanly, rejects negatives/illegal chars.
- `ShortCodeGenerator` (`tests/Unit/Support/ShortCodeGeneratorTest.php`, ✅): uniqueness over N, alphabet-only + min-length, non-enumerability (consecutive ids → non-sorted/scattered codes), **no collision lookup** (only dependency is a mocked `ShortCodeCounterInterface`), and `decode()` round-trips back to the counter id (Feistel reversibility). Set `config('shortcode.key')`/`min_length` via `Config::set` in the test.
- `AccessCodeGenerator` (`tests/Unit/AccessCode/AccessCodeGeneratorTest.php`, ✅) — note: access codes still use random-with-retry (human-readable `USR-…`); only short codes moved to the counter+Feistel scheme (ADR-0001). `USR-XXXX-XXXX` format; **retries on collision** — `findByCode` stubbed to collide once then clear, with `->twice()` proving the uniqueness loop actually runs (fails if the collision check is removed).

### Endpoints (feature)
- **Auth (`tests/Feature/Auth/AuthTest.php`, ✅ done):** admin login → token + `UserResource` (+ token persisted); wrong password / unknown email / non-admin → 422; missing fields → 422; `/admin/*` unauthenticated → 401, authenticated non-admin → 403; logout revokes the current token (reused token → 401). **Note:** login is *admin-only* — a non-admin with correct credentials is rejected at login (422), not merely blocked at `/admin/*`. (Reusing a real token across two requests in one test needs `$this->app['auth']->forgetGuards()` to defeat the sanctum guard's per-instance user memoization.)
- **Short URLs (`tests/Feature/ShortUrl/`, ✅ done):**
  - `ShortUrlCreateTest`: `POST /api/v1/urls` happy path → 201 + `ShortUrlResource`, row persisted & owned by the code's user, code consumed (`last_used_at`); **fail-closed** on missing / unknown / expired / inactive access code → 422 with no row created; bad/missing URL → 422.
  - `AdminShortUrlTest`: every admin route (`index/show/update/destroy`) → 401 unauthenticated / 403 non-admin (data-provider over the route table); index paginated; show/update/destroy 404 on missing id; **update & delete bust the cache**; `PATCH expires_at=null` **un-expires** the URL (regression guard for the DTO clear-vs-omit fix) — asserted by binding a `Mockery::spy(ShortUrlCacheInterface::class)` via `$this->app->instance(...)` and `shouldHaveReceived('forget')->with($code)` (avoids a live Redis). PHPUnit 12 needs `#[DataProvider]`, not the `@dataProvider` docblock.
- **Access codes (`tests/Feature/AccessCode/AccessCodeControllerTest.php`, ✅ done):** all six endpoints (`index/store/show/update/destroy/send`) → 401 unauthenticated / 403 non-admin (data-provider); create generates a unique `USR-XXXX-XXXX` code owned by the admin (regex-asserted), duplicate email → 422, missing email → 422; index paginated; show/update/destroy/send 404 on missing id; update deactivates + sets expiry (and `PATCH expires_at=null` un-expires — same clear-vs-omit fix as ShortUrl); delete removes; `POST /{id}/send` dispatches `AccessCodeMail` (`Mail::fake()` + `assertSent` matching the model & recipient), and a 404 send sends nothing.
- **Analytics (`tests/Feature/Analytics/AnalyticsOverviewTest.php`, ✅ done):** `GET /admin/analytics/overview` → 401 unauth / 403 non-admin; 200 returns the `totals`/`urls_created`/`clicks_series`/`top_urls`/`devices` shape with correct values from seeded short URLs, access codes, `click_daily_aggregates` and `click_device_aggregates`; `top_urls` ordered by clicks desc; the **device breakdown** is grouped-summed across buckets and sorted (D5-T4); the `days` query param is clamped to [7, 90].
- Redirect: valid 302; unknown 404; expired 404 (feature: `RedirectResilienceTest`; unit: `UrlRedirectServiceTest`). **Kafka-down resilience (D4-T4b):** publisher throws ⇒ still 302, `click.publish.failed` logged (`Log::spy`), and `click_publish_failures_total` increments (asserted via `/metrics`).
- **Observability (`tests/Feature/Observability/MetricsEndpointTest.php`, ✅ OBS):** `GET /metrics` serves the Prometheus exposition (`linkforge_http_requests_total` present after a warm request); a redirect increments `linkforge_redirect_total` and records a cache miss. Tests run with in-memory metric storage (`METRICS_STORAGE=memory`); the `Metrics` recorder swallows errors so it never breaks a request.

Factories added for tests: `ShortUrlFactory`, `AccessCodeFactory` (with `expired()` / `inactive()` states that `findValidCode` must reject).

### Projection / Consumer (D5)
- `ClickProjector` (`tests/Feature/Analytics/ClickProjectorTest.php`): a `UrlClicked` fact → `click_events` row + daily aggregate + per-URL counter.
- **Device enrichment (`tests/Feature/Analytics/DeviceProjectionTest.php`, ✅ D5-T3):** projecting events with known UAs writes correct `click_device_aggregates` rows (same browser/os/device_type bucket is reused and incremented; distinct UAs → distinct buckets); a null/unknown UA degrades to an `Unknown`/`unknown` row without crashing. UA classification unit-tested in `tests/Unit/Support/UserAgentParserTest.php`.

## Conventions
- One behaviour per test method; Arrange/Act/Assert; descriptive names (`it_rejects_expired_access_code`).
- No network in unit tests. Fake the Kafka publisher via the interface binding.
- Feature tests assert JSON structure via `assertJsonPath`/`assertJsonStructure`, and status codes explicitly.

## CI (Phase 2)
`composer install` → `pint --test` → `composer test` must pass before merge (Jenkins stage).
