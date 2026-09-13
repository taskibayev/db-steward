# DB Steward — technical specification

## Product

DB Steward is a controlled, simplified phpMyAdmin-like application. Administrators connect external MySQL databases and grant managers access without revealing credentials. Managers browse data, perform single-row CRUD, run custom SQL, inspect database-wide history, and undo their own supported CRUD operations.

## Roles and authentication

- Roles: administrator and manager.
- The first administrator is created only by `app:user:create-admin <email>`.
- Administrators pre-create managers by email.
- Google OAuth accepts only a pre-created, active user with verified matching email.
- OAuth is provider-adapter based; only Google is included initially.
- A development/test mock provider is impossible to enable in production.
- Administrators manage users, connections, access, all jobs, and all undo operations.
- Managers see all audit activity for an assigned database, see only their jobs, and undo only their own CRUD.

## Connections and permissions

- One connection identifies exactly one MySQL database.
- Credentials use authenticated encryption and never appear in API responses, logs, audit, queue messages, or WebSocket events.
- For each manager/database assignment, the administrator selects default allow or default deny.
- Table-level `SELECT`, `INSERT`, `UPDATE`, and `DELETE` decisions override the database default independently.
- Permissions are checked on every API call and rechecked by workers.
- Views and tables without a primary key are read-only.

## Data browser and CRUD

- Server-side pagination: 25, 50, or 100 rows.
- Filters and sorting use verified schema metadata and bound values.
- Simple and composite primary keys are supported.
- Generated, binary, and automatic fields are not editable.
- `NULL` is distinct from an empty string.
- A normal mutation affects one row and records a typed before/after snapshot.
- Delete requires explicit confirmation.

## Audit and undo

Audit is append-only and retained indefinitely in the first release. It records actor, correlation ID, database, table, action, typed primary key, before/after snapshots, diff, status, timing, and a safe error.

- Undo INSERT deletes the row only if it still equals the recorded after snapshot.
- Undo UPDATE restores before only if the row still equals after.
- Undo DELETE inserts the full before snapshot with the original primary key, including an old auto-increment value.
- Conflicts, schema drift, foreign keys, or unique constraints block undo without forced overwrite.
- Every undo attempt creates a new audit operation.
- An operation can be successfully undone once. Redo is deferred.

## Custom SQL

- Exactly one `SELECT`, `INSERT`, `UPDATE`, or `DELETE` statement is permitted.
- DDL, DCL, transaction control, file access, procedures, multiple statements, schema switching, and system schemas are forbidden.
- Validation uses a MySQL parser/AST, not regular expressions alone.
- Permissions are checked for every table found in joins and subqueries.
- All custom SQL is queued in RabbitMQ.
- Custom writes require explicit acknowledgement that they can affect many rows and cannot be undone.
- The first release does not preview or cap affected write rows. It shows the actual count after completion.
- SELECT results are server-limited to 1000 rows, retained privately for one hour, then deleted.
- CSV export, write previews, write limits, and transactional batch CRUD are deferred.

## Jobs and realtime

- Job states: queued, running, succeeded, failed, cancel requested, cancelled.
- Queue messages contain identifiers, never credentials or full results.
- A blocked user, disabled connection, or revoked permission prevents job execution.
- Duplicate delivery must not reapply a completed write.
- Write operations with uncertain outcome are not retried automatically.
- Queued cancellation is guaranteed; running cancellation is best effort.
- Centrifugo publishes only safe metadata to a private per-user channel.
- Notifications persist in the system database and support unread state.

## Interface

- Vue 3 + TypeScript, desktop-first with tablet support.
- Languages: Russian (default), Kazakh, English.
- Explicit loading, empty, success, forbidden, conflict, and error states.
- Dangerous operations require confirmation.

## Security boundaries

- Vue talks only to Symfony.
- Doctrine ORM accesses only the system database.
- Client schemas are dynamic DBAL connections behind interfaces.
- Values are bound; identifiers are selected from metadata and quoted by DBAL.
- Cookie authentication uses Secure, HttpOnly and SameSite settings with CSRF protection.
- Production requires HTTPS, rate limits, security headers, safe errors, and structured secret-free logs.
- Meaningful operations carry a correlation ID.

## Deferred scope

Batch CRUD, redo, CSV export, affected-row previews and limits, binary editing, mobile application, non-MySQL databases, additional OAuth providers, schema changes, dumps, and backups are outside the first release.

