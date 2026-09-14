# Architecture

## Shape

DB Steward is a modular monolith. One Symfony codebase serves the REST API and console commands; the same image runs Messenger workers. Vue is a separate SPA. Nginx is the only browser-facing service.

```text
Browser -> Nginx -> Vue
                 -> Symfony API -> system MySQL
                                -> client MySQL (dynamic DBAL)
                                -> RabbitMQ -> worker -> client MySQL
                                -> Centrifugo -> private WebSocket channel
```

## Trust boundaries

- Client database credentials are decrypted only inside the backend or worker process when required.
- Frontend never accesses MySQL, RabbitMQ, or Centrifugo's server API.
- Queue and WebSocket payloads contain resource IDs and safe state only.
- The system and client databases do not share a distributed transaction. Future mutation workflows must represent uncertain outcomes explicitly.
- Only system-database migrations are supported.

## Backend modules

Planned bounded modules: Auth, User, Connection, Permission, ClientDatabase, Schema, Crud, Audit, Undo, Job, Sql, Notification, and Shared. Controllers remain thin. Client database access is isolated behind explicit interfaces.

## System data model

Implemented tables: `users`, `oauth_identities`, `client_connections`, `user_database_access`, and `user_table_permissions`. Planned later tables: `audit_operations`, `audit_snapshots`, `jobs`, `sql_executions`, `temporary_query_results`, and `notifications`.

## Authentication flow

- Accounts are pre-created; no public registration endpoint exists.
- Administrators are created with `app:user:create-admin`; the administration API creates managers only.
- OAuth providers implement a common backend interface. Google is always available; mock OAuth is registered only in `dev` and `test` and also refuses construction for `prod`.
- OAuth state is single-use and stored in the server-side session. Only a verified email matching an active account is accepted.
- Authentication uses a Symfony session cookie. Mutating `/api` calls require a session-bound CSRF token.
- Administrators inherit manager permissions. Disabled users are rejected by the security user checker.

## Client connection boundary

- `ClientDatabaseConnector` is the port for client-database connectivity; `DbalClientDatabaseConnector` is its Doctrine DBAL adapter.
- Doctrine ORM never manages a client database. Dynamic DBAL connections are short-lived and explicitly closed.
- Client usernames and passwords are encrypted independently with XChaCha20-Poly1305. Each encryption uses a random nonce and authenticated additional data.
- The encryption key comes from `CLIENT_CREDENTIALS_KEY`; ciphertext contains a format version for future rotation support.
- Connection API views expose safe metadata and `credentialsConfigured`, never the username, password, ciphertext, key, or driver exception.
- Connectivity checks have a five-second connection timeout and execute only `SELECT 1`, `SELECT VERSION()`, and `SELECT DATABASE()`.
- Failures collapse to stable safe error codes. Raw connection exceptions do not enter API responses.

## Authorization model

- A `user_database_access` row assigns one active client connection to one manager and selects either `default_deny` or `default_allow`.
- A `user_table_permissions` row may independently set `SELECT`, `INSERT`, `UPDATE`, and `DELETE` to allow, deny, or inherit the assignment default.
- Missing assignments deny manager access. Administrators are allowed without assignments, while inactive users and inactive connections are always denied.
- `PermissionChecker` is the application port used by future synchronous handlers and workers. Workers must reload current system state and call it again immediately before client-database work.
- The manager connection list is derived from current assignments and never reveals unassigned or inactive connections.

## Local demo boundary

- `client-mysql-demo` is a separate MySQL container and volume; it never shares storage or credentials with the system database.
- `make demo` imports checksum-pinned Northwind and Sakila samples, creates an OAuth manager identity, stores encrypted demo connection credentials, and assigns both databases.
- The client demo account has data-only CRUD grants and no DDL, user-management, or system-database privileges.
- Demo provisioning refuses to run in the production Symfony environment.

## Read-only database browser

- `ClientDatabaseReader` is the read-only port; its DBAL adapter opens a short-lived dynamic connection and always closes it.
- Schema identifiers originate in `information_schema`. Requested table, sort, and filter column names must exactly match this metadata before DBAL quotes them.
- Filter values are bound parameters. Pagination is server-side and limited to 25, 50, or 100 rows.
- Schema discovery requires a current database assignment. Managers only receive tables for which `SELECT` resolves to allowed; the permission is checked again before every row query.
- Views and tables without a primary key are explicitly marked read-only. Invalid binary UTF-8 values are represented as base64 metadata rather than corrupting JSON.
- Driver failures collapse to `client_database_unavailable`; raw client errors and credentials never enter API responses.

## Decisions

- Symfony API plus Vue SPA in one repository.
- MySQL 8.4 for the system database.
- UUIDv7 application identifiers.
- RabbitMQ with no automatic write retries.
- Centrifugo private user channels.
- Nginx single-origin routing.
- Russian-default frontend catalogs with Kazakh and English from the first frontend slice.
