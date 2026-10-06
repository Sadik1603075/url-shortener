# Load tests (JMeter)

Parameterised JMeter 5.6 plans for the three hot paths. Run them headless (CLI) and
record the baseline in [`docs/load-testing.md`](../docs/load-testing.md).

| Plan | What it drives | Asserts |
|---|---|---|
| `redirect-throughput.jmx` | `GET /{code}` (the hot path) | 302 |
| `create-with-code.jmx` | `POST /api/v1/urls` with an access code | 201 |
| `admin-login-list.jmx` | `POST /auth/login` → `GET /admin/urls` (Bearer) | 200 / 200 |

## Prerequisites

1. The stack is up (`make up`, or `php artisan serve`) and reachable.
2. Seed data exists: `php artisan db:seed` → admin `dev@example.com` / `password` and
   access code `DEV-ACCESS-001`.
3. For `redirect-throughput`, you need a **real short code**. Create one and grab it:
   ```bash
   curl -s -X POST http://127.0.0.1:8000/api/v1/urls \
     -H 'Content-Type: application/json' -H 'Accept: application/json' \
     -d '{"access_code":"DEV-ACCESS-001","long_url":"https://example.com"}'
   # → copy data.short_code, pass it as -Jcode=<code>
   ```
4. JMeter 5.6+ on PATH (`jmeter --version`).

## Parameters (all have defaults; override with `-Jname=value`)

`host` (127.0.0.1) · `port` (8000) · `protocol` (http) · `host_header` (empty — set to
`linkforge.local` when targeting a k8s ingress) · `threads` · `rampup` (s) ·
`duration` (s). Plan-specific: `code` (redirect), `access_code` (create),
`email`/`password` (admin).

## Run headless

```bash
# from repo root — direct (php artisan serve / Docker Compose)
jmeter -n -t load/redirect-throughput.jmx -Jcode=ABC1234 -Jthreads=50 -Jduration=60 \
       -l out/redirect.jtl -e -o out/redirect-report

jmeter -n -t load/create-with-code.jmx -Jaccess_code=DEV-ACCESS-001 -Jthreads=20 -Jduration=60 \
       -l out/create.jtl -e -o out/create-report

jmeter -n -t load/admin-login-list.jmx -Jemail=dev@example.com -Jpassword=password -Jthreads=10 -Jduration=60 \
       -l out/admin.jtl -e -o out/admin-report

# via minikube ingress (port-forwarded)
jmeter -n -t load/redirect-throughput.jmx -Jhost=127.0.0.1 -Jport=8090 \
       -Jhost_header=linkforge.local -Jcode=ABC1234 -Jthreads=20 -Jduration=60 \
       -l out/redirect.jtl -e -o out/redirect-report
```

- `-n` headless, `-t` plan, `-l` raw results (JTL), `-e -o <dir>` HTML dashboard.
- Read RPS / p95 / error% from the HTML report (or the JTL) and record them in
  `docs/load-testing.md`.

## Watching autoscale (Phase 2 / minikube)

Point `-Jhost`/`-Jport` at the ingress, drive `redirect-throughput` hard, and watch
`kubectl get hpa -w` / `kubectl get pods -w` — new `api` replicas should spin up as
`linkforge_http_requests_total` rate and p95 climb (see observability.md).
