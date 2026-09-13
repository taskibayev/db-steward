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

Planned tables: users, oauth_identities, client_connections, user_database_access, user_table_permissions, audit_operations, audit_snapshots, jobs, sql_executions, temporary_query_results, and notifications. Product tables and their first migration belong to later stages.

## Decisions

- Symfony API plus Vue SPA in one repository.
- MySQL 8.4 for the system database.
- UUIDv7 application identifiers.
- RabbitMQ with no automatic write retries.
- Centrifugo private user channels.
- Nginx single-origin routing.
- Russian-default frontend catalogs with Kazakh and English from the first frontend slice.

