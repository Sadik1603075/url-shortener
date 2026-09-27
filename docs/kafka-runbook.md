# Kafka runbook — local click analytics

How to run the click-analytics pipeline locally. Architecture: [ADR-0002](adr/0002-kafka-event-analytics.md).

## TL;DR — two ways to run

| Mode | `CLICK_EVENT_DRIVER` | Kafka? | Backend runs | Use when |
|---|---|---|---|---|
| **Host dev** | `sync` | no | `php artisan serve` on host | day-to-day coding; analytics update inline |
| **Full stack** | `kafka` | yes (Docker) | Docker container | exercising the real event pipeline |

The dashboard chart and stats work in **both** modes — `sync` just projects
synchronously instead of through Kafka.

---

## Mode A — host dev (no Kafka)

Nothing to install. In `backend/.env`:

```env
CLICK_EVENT_DRIVER=sync
```

```bash
cd backend
php artisan migrate      # creates click_events + click_daily_aggregates
php artisan serve
```

Hit a short link → the click is recorded immediately. Verify:

```bash
php artisan tinker --execute="echo App\Models\ClickEvent::count();"
```

---

## Mode B — full stack (Kafka in Docker)

Requires Docker Desktop. **SQL Server stays on the Windows host**; the containers
reach it via `host.docker.internal`. Make sure SQL Server allows TCP on 1433 and
the `backend/.env` DB credentials are valid.

> If you already started a standalone `apache/kafka` container, stop it first so
> it doesn't clash on port 9092: `docker rm -f <that-container>`.

From the repo root:

```bash
docker compose up -d --build
```

This starts: **kafka** (KRaft, 9092), **kafka-ui** (http://localhost:8080),
**redis** (6379), **api** (http://localhost:8000), and **clicks-worker**
(`php artisan clicks:consume`). The `api` and `clicks-worker` containers run with
`CLICK_EVENT_DRIVER=kafka` and `KAFKA_BROKERS=kafka:29092` (set in
`docker-compose.yml`).

Create the topic (auto-created on first produce, or explicitly):

```bash
docker compose exec kafka /opt/kafka/bin/kafka-topics.sh \
  --create --if-not-exists --topic url.clicked \
  --bootstrap-server localhost:9092 --partitions 3 --replication-factor 1
```

### Verify the pipeline

1. Create a short URL in the app (http://localhost:8000 via the frontend, or the API).
2. Visit `http://localhost:8000/{code}` a few times.
3. Watch events land on the topic in **kafka-ui** (http://localhost:8080) under
   Topics → `url.clicked`.
4. Watch the worker project them:
   ```bash
   docker compose logs -f clicks-worker
   ```
5. Confirm the read model:
   ```bash
   docker compose exec api php artisan tinker \
     --execute="echo App\Models\ClickDailyAggregate::sum('clicks');"
   ```
6. The dashboard **Activity → Clicks** chart reflects the totals.

### Resilience check (the hot path must survive Kafka being down)

```bash
docker compose stop kafka
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/{code}   # → 302
docker compose start kafka
```

The redirect still returns 302; the publish failure is logged
(`click.publish.failed`). Buffered/failed clicks are simply not recorded — the
redirect is never sacrificed for analytics.

---

## Config reference (`backend/.env`)

| Key | Default | Meaning |
|---|---|---|
| `CLICK_EVENT_DRIVER` | `sync` | `sync` \| `kafka` \| `log` |
| `KAFKA_BROKERS` | `localhost:9092` | broker list (compose overrides to `kafka:29092`) |
| `KAFKA_CLICK_TOPIC` | `url.clicked` | topic for click events |
| `KAFKA_CONSUMER_GROUP` | `linkforge-clicks` | consumer group id |
| `KAFKA_CONSUME_TIMEOUT_MS` | `2000` | poll timeout (graceful shutdown between polls) |

## Troubleshooting

- **`ext-rdkafka is not installed`** from `clicks:consume` → you ran it on the
  host. It only runs in the Docker image; use `docker compose`.
- **Consumer can't reach DB** → SQL Server not accepting remote TCP, or
  `host.docker.internal` not resolving. Confirm `extra_hosts` is present (it is
  in `docker-compose.yml`) and the host firewall allows 1433.
- **Producer connects but nothing consumes** → check the topic name matches and
  the worker's `KAFKA_BROKERS=kafka:29092` (internal listener), not `localhost`.
- **Port 9092 already in use** → a leftover standalone Kafka container; remove it.
