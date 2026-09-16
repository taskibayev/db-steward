# Production deployment

Stage 9 supplies a production-oriented Compose topology. It is deliberately separate from `compose.yaml`, which remains a local development environment.

## Prerequisites

- A Linux host with supported Docker Engine and Compose v2.
- A DNS name resolving to the host.
- A trusted TLS certificate and private key readable only by the deployment account.
- Google OAuth production credentials with `https://your-domain/api/auth/oauth/google/callback` registered.
- An encrypted, access-controlled backup destination outside the Docker host.

## Secrets and configuration

Copy `.env.production.example` to a secret source outside Git and replace every placeholder. Generate independent random values; do not reuse database, RabbitMQ, application, encryption, API, or JWT secrets.

Generate the client credential key with:

```bash
openssl rand -base64 32
```

Copy `infra/centrifugo/production.json` outside the repository, replace `https://db-steward.example.com` with the exact public origin, and set its absolute path as `CENTRIFUGO_CONFIG_PATH`. Do not use `*` for production origins.

Validate without printing secret values:

```bash
docker compose --env-file /secure/path/db-steward.env -f compose.production.yaml run --rm backend php bin/console app:production:validate
```

## First deployment

```bash
docker compose --env-file /secure/path/db-steward.env -f compose.production.yaml build
docker compose --env-file /secure/path/db-steward.env -f compose.production.yaml run --rm backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file /secure/path/db-steward.env -f compose.production.yaml up -d
docker compose --env-file /secure/path/db-steward.env -f compose.production.yaml exec backend php bin/console app:user:create-admin admin@example.com
```

Only Nginx ports 80 and 443 are published. MySQL, RabbitMQ, Symfony, Vue, and Centrifugo stay on the private Compose network. HTTP redirects to HTTPS; secure cookies, HSTS, security headers, API rate limits, OAuth rate limits, and WebSocket connection limits are enabled.

Backend and worker startup fails immediately when production secrets are missing, weak, malformed, or still contain placeholders. Container logs rotate locally at five 10 MB files per service and `no-new-privileges` is enabled.

## Release acceptance

Before exposing real client databases:

1. Run `make check` and `make production-check` from the exact release commit.
2. Verify `app:production:validate`, migrations, `/health/live`, and `/health/ready`.
3. Confirm HTTP redirects to HTTPS and inspect TLS with an external scanner.
4. Sign in through Google as an administrator and a manager.
5. Verify assignment isolation, read-only views, single-row CRUD, audit, all three undo types, queued SELECT/write SQL, cancellation, notifications, and reconnect recovery.
6. Revoke a manager permission while a job is queued and confirm the worker refuses it.
7. Stop and restart a worker during a queued test job and confirm no write is applied twice.
8. Confirm logs contain correlation IDs but no client credentials, SQL results, OAuth secrets, or encryption keys.
9. Create a system backup and restore it into an isolated empty test deployment.

## Backup and restore

Back up the system database at least daily and before every migration:

```bash
./infra/production/backup-system-db.sh /secure/backups/db-steward
```

The backup contains sensitive application metadata and encrypted client credentials; encrypt it again at rest, restrict access, copy it off-host, and test restoration regularly. Keep `CLIENT_CREDENTIALS_KEY` in a separate backed-up secret store—database backups are unusable without it.

Restore only into an empty, isolated system database while backend and workers are stopped. Decompress the chosen dump into the MySQL client, run migrations, validate the schema, then start services. Never run these commands against a client database.

## Upgrade and rollback

Back up first, build immutable images, run migrations once, and then recreate backend, worker, frontend, and Nginx. Application rollback is safe only while the migrated schema remains backward compatible. Database rollback must use a tested backup; do not automatically execute Doctrine `down` migrations on production data.

## Monitoring

Monitor container health, HTTP 5xx/429 rates, login failures, worker restarts, RabbitMQ queue depth, failed job counts, notification publication warnings, MySQL capacity, certificate expiry, backup freshness, and disk usage. Alerts and log shipping depend on the operator's platform and are not embedded in this repository.
