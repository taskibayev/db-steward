.DEFAULT_GOAL := help
.PHONY: help init install up down logs migrate worker demo lint analyse test test-backend test-frontend build check production-check

help:
	@echo "DB Steward: init up down migrate worker demo lint analyse test build check production-check"
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
demo: up migrate
	./infra/demo-mysql/provision.sh
	docker compose exec -T backend php bin/console app:demo:provision $${DEMO_MANAGER_EMAIL:-manager@example.com}
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
production-check:
	./infra/production/check-compose.sh
	docker build --target production -t db-steward-backend-production-check ./backend
	docker build --target production -t db-steward-frontend-production-check ./frontend
	docker run --rm \
		-e APP_ENV=prod -e APP_SECRET=check-only-app-secret-0000000000000000 \
		-e DATABASE_URL=mysql://check:check@database/check -e MESSENGER_TRANSPORT_DSN=amqp://check:check@queue/%2f/messages \
		-e GOOGLE_OAUTH_CLIENT_ID=check-client -e GOOGLE_OAUTH_CLIENT_SECRET=check-only-google-secret-000000000000 \
		-e CLIENT_CREDENTIALS_KEY=MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= \
		-e CENTRIFUGO_API_URL=http://centrifugo:8000 -e CENTRIFUGO_API_KEY=check-only-api-key-00000000000000000000 \
		-e CENTRIFUGO_TOKEN_SECRET=check-only-token-secret-00000000000000 \
		db-steward-backend-production-check php bin/console app:production:validate
