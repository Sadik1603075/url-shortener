# Observability

How LinkForge is monitored locally (and, identically, in cloud). Decisions in
[ADR-0004](../adr/0004-observability.md).

## Running it

```
docker compose up -d --build
```

- **API `/metrics`** — http://localhost:8000/metrics (Prometheus exposition)
- **Prometheus** — http://localhost:9090 (scrapes `api:8000/metrics` every 15s)
- **Grafana** — http://localhost:3000 (admin / admin) → dashboard **LinkForge Overview**

On the host (no containers) metrics default to in-memory storage, so `/metrics`
reflects only the single `php artisan serve` process. In Compose, `METRICS_STORAGE=redis`
makes the `api` and `clicks-worker` share one metric store, so a single scrape of the
api covers the worker's counters too.

## Metrics (namespace `linkforge`)

| Metric | Type | Labels | Meaning |
|---|---|---|---|
| `http_requests_total` | counter | method, route, status | request volume / error rate |
| `http_request_duration_seconds` | histogram | method, route | latency (p50/p95/p99 via `histogram_quantile`) |
| `redirect_total` | counter | — | redirects served (business KPI) |
| `cache_events_total` | counter | result | redirect cache hit vs miss |
| `clicks_published_total` | counter | — | click events emitted on the redirect path |
| `click_publish_failures_total` | counter | — | degraded publishes (Kafka down) |
| `clicks_consumed_total` | counter | — | click events projected by the worker |

Route labels use the **route pattern** (e.g. `{shortCode}`, `api/v1/urls`), never the
concrete path, to keep cardinality bounded.

## Dashboard panels (LinkForge Overview)

1. **Request rate** — `sum(rate(linkforge_http_requests_total[5m])) by (route)`
2. **Latency p95** — `histogram_quantile(0.95, sum(rate(linkforge_http_request_duration_seconds_bucket[5m])) by (le))`
3. **Redirect rate** — `rate(linkforge_redirect_total[5m])`
4. **Cache hit ratio** — `sum(rate(linkforge_cache_events_total{result="hit"}[5m])) / clamp_min(sum(rate(linkforge_cache_events_total[5m])),1)`
5. **Publish failures** — `rate(linkforge_click_publish_failures_total[5m])`
6. **Consumer lag proxy** — `clamp_min(linkforge_clicks_published_total - linkforge_clicks_consumed_total, 0)`

## Alert ideas (not yet wired)

- **High error rate:** `sum(rate(linkforge_http_requests_total{status=~"5.."}[5m])) / sum(rate(linkforge_http_requests_total[5m])) > 0.05` for 5m.
- **Latency SLO burn:** p95 `http_request_duration_seconds` > 0.5s for 10m.
- **Publish failures:** `increase(linkforge_click_publish_failures_total[5m]) > 0` (Kafka degraded).
- **Consumer backlog:** lag proxy rising for 10m (worker stuck / Kafka lag).
- **Cache cold:** hit ratio < 0.5 sustained (cache eviction / cold start).

## Structured logs

JSON to stdout (`stdout` channel). `AssignRequestId` stamps `X-Request-Id` and adds
`request_id` + `actor` to every log line's context, so logs are correlatable per
request. Containers run `LOG_STACK=single,stdout`; in cloud, stdout ships to the log
backend (CloudWatch/Loki).

## Autoscaling note (Phase 2 / minikube)

The HPA will scale the `api` deployment on load; `linkforge_http_requests_total` rate
and `http_request_duration_seconds` p95 are the signals to watch while JMeter drives
traffic and new replicas spin up.
