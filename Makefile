# LinkForge — local developer shortcuts.
# Requires Docker Compose v2 (`docker compose`) and GNU make (Windows: install via
# choco, or run from Git Bash/WSL). Backend/frontend tests run on the host (see
# docs/local-setup.md). The DB is host SQL Server (ADR-0003), not a container — the
# server process survives `down`/`fresh`, but note `fresh` WIPES its schema/data.

.DEFAULT_GOAL := help
.PHONY: help up down logs fresh test test-backend test-frontend migrate \
        k8s-up k8s-build k8s-deploy k8s-migrate k8s-restart k8s-status k8s-down

COMPOSE := docker compose
K8S_OVERLAY := infra/k8s/overlays/local
K8S_NS := linkforge

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

k8s-deploy: ## Apply the local overlay, then run the migration Job
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
