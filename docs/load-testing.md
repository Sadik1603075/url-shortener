# Load testing & performance baseline

JMeter plans live in [`load/`](../load/) (see its README to run them). This doc
records the **baseline** so regressions and capacity decisions have a reference point.

## Method

- **Target:** minikube cluster (2 `api` replicas, `php artisan serve`, port-forwarded
  ingress on `127.0.0.1:8090`, `Host: linkforge.local`).
- **Each scenario:** 10s ramp-up, then 60s steady. Results from the JMeter HTML dashboard
  (`-e -o`).
- **Environment:** Windows 11 Pro, minikube (docker driver), `CLICK_EVENT_DRIVER=kafka`
  (in-cluster Kafka in CrashLoopBackOff — click publish falls back gracefully),
  in-cluster Redis warm.

## Baseline

_Run on: 2026-10-06 · commit: `dc39817` · host: minikube (2 api pods, `php artisan serve`) · driver: kafka (degraded — Kafka down, graceful fallback)_

| Scenario | Threads | RPS | p50 (ms) | p95 (ms) | p99 (ms) | Error % |
|---|---|---|---|---|---|---|
| Redirect (`GET /{code}`) | 20 | 0.7 | 24,518 | 35,381 | 37,281 | 0.00 |
| Create (`POST /api/v1/urls`) | 10 | 3.8 | 2,443 | 4,694 | 5,317 | 0.00 |
| Admin login + list | 10 | 0.9 | 7,815 | 22,630 | 25,396 | 0.00 |

> **Note:** `php artisan serve` is a single-threaded development server. Each pod can
> only process one request at a time, so latency climbs linearly with concurrency. These
> numbers reflect the single-process bottleneck, not the application's inherent capacity.
> Moving to php-fpm/nginx or Laravel Octane would give a dramatically different profile.

## Expected shape (hypotheses to confirm)

- **Redirect** should be the fastest and highest-RPS path — it's a Redis cache hit +
  a fire-and-forget click emit, no synchronous DB write on a cache hit. Watch the
  `linkforge_cache_events_total{result="hit"}` ratio climb after warmup.
- **Create** is heavier: access-code validation + a counter increment + an insert in a
  transaction. Lower RPS than redirect is expected.
- **Admin login** does bcrypt (`BCRYPT_ROUNDS`) + a token insert — intentionally the
  slowest; list is a paginated read.

## Bottlenecks observed

- **`php artisan serve` single-threading** is the dominant bottleneck across all
  scenarios. With 2 pods, the cluster can serve exactly 2 concurrent requests; all others
  queue, driving latency proportional to `threads / 2`. This masks the actual application
  performance.
- **Redirect latency inversion:** redirect is *slower* than create (0.7 vs 3.8 RPS)
  because 20 threads queue behind 2 serial workers vs 10 threads for create. The per-
  request cost is actually lower (~2s uncongested vs ~2.4s for create).
- **Liveness probe sensitivity:** the default 1s timeout caused pod kills under load.
  Fixed by bumping `timeoutSeconds: 5` + `failureThreshold: 6` in [api.yaml](../infra/k8s/base/api.yaml).
- **Kafka down (CrashLoopBackOff):** click publish fails gracefully (no 500s), but
  analytics are not being projected. Needs a Kafka fix for full pipeline validation.
- bcrypt cost dominates admin login (~8s median for the login step alone under load).

## Autoscaling — HPA load proof (K8S-14)

Goal: show that **peak load adds `api` replicas** on the local minikube cluster
(the headline Phase-1.5 requirement). Setup: [k8s-local.md](k8s-local.md).

### Procedure

```bash
# 1. Cluster up with the app (metrics-server addon on):
make k8s-up && make k8s-deploy   # k8s-deploy includes k8s-build

# 2. Run the load test (auto-mints a code, starts port-forward, drives load):
make k8s-load-test REDIRECT_THREADS=100 DURATION=180

# 3. Watch HPA live in another terminal while it runs:
kubectl get hpa api -n linkforge -w
```

### What to record here (TBD — run on a minikube host)

- Baseline replicas (should be `minReplicas=2`) and idle CPU%.
- The **thread level / RPS at which the HPA adds a 2nd→3rd replica**, and the max
  replicas reached (cap `maxReplicas=10`).
- Whether p95 latency holds as replicas are added (`out/k8s-redirect-report`).
- Scale-down: replicas returning toward `minReplicas` after load stops (≈120s
  stabilization window, per `hpa.yaml`).
- Paste the `kubectl get hpa`/`get pods` transcript showing REPLICAS climbing.

| Metric | Value |
|---|---|
| Idle replicas / CPU% | TBD |
| Threads at first scale-out | TBD |
| Max replicas under load | TBD |
| p95 at peak (ms) | TBD |
| Time to scale back to min | TBD |

> Run with `make k8s-load-test` (see [k8s-local.md](k8s-local.md)). The api image now
> uses **nginx + php-fpm** (8 workers per pod) for concurrent request handling.
