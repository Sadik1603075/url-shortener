# CLAUDE.md — Frontend

Claude-specific behaviour for the SPA. **Read `./AGENTS.md` and the root `../AGENTS.md` + `../CLAUDE.md` first.**

Reminders specific to this app:
- Feature-sliced: a feature owns its pages, `api.js`, react-query hooks, and zod schemas. Don't cross-import feature internals.
- Server state → react-query; never hand-roll fetching in `useState`. Forms → react-hook-form + zod.
- Auth token lives in `localStorage`; `apiClient` attaches it and clears on 401. `ProtectedRoute` guards `/admin`.
- Every async view handles loading/empty/error. No business logic inside components.
- **Every task ships tests** (Vitest + RTL, `npm test`); mock the api module. Charts follow the `dataviz` skill.
- Update `frontend/docs/` and tick the box in `docs/roadmap.md` when done.
