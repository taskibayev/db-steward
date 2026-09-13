.DEFAULT_GOAL := help
.PHONY: help init install up down logs migrate worker lint analyse test test-backend test-frontend build check

help:
	@echo "DB Steward: init up down migrate worker lint analyse test build check"
init:
	@test -f .env || cp .env.example .env
	docker compose build
	docker compose run --rm backend composer install
	docker compose run --rm frontend npm ci
install:
	docker compose run --rm backend composer install
	docker compose run --rm frontend npm ci
up:
	docker compose up -d --build
down:
	docker compose down
logs:
	docker compose logs -f --tail=100
migrate:
	docker compose run --rm backend php bin/console doctrine:migrations:migrate --no-interaction
worker:
	docker compose run --rm worker
lint:
	docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff
	docker compose run --rm frontend npm run lint
	docker compose run --rm frontend npm run format:check
analyse:
	docker compose run --rm backend vendor/bin/phpstan analyse
	docker compose run --rm frontend npm run typecheck
test: test-backend test-frontend
test-backend:
	docker compose run --rm backend php bin/phpunit
test-frontend:
	docker compose run --rm frontend npm run test
build:
	docker compose build
	docker compose run --rm frontend npm run build
check: lint analyse test build

