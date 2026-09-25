# Frontend Architecture

> Feature-sliced React SPA. Reflects current scaffold + planned features (roadmap D6–D7).

## Composition root (`src/app`)
- `main.jsx` → mounts `<App/>` inside `<Providers/>`.
- `providers.jsx` → wraps the tree in `QueryClientProvider` (react-query). Add more providers here (e.g. router, toast) as needed.
- `router.jsx` → `createBrowserRouter`:
  - `PublicLayout` → `/` = `GenerateUrlPage`
  - `/login` = `LoginPage`
  - `ProtectedRoute` → `/admin` (`AdminLayout`) → `index`/`urls` = `DashboardPage`, `access-codes` = `AccessCodesPage`
  > These pages/layouts are referenced but **not yet implemented** — built in D6–D7.

## Layers
| Layer | Location | Responsibility |
|---|---|---|
| Framework glue | `lib/` | `apiClient` (axios + auth interceptor), `queryClient`, `utils` |
| Routing/providers | `app/` | route tree, global providers |
| Layout | `components/layout` | `PublicLayout`, `AdminLayout` (nav, auth-aware chrome) |
| Common UI | `components/common` | reusable presentational components |
| Feature | `features/<name>` | pages + `api.js` + react-query hooks + zod schema |

## Data flow
```
Component → useX hook (react-query) → feature api.js → apiClient (axios) → backend
                     ↑ cache, loading/error state managed by react-query
Form input → react-hook-form + zodResolver → mutation hook → api.js
Server 422 errors → mapped back onto form fields
```

## State strategy
- **Server state:** react-query only (queries + mutations). Centralise query keys per feature: `['shortUrls','list',params]`, `['accessCodes','list']`, etc. Invalidate on mutations.
- **Auth state:** token in `localStorage` (`auth_token`, `auth_user`); read by `apiClient` interceptor. A thin `useAuth` may expose `isAuthenticated`/user for `ProtectedRoute` and layout.
- **Local UI state:** `useState` for ephemeral component state only.

## Feature blueprints (planned)
- **auth (D6):** `LoginPage` (email/password, zod), `useLogin` (mutation → store token → redirect `/admin`), `useLogout`, `ProtectedRoute` (redirect to `/login` when no token / on 401).
- **shortUrls (D6–D7):** `GenerateUrlPage` (access code + long URL → short URL, copy); `DashboardPage` (analytics charts + URLs table).
- **accessCodes (D7):** `AccessCodesPage` (CRUD table with optimistic mutations).

## Environment
`import.meta.env`: `VITE_API_BASE_URL` (`…/api/v1`), `VITE_APP_NAME` ("LinkForge"), `VITE_SHORT_URL_BASE` (for displaying/copying the short link).
