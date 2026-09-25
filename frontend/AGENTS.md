# AGENTS.md — Frontend (React SPA)

> Rules for the React 19 / Vite 8 SPA ("LinkForge"). Read the **root `AGENTS.md`** first. Deeper references in `frontend/docs/`.

## Stack
- React 19, Vite 8, React Router 7 (`createBrowserRouter`).
- **Server state:** TanStack Query (react-query). **Forms:** react-hook-form + zod (`@hookform/resolvers`).
- HTTP: axios (`src/lib/apiClient.js`, Bearer from `localStorage`, 401 → clear token).
- Icons: lucide-react. Charts (D7): follow the `dataviz` skill conventions.
- **Tests (added D1-T4):** Vitest + React Testing Library + jsdom.

## Architecture — feature-sliced

```
src/
  app/            router.jsx, providers.jsx, App.jsx  (composition root)
  lib/            apiClient.js, queryClient.js, utils.js  (framework glue)
  components/
    common/       reusable presentational UI (Button, Input, Field, Toast…)
    layout/       PublicLayout, AdminLayout
  features/
    auth/         LoginPage, ProtectedRoute, api.js, hooks (useLogin…), schema
    shortUrls/    GenerateUrlPage, DashboardPage, api.js, hooks, schema
    accessCodes/  AccessCodesPage, api.js, hooks, schema
  styles/         index.css, theme.css
```

### Rules
- **A feature owns its slice:** page components, its `api.js` (endpoint calls via `apiClient`), its react-query hooks (`useX` queries/mutations), and its zod schemas. Features do not import each other's internals — share via `components/common` or `lib`.
- **Server state lives in react-query**, not `useState`/context. Keep query keys centralised per feature.
- **No business logic in components.** Data fetching/mutations → hooks; validation → zod schemas; formatting → `lib/utils`.
- **Forms:** react-hook-form + `zodResolver`. Show field errors from the schema and server (422) uniformly.
- **Auth:** token in `localStorage` (`auth_token`), attached by `apiClient` interceptor; `ProtectedRoute` guards `/admin`. On 401 the interceptor clears token — route should redirect to `/login`.
- **Presentational vs container:** `components/common` are dumb/reusable; pages compose hooks + common components.
- **Accessibility & states:** every async view handles loading / empty / error explicitly.

## Environment
`.env` keys (Vite `import.meta.env`): `VITE_API_BASE_URL`, `VITE_APP_NAME`, `VITE_SHORT_URL_BASE`. Document new keys in `.env.example`.

## Testing (hard rule — see `docs/testing.md`)
- **Every task ships component/hook tests.** Test behaviour via React Testing Library (what the user sees/does), mock the api module or use MSW.
- Cover: form validation, submit success/failure, protected-route redirect, table CRUD interactions, chart data mapping.
- Run: `npm test`.

## Conventions (summary — full in `docs/conventions.md`)
- Function components + hooks only. Named exports for utilities, default export for a page/component file.
- Keep components small; extract hooks when logic grows.
- ESLint clean (`npm run lint`). No unused vars, exhaustive-deps respected.
- Query keys: `['feature', 'entity', params]`. Invalidate on relevant mutations.
