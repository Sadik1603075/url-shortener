# Running LinkForge on local minikube + load testing (Windows / Docker driver)

> **Scope.** A start-to-finish runbook for bringing the whole app up on a **local
> minikube cluster** and running the **JMeter load tests** (including the HPA autoscale
> proof). This is the **Windows + Docker-driver** companion to [`k8s-local.md`](k8s-local.md)
> — it captures the networking realities that the generic guide (written for VM drivers)
> skips. Topology/decisions: [ADR-0005](adr/0005-local-kubernetes-topology.md).
> Load-test reference + baseline table: [`load-testing.md`](load-testing.md),
> [`load/README.md`](../load/README.md).

The cluster is **not fully self-contained**: the database is **host SQL Server**
(ADR-0003), reached from pods via `host.minikube.internal`. Redis + Kafka run in-cluster.

---

## 0. Prerequisites (one-time)

| Tool | Check | Notes |
|---|---|---|
| Docker Desktop | `docker version` | minikube uses the **docker** driver |
| minikube | `minikube version` | v1.39+ |
| kubectl | `kubectl version --client` | |
| GNU make | `make --version` | installed via `winget install ezwinports.make` |
| JMeter 5.6+ | `jmeter --version` | for the load tests |
| SQL Server | listening on `0.0.0.0:1433` | the `url_shortener` DB exists |

> After installing make/minikube, **open a fresh terminal** so they're on `PATH`.

### 0a. Firewall — let pods reach host SQL Server  *(critical on Windows)*

minikube pods connect to the host DB over the Docker/WSL virtual adapter. The default
inbound block drops that traffic, so the `api` pods `CrashLoopBackOff` with DB errors.
In an **Administrator** PowerShell, add an inbound allow once:

```powershell
New-NetFirewallRule -DisplayName "SQL Server 1433 (local k8s)" `
  -Direction Inbound -Action Allow -Protocol TCP -LocalPort 1433 -Profile Any
```

Also ensure SQL Server has **mixed-mode (SQL) auth** on and the `.env.k8s.local` login
has rights on `url_shortener` (pods use SQL auth, not Windows auth).

### 0b. Secrets

```bash
cp infra/k8s/overlays/local/.env.k8s.local.example infra/k8s/overlays/local/.env.k8s.local
# edit .env.k8s.local:
#   APP_KEY        → (cd backend && php artisan key:generate --show)
#   SHORTCODE_KEY  → any long random string
#   DB_USERNAME / DB_PASSWORD → host SQL Server credentials
```
`.env.k8s.local` is gitignored; only the `.example` is tracked.

---

## 1. Bring the cluster up

```bash
make k8s-up        # minikube start --addons=ingress,metrics-server
make k8s-build     # build linkforge/api:local + linkforge/frontend:local into minikube
make k8s-deploy    # kubectl apply -k overlays/local, then the migration Job
make k8s-status    # pods / svc / hpa / ingress
```

Expected pods in `linkforge`: `api` (×2), `clicks-worker`, `frontend` (×2), `redis`,
`kafka-0`, `prometheus`, `grafana`, and the `migrate` Job **Complete**.

> **Kafka / clicks-worker crash loop on a constrained machine?** Single-broker KRaft can
> be slow to form a quorum, and `clicks-worker` crash-loops until it's ready. Give it a
> minute, or fall back to the no-Kafka path: set `CLICK_EVENT_DRIVER=sync` in
> `infra/k8s/overlays/local/config/app.local.env`, then `make k8s-restart`.

---

## 2. Reach the app  *(Docker-driver specifics — this is where the generic guide is wrong)*

On the **docker driver**, the node IP from `minikube ip` (e.g. `192.168.49.2`) is **inside
Docker's network and NOT routable from the Windows host** — so the `k8s-local.md`
instruction to put the node IP in your hosts file does **not** work here. Instead:

### 2a. Hosts file → `127.0.0.1`

Edit `C:\Windows\System32\drivers\etc\hosts` **as Administrator**:

```
127.0.0.1  linkforge.local app.linkforge.local
```

### 2b. Port-forward the ingress to a free local port

Ports **80** (IIS) and **8080** (XAMPP) are typically already taken on this machine, so
pick a free one — e.g. **8090**. Keep this terminal **open**; reachability lasts only
while it runs:

```bash
kubectl port-forward -n ingress-nginx svc/ingress-nginx-controller 8090:80 --address 127.0.0.1
```

Browser connects to `127.0.0.1:8090` and sends `Host: app.linkforge.local` →
the forward pipes to **ingress-nginx**, which routes on the `Host` header (port ignored).
No `minikube tunnel` and **no Apache/reverse-proxy needed**.

### 2c. Open it

| URL | Serves |
|---|---|
| `http://app.linkforge.local:8090` | admin SPA (its `/api` + `/metrics` are same-origin) |
| `http://linkforge.local:8090/<code>` | public short-URL redirects |

> If the page shows an **IIS/XAMPP welcome page** instead of LinkForge, the request hit a
> host web server, not the forward — confirm you used the `:8090` URL and that the
> port-forward is running. If Chrome keeps timing out after a fix, clear its cache:
> `chrome://net-internals/#dns` → *Clear host cache*, then fully quit Chrome.

### 2d. Grafana / Prometheus (separate forwards)

```bash
kubectl port-forward -n linkforge svc/grafana 3000:3000     # http://localhost:3000  (admin/admin)
kubectl port-forward -n linkforge svc/prometheus 9090:9090  # http://localhost:9090
```

---

## 3. Seed + end-to-end smoke (manual)

Seed from the host (SSMS / `php artisan db:seed` in `backend/` — whatever you use):
admin `dev@example.com` / `password` and access code `DEV-ACCESS-001`.

1. Open `http://app.linkforge.local:8090`, create a short URL with the access code.
2. Click `http://linkforge.local:8090/<code>` → expect a **302** to the target.
3. Dashboard analytics increment; Grafana *LinkForge Overview* shows the redirect counter move.

> **Short-URL base.** Generated links must point at the **redirect** host
> (`linkforge.local:8090`), not the admin host. This is controlled by `SHORT_URL_BASE`
> (set in `overlays/local/config/app.local.env`); the `api` image builds `short_url` from
> it ([`ShortUrlResource`](../backend/app/Http/Resources/ShortUrlResource.php)). If a link
> comes out on `app.linkforge.local` (→ SPA 404), `SHORT_URL_BASE` is wrong/unset — fix it
> and `make k8s-restart`. Change the port here if you expose the ingress on something else.

---

## 4. Load testing (JMeter)

Plans live in [`load/`](../load/) (see its README for all params). Everything is driven
**through the port-forward** — so §2b's `kubectl port-forward … 8090:80` must still be
running. Prereq: the DB is **seeded** (admin `dev@example.com` + access code
`DEV-ACCESS-001`) — see §3.

### 4a. Run via `make` (preferred)

The `load*` targets wrap JMeter with the ingress host/port, assertions, and HTML-report
output already wired for this topology (defaults: `LOAD_PORT=8090`,
`ACCESS_CODE=DEV-ACCESS-001`). They write JTL + an HTML dashboard under `out/` (gitignored).

```bash
make load            # all three plans (redirect + create + admin) headless
# …or one at a time:
make load-redirect   # GET /{code} hot path (asserts 302) — auto-mints a code to hammer
make load-create     # POST /api/v1/urls with the access code (asserts 201)
make load-admin      # POST /auth/login → GET /admin/urls (asserts 200/200)
```

`load-redirect` **auto-mints** a short code (via the access code) if you don't give one;
pin an existing one with `CODE=<short_code>`. Override load shape on the CLI, e.g.
`make load DURATION=120 REDIRECT_THREADS=80`. Other vars: `RAMPUP`, `CREATE_THREADS`,
`ADMIN_THREADS`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `LOAD_APP_HOST`, `LOAD_RDR_HOST`.

### 4b. Equivalent raw commands (fallback / to tweak a plan directly)

```bash
# First mint a code to hammer (copy data.short_code):
curl -s -X POST http://app.linkforge.local:8090/api/v1/urls \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"access_code":"DEV-ACCESS-001","long_url":"https://example.com"}'

jmeter -n -t load/redirect-throughput.jmx \
  -Jhost=linkforge.local -Jport=8090 -Jcode=<SHORT_CODE> \
  -Jthreads=50 -Jrampup=10 -Jduration=60 \
  -l out/redirect.jtl -e -o out/redirect-report
```

Read **RPS / p50 / p95 / error%** from the HTML dashboard (`out/*-report/index.html`)
and record them in the baseline table in [`load-testing.md`](load-testing.md).

---

## 5. HPA autoscale proof (K8S-14 — the headline Phase-1.5 goal)

Show that **peak load adds `api` replicas**. The HPA targets CPU; `api` requests/limits
are set so CPU rises under load ([`infra/k8s/base/api.yaml`](../infra/k8s/base/api.yaml),
[`infra/k8s/base/hpa.yaml`](../infra/k8s/base/hpa.yaml): `min=2`, `max=10`, ~70% CPU).

```bash
# Terminal A — watch the HPA + api pods
kubectl get hpa api -n linkforge -w
kubectl get pods -n linkforge -l app.kubernetes.io/name=api -w

# Terminal B — drive hard for several minutes (200 threads × 300s)
make load-hpa CODE=<SHORT_CODE>
# equivalent to:
#   jmeter -n -t load/redirect-throughput.jmx \
#     -Jhost=linkforge.local -Jport=8090 -Jcode=<SHORT_CODE> \
#     -Jthreads=200 -Jrampup=30 -Jduration=300 \
#     -l out/k8s-redirect.jtl -e -o out/k8s-redirect-report
```

Record: idle replicas/CPU% → thread level at first scale-out → max replicas reached →
p95 at peak → time to scale back to `min` after load stops (~120s stabilization). Paste
the `kubectl get hpa`/`pods` transcript into the K8S-14 table in `load-testing.md`.

> **Throughput caveat.** A single `kubectl port-forward` is one proxied stream and can
> cap client RPS before the api CPU saturates. If replicas won't climb, drive load
> **from inside the cluster** instead — run JMeter (or `hey`/`wrk`) in a pod against the
> in-cluster `http://api.linkforge.svc.cluster.local:8000/<code>`, or expose the ingress
> with `minikube service -n ingress-nginx ingress-nginx-controller --url` and target that.
> Also note the api runs `php artisan serve` (single-process) — moving it to
> php-fpm/nginx or Octane sharpens CPU-driven scaling (doesn't change the HPA wiring).

---

## 6. Everyday & teardown

```bash
make k8s-migrate   # re-run the migration Job
make k8s-restart   # roll Deployments after a config/secret change
make k8s-status
make k8s-down      # delete the linkforge namespace (keep the cluster)
minikube stop      # stop the VM (keep state)   |  minikube delete = wipe cluster
```

Remember: the **`kubectl port-forward` must be running** for the app to be reachable in a
given session (it's this setup's "front door").

---

## Quick troubleshooting

| Symptom | Cause / fix |
|---|---|
| `api` `CrashLoopBackOff`, DB errors | firewall rule (§0a) missing, or wrong SQL creds in `.env.k8s.local` |
| Browser times out on `app.linkforge.local` | hosts file must be `127.0.0.1` (§2a) **and** port-forward running (§2b) |
| IIS/XAMPP welcome page shows | you hit a host web server — use the `:8090` URL; another server owns 80/8080 |
| `clicks-worker` crash loop / Kafka not ready | wait, or `CLICK_EVENT_DRIVER=sync` + `make k8s-restart` (§1) |
| HPA shows `<unknown>` | metrics-server still starting — `kubectl get hpa -n linkforge -w` |
| HPA won't scale under load | port-forward throughput cap — drive load in-cluster (§5 caveat) |
| Config change didn't take | stable generated names don't auto-roll — `make k8s-restart` |
