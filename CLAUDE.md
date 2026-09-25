# CLAUDE.md — Agent Operating Protocol

> How an AI agent should **behave** in this repo. For *what* to build and *how the code is organised*, read `AGENTS.md` (same directory) and the directory-level `AGENTS.md`.
> This file is intentionally short. It encodes habits, not architecture.

---

## Prime directives

1. **Docs first, code second.** Before touching an area, read its `docs/` entry and the matching `docs/roadmap.md` task. Do not re-scan the whole codebase to rebuild context that a doc already holds. If the doc is missing or stale, that is itself a bug — fix the doc as part of the task.

2. **One roadmap task at a time.** The user drives the sequence. Work the task you were asked for, to its full Definition of Done (see `AGENTS.md` §5), then stop and report. Do not silently expand scope.

3. **Test as you go — never defer testing.** Every task ships its own tests, in the same change. A task with passing code and no tests is **not done**.

4. **Respect the layering.** Controller → Service → Repository, DTOs at boundaries, FormRequests for validation, Resources for output. If you feel tempted to put logic in a controller or a model, re-read `backend/AGENTS.md`.

5. **Keep the hot path cold-blooded.** The redirect path touches Redis and emits an event — it must not do synchronous analytics work or heavy DB writes.

---

## Token & context economy

- Prefer reading a focused `docs/` file over grepping many source files.
- When exploring the user's machine, **stage only the specific files a task needs**, not whole trees. The full tree is already summarised in the docs.
- After finishing a task, **write down what you learned** in the relevant doc so the next session starts cheap.
- Summarise long tool output to the essential finding; don't echo large files back.

---

## Working rhythm for each task

1. Read the roadmap task + referenced docs.
2. Restate the task and its Definition of Done in one or two lines.
3. Implement following the conventions.
4. Write and run tests for it.
5. Update the doc(s) and tick the roadmap checkbox.
6. Report: what changed, what was tested, any deviation or decision that needs the user's confirmation.

---

## When to stop and ask

- A decision is **irreversible or expensive** and could reasonably go two ways (e.g. DB engine choice, event schema that consumers will depend on) → surface it, recommend one, wait.
- The task as written conflicts with an ADR or a product property in `AGENTS.md` → flag it.
- Everything else: take the most reasonable reading, note the assumption, proceed.

---

## Guardrails

- Never commit secrets or real credentials. `.env` stays local; document keys in `*.env.example`.
- Never weaken the access-code gate or the admin authorization to "make a test pass".
- Never introduce a new infra dependency without an ADR and a corresponding local Docker Compose service.
