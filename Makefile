# LinkForge — local developer shortcuts.
# Requires Docker Compose v2 (`docker compose`) and GNU make (Windows: install via
# choco, or run from Git Bash/WSL). Backend/frontend tests run on the host (see
# docs/local-setup.md). The DB is host SQL Server (ADR-0003), not a container — the
# server process survives `down`/`fresh`, but note `fresh` WIPES its schema/data.

.DEFAULT_GOAL := help
.PHONY: help up down logs fresh test test-backend test-frontend migrate \
        k8s-up k8s-build k8s-deploy k8s-migrate k8s-restart k8s-status k8s-down \
        k8s-load-test load load-redirect load-create load-admin load-hpa

COMPOSE := docker compose
K8S_OVERLAY := infra/k8s/overlays/local
K8S_NS := linkforge

# --- Load testing (JMeter) — see docs/load-testing.md + docs/run-local-minikube.md §4.
# Everything is driven THROUGH the ingress port-forward, so that must be running:
#   kubectl port-forward -n ingress-nginx svc/ingress-nginx-controller 8090:80 --address 127.0.0.1
# Override any variable on the CLI, e.g. make load LOAD_PORT=8090 DURATION=120.
LOAD_PORT        := 8090
LOAD_APP_HOST    := app.linkforge.local
LOAD_RDR_HOST    := linkforge.local
ACCESS_CODE      := DEV-ACCESS-001
ADMIN_EMAIL      := dev@example.com
ADMIN_PASSWORD   := password
RAMPUP           := 10
DURATION         := 60
REDIRECT_THREADS := 50
CREATE_THREADS   := 20
ADMIN_THREADS    := 10
LOAD_OUT         := out

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-16s %s\n", $$1, $$2}'

up: ## Build and start the full stack (api, worker, kafka, kafka-ui, redis, prometheus, grafana)
	$(COMPOSE) up -d --build

down: ## Stop and remove the stack containers
	$(COMPOSE) down

logs: ## Tail logs — all services, or one: make logs SERVICE=clicks-worker
	$(COMPOSE) logs -f $(SERVICE)

migrate: ## Run DB migrations against the host SQL Server
	cd backend && php artisan migrate --force

fresh: ## DESTRUCTIVE reset: drops ALL dev DB tables + container volumes, rebuilds (needs CONFIRM=1)
ifndef CONFIRM
	@echo "'fresh' runs migrate:fresh — it DROPS ALL TABLES on the dev DB (url_shortener),"
	@echo "which the host SQL Server keeps even after 'down -v'. Re-run: make fresh CONFIRM=1"
	@exit 1
endif
	$(COMPOSE) down -v --remove-orphans
	cd backend && php artisan migrate:fresh --force
	$(COMPOSE) up -d --build

test: test-backend test-frontend ## Run the backend + frontend test suites

test-backend: ## Run the backend PHPUnit suite
	cd backend && composer test

test-frontend: ## Run the frontend Vitest suite
	cd frontend && npm test

# --- Local Kubernetes (minikube) — Phase 1.5, ADR-0005 ----------------------

k8s-up: ## Start minikube with the ingress + metrics-server addons (NetworkPolicy: add --cni=calico)
	minikube start --addons=ingress,metrics-server

k8s-build: ## Build the api + frontend images straight into minikube
	minikube image build -t linkforge/api:local ./backend
	minikube image build -t linkforge/frontend:local ./frontend

k8s-deploy: k8s-build ## Build images, apply the local overlay, then run the migration Job
	kubectl apply -k $(K8S_OVERLAY)
	$(MAKE) k8s-migrate

k8s-migrate: ## (Re)run the DB migration Job
	kubectl delete job migrate -n $(K8S_NS) --ignore-not-found
	kubectl apply -f infra/k8s/jobs/migrate.job.yaml
	kubectl wait --for=condition=complete job/migrate -n $(K8S_NS) --timeout=120s

k8s-restart: ## Roll all Deployments (pick up config/secret changes — names are stable)
	kubectl rollout restart deploy -n $(K8S_NS)

k8s-status: ## Show pods, services, HPA
	kubectl get pods,svc,hpa,ingress -n $(K8S_NS)

k8s-down: ## Delete the linkforge namespace (keep the cluster)
	kubectl delete -k $(K8S_OVERLAY) --ignore-not-found

# --- Load testing (JMeter) — needs the ingress port-forward running (see header) -------
# All load targets connect to 127.0.0.1:<LOAD_PORT> and send the Host header required by
# the ingress. Targets that need bash use Git Bash explicitly (GITBASH). Override on the
# CLI if your Git install is elsewhere: make k8s-load-test GITBASH="C:/Git/bin/bash.exe"
GITBASH := C:/Program Files/Git/bin/bash.exe

load: load-redirect load-create load-admin ## Run all three JMeter plans headless, writing JTL + HTML reports to out/

load-redirect: ## Redirect hot path (302). Auto-mints a short code, or pass CODE=<short_code>
	@"$(GITBASH)" -c '\
	  mkdir -p $(LOAD_OUT); \
	  rm -rf $(LOAD_OUT)/redirect.jtl $(LOAD_OUT)/redirect-report; \
	  CODE="$(CODE)"; \
	  if [ -z "$$CODE" ]; then \
	    echo "No CODE given — minting one via $(LOAD_APP_HOST) (access_code=$(ACCESS_CODE))…"; \
	    CODE=$$(curl -s -X POST http://127.0.0.1:$(LOAD_PORT)/api/v1/urls \
	      -H "Host: $(LOAD_APP_HOST)" -H "Content-Type: application/json" -H "Accept: application/json" \
	      -d "{\"access_code\":\"$(ACCESS_CODE)\",\"long_url\":\"https://example.com\"}" \
	      | sed -n "s/.*\"short_code\":\"\\([^\"]*\\)\".*/\\1/p"); \
	  fi; \
	  if [ -z "$$CODE" ]; then \
	    echo "ERROR: could not obtain a short code. Is the DB seeded and the port-forward up?"; \
	    echo "       Pass an existing one explicitly: make load-redirect CODE=<short_code>"; \
	    exit 1; \
	  fi; \
	  echo "Driving redirect-throughput on code=$$CODE ($(REDIRECT_THREADS) threads, $(DURATION)s)"; \
	  jmeter -n -t load/redirect-throughput.jmx \
	    -Jhost=127.0.0.1 -Jport=$(LOAD_PORT) -Jhost_header=$(LOAD_RDR_HOST) -Jcode=$$CODE \
	    -Jthreads=$(REDIRECT_THREADS) -Jrampup=$(RAMPUP) -Jduration=$(DURATION) \
	    -l $(LOAD_OUT)/redirect.jtl -e -o $(LOAD_OUT)/redirect-report'

load-create: ## Create-with-code path (201)
	@"$(GITBASH)" -c '\
	  mkdir -p $(LOAD_OUT); \
	  rm -rf $(LOAD_OUT)/create.jtl $(LOAD_OUT)/create-report; \
	  jmeter -n -t load/create-with-code.jmx \
	    -Jhost=127.0.0.1 -Jport=$(LOAD_PORT) -Jhost_header=$(LOAD_APP_HOST) -Jaccess_code=$(ACCESS_CODE) \
	    -Jthreads=$(CREATE_THREADS) -Jrampup=$(RAMPUP) -Jduration=$(DURATION) \
	    -l $(LOAD_OUT)/create.jtl -e -o $(LOAD_OUT)/create-report'

load-admin: ## Admin login + list path (200/200)
	@"$(GITBASH)" -c '\
	  mkdir -p $(LOAD_OUT); \
	  rm -rf $(LOAD_OUT)/admin.jtl $(LOAD_OUT)/admin-report; \
	  jmeter -n -t load/admin-login-list.jmx \
	    -Jhost=127.0.0.1 -Jport=$(LOAD_PORT) -Jhost_header=$(LOAD_APP_HOST) \
	    -Jemail=$(ADMIN_EMAIL) -Jpassword=$(ADMIN_PASSWORD) \
	    -Jthreads=$(ADMIN_THREADS) -Jrampup=$(RAMPUP) -Jduration=$(DURATION) \
	    -l $(LOAD_OUT)/admin.jtl -e -o $(LOAD_OUT)/admin-report'

load-hpa: ## HPA autoscale proof — heavy redirect (200 threads x 300s). Pass CODE=<short_code>
	@"$(GITBASH)" -c '\
	  if [ -z "$(CODE)" ]; then \
	    echo "ERROR: pass an existing short code: make load-hpa CODE=<short_code>"; \
	    exit 1; \
	  fi; \
	  echo "Watch in another terminal: kubectl get hpa api -n $(K8S_NS) -w"; \
	  mkdir -p $(LOAD_OUT); \
	  rm -rf $(LOAD_OUT)/k8s-redirect.jtl $(LOAD_OUT)/k8s-redirect-report; \
	  jmeter -n -t load/redirect-throughput.jmx \
	    -Jhost=127.0.0.1 -Jport=$(LOAD_PORT) -Jhost_header=$(LOAD_RDR_HOST) -Jcode=$(CODE) \
	    -Jthreads=200 -Jrampup=30 -Jduration=300 \
	    -l $(LOAD_OUT)/k8s-redirect.jtl -e -o $(LOAD_OUT)/k8s-redirect-report'

k8s-load-test: ## Full HPA load-test: show pods, drive redirect load, show scale-out result
	@"$(GITBASH)" load/k8s-load-test.sh \
	  $(LOAD_PORT) $(LOAD_APP_HOST) $(LOAD_RDR_HOST) $(ACCESS_CODE) \
	  $(REDIRECT_THREADS) $(RAMPUP) $(DURATION) $(LOAD_OUT) $(K8S_NS)
