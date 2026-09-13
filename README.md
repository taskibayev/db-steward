# DB Steward

DB Steward provides controlled access to client MySQL databases. Managers can browse permitted data, edit one row at a time, inspect an append-only audit trail, and safely undo supported CRUD operations. Long-running custom SQL will be handled asynchronously.

Stages 0 and 1 are complete: the application foundation, system users, roles, console-only administrator creation, and OAuth sign-in are available.

## Requirements

- Docker Engine with Docker Compose v2
- Make

Host PHP, Composer, Node.js and MySQL installations are not required.

## Local setup

```bash
make init
make up
```

Open <http://localhost:8080>. RabbitMQ management is bound to <http://127.0.0.1:15672> for local development only.

Apply system migrations and create the first administrator:

```bash
make migrate
docker compose exec backend php bin/console app:user:create-admin you@example.com
```

In local development, use the test sign-in form with the same email. For Google sign-in, set `GOOGLE_OAUTH_CLIENT_ID` and `GOOGLE_OAUTH_CLIENT_SECRET` outside Git and register this callback URL:

```text
http://localhost:8080/api/auth/oauth/google/callback
```

The values in `.env.example` are explicitly local development defaults. Production credentials must be supplied by a secret store and must never be committed.

## Canonical commands

```bash
make up             # start all services
make down           # stop all services
make migrate        # migrate the system database only
make worker         # run a foreground Messenger worker
make lint           # formatting and lint checks
make analyse        # PHPStan and TypeScript
make test           # backend and frontend tests
make build          # container and frontend builds
make check          # all checks
```

Migrations must never be run against client databases.

GitHub Actions is intentionally disabled. Run `make check` locally before requesting a commit.

## Services

- Nginx — single local entry point
- Symfony API — system operations and authorization boundary
- Vue 3 — browser application
- MySQL 8.4 — DB Steward system database
- RabbitMQ — asynchronous jobs
- Symfony Messenger worker — job execution
- Centrifugo — private WebSocket transport

See [the architecture](docs/ARCHITECTURE.md), [technical specification](docs/TECHNICAL_SPEC.md), and [current status](docs/CURRENT.md).
