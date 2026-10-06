# Running LinkForge on local Kubernetes (minikube)

Phase 1.5 — deploy the whole app to a local cluster and prove HPA scales under load.
Topology & decisions: [ADR-0005](adr/0005-local-kubernetes-topology.md). Manifests:
[`infra/k8s/`](../infra/k8s/) (Kustomize). For the Docker Compose path instead, see
[local-setup.md](local-setup.md).

## Prerequisites

- **minikube** + **kubectl**, Docker driver.
- **SQL Server** running on the host (port 1433) with the `url_shortener` database — the
  DB is NOT in the cluster (ADR-0003); pods reach it at `host.minikube.internal`.
- Backend/frontend build context (this repo).

## One-time: secrets

```bash
cp infra/k8s/overlays/local/.env.k8s.local.example infra/k8s/overlays/local/.env.k8s.local
# edit .env.k8s.local:
#   APP_KEY        → (cd backend && php artisan key:generate --show)
#   SHORTCODE_KEY  → any long random string
#   DB_USERNAME / DB_PASSWORD → your host SQL Server credentials
```
`.env.k8s.local` is gitignored; only the `.example` is tracked.

## Bring it up

```bash
make k8s-up        # minikube start + ingress & metrics-server addons
make k8s-build     # build linkforge/api:local + linkforge/frontend:local into minikube
make k8s-deploy    # kubectl apply -k overlays/local, then the migration Job
make k8s-status    # pods / services / hpa / ingress
```

> **NetworkPolicy enforcement:** minikube's default CNI does not enforce NetworkPolicy.
> To exercise the policies in `networkpolicy.yaml`, start with
> `minikube start --cni=calico --addons=ingress,metrics-server` instead of `make k8s-up`.

## Reach it (ingress hosts)

Map both hosts to the minikube IP:

```bash
echo "$(minikube ip) linkforge.local app.linkforge.local" | sudo tee -a /etc/hosts
```

- **http://app.linkforge.local** — the admin SPA (its `/api` + `/metrics` are routed to
  the api on the same origin, so no CORS).
- **http://linkforge.local/<code>** — public short-URL redirects.
- Grafana / Prometheus are ClusterIP — reach them with port-forward:
  ```bash
  kubectl port-forward -n linkforge svc/grafana 3000:3000     # admin / admin
  kubectl port-forward -n linkforge svc/prometheus 9090:9090
  ```

## Everyday

```bash
make k8s-migrate   # re-run migrations (deletes + re-applies the Job)
make k8s-restart   # roll Deployments after a config/secret change (names are stable)
make k8s-status
make k8s-down      # delete the linkforge namespace (keep the cluster)
```

## What's deployed

| Workload | Kind | Notes |
|---|---|---|
| api | Deployment + Service + **HPA** | stateless; CPU-autoscaled (K8S-10) |
| clicks-worker | Deployment | Kafka consumer; not autoscaled |
| frontend | Deployment + Service | nginx-served SPA |
| redis, kafka | Deployment / StatefulSet + Service | local in-cluster (cloud → ElastiCache/MSK) |
| prometheus, grafana | Deployment + Service | scrape api `/metrics`; LinkForge dashboard |
| config/secrets | ConfigMap + Secret | `linkforge-config` / `linkforge-secrets` |
| PDB, NetworkPolicy, Ingress | — | resilience + routing |

## Troubleshooting

- **api `CrashLoopBackOff` / DB errors** — check `host.minikube.internal` resolves and
  SQL Server accepts the creds in `.env.k8s.local`; `kubectl logs deploy/api -n linkforge`.
- **Kafka not ready** — single-broker KRaft can be slow to form the quorum; give it a
  minute, check `kubectl logs kafka-0 -n linkforge`. On a constrained machine set
  `CLICK_EVENT_DRIVER=sync` in `config/app.local.env` and `make k8s-restart` (no Kafka).
- **HPA shows `<unknown>` targets** — metrics-server not ready yet; `kubectl get hpa -n linkforge -w`.
- **Config change didn't take** — generated names are stable (no hash), so pods don't
  roll automatically: `make k8s-restart`.

## Load testing & HPA proof (K8S-14)

A single command drives load through the ingress and shows HPA scaling:

```bash
make k8s-load-test                                     # 50 threads, 60s (defaults)
make k8s-load-test REDIRECT_THREADS=100 DURATION=180   # heavier run
```

What it does:
1. Shows pre-test API pods and HPA (baseline replicas / CPU)
2. Starts the ingress port-forward if not already running
3. Mints a short code and verifies the redirect works
4. Runs JMeter `redirect-throughput` against the ingress
5. Shows post-test HPA and pods — replicas should have scaled up

Watch HPA live in a second terminal while it runs:
```bash
kubectl get hpa api -n linkforge -w
```

HTML report lands in `out/redirect-report/index.html`.

For the individual scenario tests or the full 200-thread HPA proof, see
[load-testing.md](load-testing.md).
