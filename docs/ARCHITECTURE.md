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

Implemented tables: `users` and `oauth_identities`. Planned later tables: `client_connections`, `user_database_access`, `user_table_permissions`, `audit_operations`, `audit_snapshots`, `jobs`, `sql_executions`, `temporary_query_results`, and `notifications`.

## Authentication flow

- Accounts are pre-created; no public registration endpoint exists.
- Administrators are created with `app:user:create-admin`; the administration API creates managers only.
- OAuth providers implement a common backend interface. Google is always available; mock OAuth is registered only in `dev` and `test` and also refuses construction for `prod`.
- OAuth state is single-use and stored in the server-side session. Only a verified email matching an active account is accepted.
- Authentication uses a Symfony session cookie. Mutating `/api` calls require a session-bound CSRF token.
- Administrators inherit manager permissions. Disabled users are rejected by the security user checker.

## Decisions

- Symfony API plus Vue SPA in one repository.
- MySQL 8.4 for the system database.
- UUIDv7 application identifiers.
- RabbitMQ with no automatic write retries.
- Centrifugo private user channels.
- Nginx single-origin routing.
- Russian-default frontend catalogs with Kazakh and English from the first frontend slice.
