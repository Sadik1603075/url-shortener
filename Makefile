# LinkForge — local developer shortcuts.
# Requires Docker Compose v2 (`docker compose`) and GNU make (Windows: install via
# choco, or run from Git Bash/WSL). Backend/frontend tests run on the host (see
# docs/local-setup.md). The DB is host SQL Server (ADR-0003), not a container — the
# server process survives `down`/`fresh`, but note `fresh` WIPES its schema/data.

.DEFAULT_GOAL := help
.PHONY: help up down logs fresh test test-backend test-frontend migrate

COMPOSE := docker compose

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
