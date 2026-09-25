# Backend Architecture

> Layered architecture + request lifecycle for the Laravel API. Reflects **current** implemented state; "Planned" marks future work tied to roadmap tasks.

## Layers & responsibilities

| Layer | Location | Responsibility | Must NOT |
|---|---|---|---|
| Route | `routes/api.php`, `routes/web.php` | Map URL → controller; apply middleware | contain logic |
| Middleware | `app/Http/Middleware` | Cross-cutting gates (`admin`) | business rules |
| FormRequest | `app/Http/Requests` | Validation + `authorize()` | persistence |
| Controller | `app/Http/Controllers` | Orchestrate one service call, return Resource | query DB, branch on business rules |
| Service | `app/Services/<Domain>` | Business logic, transactions, coordinate repos/cache/events | read `$request`, shape output |
| Repository | `app/Repositories` (Contracts + Eloquent) | All persistence; return Models/paginators | business rules |
| Model | `app/Models` | fillable, casts, relations, tiny invariants | fat logic |
| Resource | `app/Http/Resources` | Output shaping | business rules |
| DTO | `app/DTOs` | Immutable data across boundaries | behaviour |
| Support | `app/Support` | Pure helpers (Base62, ShortCodeGenerator) | I/O |

## Dependency injection / bindings
- `RepositoryServiceProvider`: `ShortUrlRepositoryInterface → EloquentShortUrlRepository`, `AccessCodeRepositoryInterface → EloquentAccessCodeRepository`.
- `AppServiceProvider`: `ShortUrlCacheInterface → RedisShortUrlCache`; registers `admin` middleware alias.
- **Planned:** `ClickEventPublisherInterface → KafkaClickProducer` (D4).
- Both providers registered in `bootstrap/providers.php`.

## Request lifecycles

### Create short URL — `POST /api/v1/urls`
`StoreShortUrlRequest` → `ShortUrlController@store` → `AccessCodeService.validateAndConsume(code)` (owner id, or `ValidationException`) → `ShortUrlService.create(userId, CreateShortUrlData)` → `ShortCodeGenerator.generate()` + `ShortUrlRepository.create()` inside `DB::transaction` → `201 { data: ShortUrlResource }`.

### Redirect — `GET /{code}` (web)
`RedirectController` → `UrlRedirectService.redirect(code)`:
1. Redis `get(code)` → hit ⇒ `302 away`.
2. miss ⇒ `repository.findByShortCode` → invalid/expired ⇒ `404`.
3. cache `put(code, longUrl, ttl)`; `incrementClickCount`.
4. **Planned (D4):** emit `UrlClicked` fire-and-forget.
5. `302 away`.

### Admin URL management — `/api/v1/admin/urls*`
Behind `auth:sanctum` + `admin`. `index/show/update/destroy` → `ShortUrlService` (paginate/findOrFail/update/delete). Update & delete **bust the Redis cache**.

### Auth — `/api/v1/auth/*`
`login` → `AuthenticationService.login()` (verify credentials + admin role) → Sanctum token. `logout` → revoke current token.

## Planned additions (by roadmap day)
- **D2:** `Support/Base62` + counter/obfuscation-backed `ShortCodeGenerator` (ADR-0001).
- **D3:** Access-code admin CRUD (`AccessCodeController`, service+repo methods, `AccessCodeResource`).
- **D4:** `Events/UrlClicked`, `Messaging/` Kafka producer behind interface; Redis on redirect confirmed.
- **D5:** `clicks:consume` worker, `clicks` table + `ClickRepository`, device enrichment, aggregate queries.
- **D8:** `/metrics` endpoint + structured logging.

## Error handling
JSON rendering for `api/*` is enabled in `bootstrap/app.php`. Validation → 422; auth → 401; forbidden → 403; not found → 404. Services throw `ValidationException`/`abort()`; controllers don't catch routine errors.
