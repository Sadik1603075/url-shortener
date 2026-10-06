# Load testing & performance baseline

JMeter plans live in [`load/`](../load/) (see its README to run them). This doc
records the **baseline** so regressions and capacity decisions have a reference point.

> ⚠️ **The numbers below are a template, not yet a measured baseline.** JMeter was not
> run in the environment that authored this doc. Run the plans per `load/README.md` and
> replace the `TBD` cells with real figures (and date/commit them).

## Method

- **Target:** local stack via `make up` (api in Docker, `CLICK_EVENT_DRIVER=kafka`) —
  or host `php artisan serve` with `sync`. Note which, since the click path differs.
- **Each scenario:** warm up ~10s, then measure a steady 60s. Record from the JMeter
  HTML dashboard (`-e -o`).
- **Environment to note with every run:** machine (CPU/RAM), PHP server (fpm vs
  `artisan serve`), driver (`sync`/`kafka`), threads, and whether Redis/Kafka are warm.

## Baseline

_Run on: `TBD` · commit: `TBD` · host: `TBD` · driver: `TBD`_

| Scenario | Threads | RPS | p50 (ms) | p95 (ms) | p99 (ms) | Error % |
|---|---|---|---|---|---|---|
| Redirect (`GET /{code}`) | TBD | TBD | TBD | TBD | TBD | TBD |
| Create (`POST /urls`) | TBD | TBD | TBD | TBD | TBD | TBD |
| Admin login + list | TBD | TBD | TBD | TBD | TBD | TBD |

## Expected shape (hypotheses to confirm)

- **Redirect** should be the fastest and highest-RPS path — it's a Redis cache hit +
  a fire-and-forget click emit, no synchronous DB write on a cache hit. Watch the
  `linkforge_cache_events_total{result="hit"}` ratio climb after warmup.
- **Create** is heavier: access-code validation + a counter increment + an insert in a
  transaction. Lower RPS than redirect is expected.
- **Admin login** does bcrypt (`BCRYPT_ROUNDS`) + a token insert — intentionally the
  slowest; list is a paginated read.

## Bottlenecks to watch (fill in after a run)

- DB connection pool / SQL Server round-trips on create.
- Redis latency on the redirect path (cache + metrics storage share Redis).
- Under `kafka`, consumer lag (`linkforge_clicks_published_total - ..._consumed_total`)
  — producer throughput vs the single `clicks-worker`.
- bcrypt cost dominating admin login (tune `BCRYPT_ROUNDS` for the environment).

## Autoscaling — HPA load proof (K8S-14)

Goal: show that **peak load adds `api` replicas** on the local minikube cluster
(the headline Phase-1.5 requirement). Setup: [k8s-local.md](k8s-local.md).

### Procedure

```bash
# 1. Cluster up with the app (metrics-server addon on):
make k8s-up && make k8s-build && make k8s-deploy

# 2. Create a short code to hammer (via the api host):
curl -s -X POST http://linkforge.local/api/v1/urls \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"access_code":"DEV-ACCESS-001","long_url":"https://example.com"}'
# → copy data.short_code

# 3. Watch the HPA + pods in two terminals:
kubectl get hpa api -n linkforge -w
kubectl get pods -n linkforge -l app.kubernetes.io/name=api -w

# 4. Drive load at the ingress (ramp high enough to exceed 70% CPU):
jmeter -n -t load/redirect-throughput.jmx \
  -Jhost=linkforge.local -Jport=80 -Jcode=<SHORT_CODE> \
  -Jthreads=200 -Jrampup=30 -Jduration=300 \
  -l out/k8s-redirect.jtl -e -o out/k8s-redirect-report
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

> Not yet executed — needs a running minikube + JMeter. `api` currently runs `php artisan
> serve` (single-process); for sharper CPU-driven scaling, moving the api image to
> php-fpm/nginx or Laravel Octane is a documented follow-up (doesn't change the HPA wiring).
