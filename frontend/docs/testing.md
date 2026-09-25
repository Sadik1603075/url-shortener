# Frontend Testing Strategy

> **Every roadmap task ships its own tests.** No batching to the end.

## Tooling (added D1-T4)
- **Vitest** (Vite-native runner) + **@testing-library/react** + **@testing-library/user-event** + **jsdom**.
- Mock HTTP by mocking the feature `api.js` module, or use **MSW** for realistic request interception (preferred for flows).
- `npm test` runs the suite; `npm test -- --watch` for local TDD.

## Philosophy
Test **behaviour the user experiences**, not implementation details. Query by role/label/text. Avoid asserting on internal state or class names.

## What to cover per feature

### auth (D6)
- Login form: required-field validation (zod), invalid-credentials shows server error, success stores token + navigates to `/admin`.
- `ProtectedRoute`: no token → redirects to `/login`; with token → renders child.

### shortUrls (D6–D7)
- `GenerateUrlPage`: missing/short access code or invalid URL → validation errors; success shows short URL + copy works; 422 from server maps onto fields.
- `DashboardPage`: chart data mapping (given API rows → expected series); loading/empty/error states.
- URLs table: list renders, deactivate/delete triggers mutation + invalidation.

### accessCodes (D7)
- Table CRUD: create shows new row; toggle active; delete removes row; optimistic update rolls back on failure.

## Conventions
- Co-locate tests next to code (`X.test.jsx`) or under `__tests__/` per feature — pick one at D1-T4 and keep consistent.
- Wrap components under test in the same providers they need (react-query client, router) via a `renderWithProviders` helper in a test util.
- One behaviour per test; Arrange/Act/Assert; `user-event` for interactions (not `fireEvent` where avoidable).
- No real network; deterministic tests.

## CI (Phase 2)
`npm ci` → `npm run lint` → `npm test` must pass before merge (Jenkins stage). Build (`npm run build`) must succeed.
