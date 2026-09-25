# Backend Testing Strategy

> **Every roadmap task ships its own tests in the same change.** No batching to the end.

## Tooling
- **PHPUnit 12** (already a dev dependency). Run via `composer test` (`php artisan test`).
- `RefreshDatabase` for feature tests; a dedicated test DB (sqlite in-memory is fine for CI speed, or the containerised engine for parity — decide at D1-T3).
- Mockery for test doubles of interfaces.
- Faker + model factories for fixtures.

## Test pyramid

| Level | What | Where | Doubles |
|---|---|---|---|
| **Unit** | Services, Support (Base62, ShortCodeGenerator), DTOs | `tests/Unit` | mock repos/cache/publisher; no DB |
| **Feature** | HTTP endpoints end-to-end through the container | `tests/Feature` | real DB (RefreshDatabase), fake Kafka publisher |
| **Integration** | Cross-layer flows (create → redirect → click persisted) | `tests/Feature` | real DB + fake/embedded bus |

Aim: services and Support near-100%; every endpoint has at least happy-path + auth + validation-failure tests.

## What each layer must test

### Services (unit)
- `ShortUrlService`: create calls generator + repo in a transaction; update/delete bust cache; findOrFail 404s.
- `AccessCodeService`: valid code → returns owner id + marks used; invalid/expired → `ValidationException`.
- `AuthenticationService`: bad creds → exception; non-admin → exception; success → token.
- `UrlRedirectService`: cache hit path; miss populates cache + increments; invalid → 404; **producer throws ⇒ still 302** (resilience).

### Support (unit)
- `Base62`: encode/decode round-trip (property), known vectors, alphabet-only, min-length padding.
- `ShortCodeGenerator`: uniqueness, non-enumerability sanity (sequential ids → scattered codes).

### Endpoints (feature)
- Public create: happy path 201; missing/invalid code 422; bad URL 422.
- Redirect: valid 302; unknown 404; expired 404.
- Admin: unauthenticated 401; non-admin 403; index pagination; update busts cache; delete 204.

### Consumer (D5)
- Given a `url.clicked` event → a `clicks` row with correct enrichment; idempotent on reprocess.

## Conventions
- One behaviour per test method; Arrange/Act/Assert; descriptive names (`it_rejects_expired_access_code`).
- No network in unit tests. Fake the Kafka publisher via the interface binding.
- Feature tests assert JSON structure via `assertJsonPath`/`assertJsonStructure`, and status codes explicitly.

## CI (Phase 2)
`composer install` → `pint --test` → `composer test` must pass before merge (Jenkins stage).
