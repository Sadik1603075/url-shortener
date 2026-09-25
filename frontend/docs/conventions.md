# Frontend Conventions

## Components
- Function components + hooks only. No class components.
- One component per file. Default export the component; named exports for helpers.
- Pages compose hooks + `components/common`; keep them thin. Extract a `useX` hook once logic grows past rendering.
- `components/common` are **presentational and reusable** — no data fetching, no feature knowledge.

## Feature slices
- Each feature folder contains: page component(s), `api.js` (all endpoint calls for the feature via `apiClient`), react-query hooks (`useX`), and a zod `schema.js`.
- **No cross-feature imports of internals.** Share only via `components/common` and `lib/`.

## Server state (react-query)
- All server interaction goes through hooks. No `axios`/`fetch` directly in components.
- Query keys are arrays, namespaced by feature/entity/params: `['accessCodes','list']`, `['shortUrls','detail',id]`.
- Mutations invalidate the queries they affect; use optimistic updates for snappy CRUD tables where safe.

## Forms & validation
- react-hook-form + `zodResolver(schema)`. Schemas live in the feature's `schema.js`.
- Display client (zod) and server (422) errors uniformly via a shared `Field`/error component.

## HTTP / auth
- `apiClient` is the only axios instance. It attaches `Authorization: Bearer <auth_token>` and clears token on 401.
- Never read `VITE_*` outside a small config/util; centralise env access.

## Styling
- CSS in `styles/` (`index.css`, `theme.css`). Keep a single source of theme tokens; reuse them.
- Charts (D7): follow the `dataviz` skill — one consistent palette, light/dark safe, accessible.

## Quality
- ESLint clean (`npm run lint`): no unused vars, respect `react-hooks/exhaustive-deps`.
- Handle loading / empty / error for every async view.
- Accessible: labels tied to inputs, buttons are `<button>`, keyboard-navigable.

## Testing
- Vitest + React Testing Library. Test behaviour, not implementation. Mock the feature `api.js` (or MSW). Every task ships tests (`npm test`).

## Commits
- Scope to one roadmap task; subject `type(scope): summary [D#-T#]`. Never commit `.env`.
