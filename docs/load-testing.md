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

## Autoscaling (Phase 2 / minikube)

Drive `redirect-throughput` against the k8s ingress and watch the HPA scale the `api`
deployment on request-rate / p95 (see [observability.md](architecture/observability.md)).
Record the thread level at which a second/third replica starts, and whether p95 holds.
