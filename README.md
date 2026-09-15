# DB Steward

DB Steward provides controlled access to client MySQL databases. Managers can browse permitted data, edit one row at a time, inspect an append-only audit trail, and safely undo supported CRUD operations. Long-running custom SQL will be handled asynchronously.

Stages 0 through 5 are complete: the application foundation, authentication, encrypted client connections, manager access policies, data browser, and audited single-row CRUD are available.

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

`CLIENT_CREDENTIALS_KEY` encrypts client-database usernames and passwords with authenticated XChaCha20-Poly1305 encryption. Production must use a unique base64-encoded 32-byte key, kept outside Git. Back it up securely: changing or losing it makes stored credentials unreadable.

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

## Client database connections

Administrators can create, edit, test, enable, and disable connections from the **Databases** screen. One connection always targets exactly one MySQL database. Creating or editing performs only read-only connectivity checks (`SELECT 1`, `SELECT VERSION()`, and `SELECT DATABASE()`). Login and password values are encrypted before persistence and never returned by the API.

## Local demo data

Create a local manager plus isolated Northwind and Sakila client databases:

```bash
make demo
```

The default manager is `manager@example.com`. Override it with `DEMO_MANAGER_EMAIL=you@example.com make demo`. In development, sign in through the **Test sign-in** form; there is no password because application authentication uses OAuth. The command is idempotent, assigns both databases in default-allow mode, and cannot run in production.

The demo database user receives only `SELECT`, `INSERT`, `UPDATE`, and `DELETE`; it cannot modify schemas. Northwind is pinned to the [MyWind MySQL conversion](https://github.com/dalers/mywind), while Sakila comes from the [official MySQL sample](https://dev.mysql.com/doc/sakila/en/). Downloads are checksum-verified and imported only when their target database has no tables.

## Manager access

Administrators assign databases on the **Access rights** screen. Each assignment either denies everything except explicit table allowances or allows everything except explicit table denials. `SELECT`, `INSERT`, `UPDATE`, and `DELETE` can each inherit or override that default independently. Managers only see active databases currently assigned to them.

## Data browser

Managers and administrators can open an available database on the **Databases** screen, inspect permitted tables and views, and read server-paginated rows. Page sizes are 25, 50, or 100. Sorting and exact-value filters are evaluated by the backend after identifiers are verified against live schema metadata. Enter `__NULL__` as the filter value to select SQL `NULL` values.

Writable tables expose permission-aware controls for adding, editing, and explicitly confirming deletion of one row. Each successful change stores typed before/after snapshots and a diff in the append-only system audit. The **History** screen shows database-wide activity to administrators and assigned managers.

Supported CRUD operations can be safely undone from **History**. The application first verifies that the live schema and current row still match the recorded snapshot, then writes a compensating operation. Managers can undo only their own changes; administrators can undo any supported CRUD operation. Conflicts never force an overwrite, and a restored deleted row keeps its original primary key.

See [the architecture](docs/ARCHITECTURE.md), [technical specification](docs/TECHNICAL_SPEC.md), and [current status](docs/CURRENT.md).
