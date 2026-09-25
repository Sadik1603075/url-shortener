# CLAUDE.md — Backend

Claude-specific behaviour for the backend. **Read `./AGENTS.md` (backend rules) and the root `../AGENTS.md` + `../CLAUDE.md` first.**

Quick reminders specific to this service:
- Respect the layering: Controller → Service (DTOs in) → Repository (interface) → Model; Resources out. No shortcuts.
- The **redirect path must never block on analytics** and must survive Kafka/analytics-DB being down.
- Every task ships PHPUnit tests (`composer test`). Unit-test services with mocked repos/cache/publisher; feature-test endpoints with `RefreshDatabase`.
- Never weaken the access-code gate or `admin` middleware to make a test pass.
- New infra dependency ⇒ ADR + interface + Compose service + graceful degradation + metric.
- Update `backend/docs/` (api.md, domain-model, conventions, testing) as part of the task, then tick the box in `docs/roadmap.md`.
