# Backend API Reference

Base URL (local): `http://127.0.0.1:8000/api/v1`
Auth: Bearer token (Sanctum) for `admin/*`. Public endpoints are code-gated in the body.

> Status legend: ✅ implemented · 🚧 planned (roadmap day noted).

## Auth
| Method | Path | Auth | Body | Response | Status |
|---|---|---|---|---|---|
| POST | `/auth/login` | none | `{email, password}` | `{data:{token, user}}` | ✅ |
| POST | `/auth/logout` | Bearer | — | `{message}` | ✅ |

`login` requires the user to have an admin role, else 422.

## Public — Short URLs
| Method | Path | Auth | Body | Response | Status |
|---|---|---|---|---|---|
| POST | `/urls` | none (code-gated) | `{long_url, access_code, expires_at?}` | `201 {data: ShortUrl}` | ✅ |

Invalid/expired `access_code` → 422. `long_url` must be a valid URL.

## Redirect (web, not under /api)
| Method | Path | Response | Status |
|---|---|---|---|
| GET | `/{code}` | `302` to long URL, or `404` | ✅ |

Cached in Redis; emits `UrlClicked` (🚧 D4).

## Admin — Short URLs (`auth:sanctum` + `admin`)
| Method | Path | Response | Status |
|---|---|---|---|
| GET | `/admin/urls?per_page=` | paginated `ShortUrl` collection | ✅ |
| GET | `/admin/urls/{id}` | `{data: ShortUrl}` (with user) | ✅ |
| PATCH | `/admin/urls/{id}` | `{data: ShortUrl}` (busts cache) | ✅ |
| DELETE | `/admin/urls/{id}` | `204` (busts cache) | ✅ |

## Admin — Access Codes (`auth:sanctum` + `admin`) 🚧 D3
| Method | Path | Body | Response |
|---|---|---|---|
| GET | `/admin/access-codes` | — | paginated collection |
| POST | `/admin/access-codes` | `{user_id?, expires_at?}` | `201 {data: AccessCode}` (code generated server-side) |
| GET | `/admin/access-codes/{id}` | — | `{data: AccessCode}` |
| PATCH | `/admin/access-codes/{id}` | `{is_active?, expires_at?}` | `{data: AccessCode}` |
| DELETE | `/admin/access-codes/{id}` | — | `204` |

## Admin — Analytics
| Method | Path | Response |
|---|---|---|
| GET | `/admin/analytics/overview?days=7..90` | `{data: {totals, urls_created[], clicks_series[], top_urls[], devices}}` |

A single `overview` call feeds the whole dashboard (one fetch). `data.devices` is
`{by_type[], by_browser[], by_os[]}`, each a list of `{<dimension>, clicks}` rows sorted
by clicks desc (all-time; the `days` window governs only `clicks_series`/`urls_created`).
`top_urls` are the 5 most-clicked short URLs. _(Implemented D5-T4; no separate
`/devices` or `/top-urls` routes — they're folded into `overview`.)_

## Resource shapes
**ShortUrl**: `id, short_code, short_url, long_url, is_active, click_count, expires_at, last_accessed_at, created_at, updated_at, user?`
**User**: `id, name, email, role, created_at`
**AccessCode** (🚧): `id, code, is_active, expires_at, last_used_at, user?, created_at`

> Update this file whenever an endpoint is added or its contract changes.
