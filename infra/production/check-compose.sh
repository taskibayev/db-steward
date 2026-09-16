#!/bin/sh
set -eu

check_directory=$(mktemp -d /tmp/db-steward-production-check.XXXXXX)
trap 'rm -rf "$check_directory"' EXIT
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj '/CN=db-steward-check.invalid' \
  -keyout "$check_directory/privkey.pem" -out "$check_directory/fullchain.pem" >/dev/null 2>&1

APP_SECRET=check-only-app-secret-0000000000000000 \
DATABASE_URL='mysql://db_steward:check@system-mysql:3306/db_steward?serverVersion=8.4&charset=utf8mb4' \
MESSENGER_TRANSPORT_DSN='amqp://db_steward:check@rabbitmq:5672/%2f/messages' \
MYSQL_DATABASE=db_steward \
MYSQL_USER=db_steward \
MYSQL_PASSWORD=check-only-mysql-password \
MYSQL_ROOT_PASSWORD=check-only-root-password \
RABBITMQ_USER=db_steward \
RABBITMQ_PASSWORD=check-only-rabbit-password \
GOOGLE_OAUTH_CLIENT_ID=check-client \
GOOGLE_OAUTH_CLIENT_SECRET=check-only-google-secret-000000000000 \
CLIENT_CREDENTIALS_KEY=MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= \
CENTRIFUGO_API_KEY=check-only-api-key-00000000000000000000 \
CENTRIFUGO_TOKEN_SECRET=check-only-token-secret-00000000000000 \
TLS_CERTIFICATE_PATH="$check_directory/fullchain.pem" \
TLS_PRIVATE_KEY_PATH="$check_directory/privkey.pem" \
CENTRIFUGO_CONFIG_PATH="$(pwd)/infra/centrifugo/production.json" \
docker compose -f compose.production.yaml config --quiet

docker run --rm -v "$(pwd)/infra/centrifugo/production.json:/centrifugo/config.json:ro" \
  centrifugo/centrifugo:v5 centrifugo checkconfig -c config.json

docker run --rm \
  --add-host backend:127.0.0.1 --add-host frontend:127.0.0.1 --add-host centrifugo:127.0.0.1 \
  -v "$(pwd)/infra/nginx/production.conf:/etc/nginx/conf.d/default.conf:ro" \
  -v "$check_directory/fullchain.pem:/etc/nginx/tls/fullchain.pem:ro" \
  -v "$check_directory/privkey.pem:/etc/nginx/tls/privkey.pem:ro" \
  nginx:1.28-alpine nginx -t

echo 'Production Compose, Centrifugo, and TLS Nginx configuration are structurally valid.'
