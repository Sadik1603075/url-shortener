# Frontend Testing Strategy

> **Every roadmap task ships its own tests.** No batching to the end.

## Tooling (landed D1-T4)
- **Vitest 5** (Vite-native runner) + **@testing-library/react 16** + **@testing-library/user-event 14** + **jsdom 30**, with **@testing-library/jest-dom** matchers.
- Config lives in `vite.config.js` `test` block: `environment: 'jsdom'`, `globals: true`, `setupFiles: './src/test/setup.js'`, `css: false` (don't process CSS in tests — faster, and no component here asserts on styles). The setup file registers jest-dom matchers and runs RTL `cleanup()` after each test.
- Mock HTTP by mocking the feature `api.js` module, or use **MSW** for realistic request interception (preferred for flows).
- `npm test` runs the suite once (`vitest run`); `npm run test:watch` for local TDD.
- Smoke test at `src/test/smoke.test.jsx` proves the harness renders a component into jsdom.

## Philosophy
Test **behaviour the user experiences**, not implementation details. Query by role/label/text. Avoid asserting on internal state or class names.

## What to cover per feature (FE-TESTS — ✅ landed)

Shared helper: `src/test/renderWithProviders.jsx` wraps the UI in a fresh `QueryClient` (retries off) + `MemoryRouter`. Mock the feature's `api.js` module (the boundary); the real react-query hooks run against the mock.

**Gotchas worth knowing:**
- **react-query v5 passes a 2nd context arg to the `mutationFn`.** When a mock *is* the mutationFn (e.g. `mutationFn: createShortUrl`), assert `toHaveBeenCalledWith(payload, expect.anything())`. Wrapped fns (`({id,...d}) => updateAccessCode(id, d)`) get exactly their args.
- **`userEvent.setup()` installs its own `navigator.clipboard`.** To assert on a clipboard write, install your stub *after* `setup()` (just before the copy click).

### auth (✅ `LoginPage.test.jsx`, `ProtectedRoute.test.jsx`)
- Login: required fields empty → api not called; success stores token (localStorage) + navigates `/admin`; failure shows the server message. (`useNavigate` mocked; `authStore` is real.)
- `ProtectedRoute`: no token → redirects to `/login`; with token → renders the outlet.

### shortUrls (✅ `GenerateUrlPage.test.jsx`, `DashboardPage.test.jsx`)
- `GenerateUrlPage`: empty/invalid URL → zod field errors + api not called; success shows short URL and copy writes to clipboard; a 422 `errors` payload surfaces the field message.
- `DashboardPage`: loading (`Loading activity…`), error (`Failed to load analytics.`), data mapping (totals, chart, recent links, top URLs), and empty states.

### analytics (✅ `ActivityChart.test.jsx`)
- Chart maps points → polyline, scales the y-axis to the max count, labels the x-axis; empty data renders no line and doesn't crash.

### accessCodes (✅ `AccessCodesPage.test.jsx`)
- Loading / error / empty states; row renders code+email+status; expire → `updateAccessCode(id,{is_active:false})`; delete (confirm yes → calls api; cancel → no call); send email success alert + failure alert; create via the generate modal calls the api and closes.
- _Note: the real hooks invalidate on success; they do **not** do optimistic updates, so there is no client-side rollback to test — mutation-error surfacing (send-email failure) is tested instead._

## Conventions
- **Decision (D1-T4):** co-locate tests next to the code as `X.test.jsx` (harness-level tests live under `src/test/`). Keep this consistent — no `__tests__/` dirs.
- Wrap components under test in the same providers they need (react-query client, router) via a `renderWithProviders` helper in a test util.
- One behaviour per test; Arrange/Act/Assert; `user-event` for interactions (not `fireEvent` where avoidable).
- No real network; deterministic tests.

## CI (Phase 2)
`npm ci` → `npm run lint` → `npm test` must pass before merge (Jenkins stage). Build (`npm run build`) must succeed.
