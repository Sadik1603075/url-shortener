# Backend Conventions

## Language & style
- PHP 8.3, `declare` strictness via typed signatures; **return types always**, parameter types always.
- PSR-12, enforced by **Laravel Pint** (`./vendor/bin/pint`). Run before commit.
- Constructor property promotion; `private readonly` for injected deps.
- DTOs are `final readonly` with a `fromArray()` where they map request input.
- One class per file; PSR-4 namespace mirrors the path under `app/`.
- No magic strings for closed sets → **Enums** (`UserRole`).

## Naming
- Interfaces: `<Thing>Interface` in `Contracts/`. Impls: `Eloquent<Thing>` / `Redis<Thing>` / `Kafka<Thing>`.
- Services: `<Domain>Service` under `Services/<Domain>/`.
- FormRequests: `<Verb><Noun>Request` (`StoreShortUrlRequest`, `UpdateShortUrlRequest`).
- Resources: `<Noun>Resource`.
- DTOs: `<Verb><Noun>Data` / `<Noun>Data`.

## API shape
- Success: `{"data": <resource|collection>}`. Collections use `Resource::collection($paginator)` (pagination meta preserved).
- Created: `201`. Deleted: `204` (no body). Updated/fetched: `200`.
- Errors: rely on Laravel JSON exception rendering (422 validation, 401/403/404). Do not hand-roll error envelopes.
- Versioning: everything under `/api/v1`. New breaking shapes ⇒ `/api/v2`, not mutation.

## Validation
- All input validation in FormRequests. Business validation (e.g. "code expired") in services via `ValidationException::withMessages`.
- `authorize()` returns true only when route middleware already guards it; otherwise implement the check.

## Persistence
- Repositories are the only place with Eloquent query builders.
- Migrations: reversible `up`/`down`; unique indexes on `short_code`, `access_codes.code`; composite indexes for common filters (`user_id,is_active`), index `expires_at`.
- Use `DB::transaction` in services for multi-write operations.
- Never mass-assign unvetted input; `$fillable` is explicit.

## Caching
- Only cache what the redirect needs (`short_code → long_url`) with a TTL derived from `expires_at`.
- Any mutation to a short URL (update/delete/deactivate) **must** call `cache->forget(code)`.

## Events (D4+)
- Emit domain events for facts; keep producers behind interfaces; fire-and-forget on the hot path.
- Event payloads are **versioned contracts** — additive changes only.

## Logging & config
- Structured logs (D8): include request id, actor id/role, short_code where relevant. No PII beyond what analytics requires; never log secrets or tokens.
- All env-specific values from config/env; add every new key to `.env.example` with a comment.

## Commits
- Scope to one roadmap task; subject `type(scope): summary [D#-T#]`.
- Never commit `.env`, `vendor/`, tokens, or the `SHORTCODE_KEY`.
