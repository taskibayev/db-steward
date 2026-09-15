# Current status

## Stage

Stage 8 — persistent notifications and private realtime delivery (complete).

## Completed

- New Git repository initialized on `main`.
- Symfony and Vue 3 TypeScript skeletons created.
- System MySQL, RabbitMQ, worker, Centrifugo, Nginx, and frontend Compose services defined.
- Backend liveness/readiness endpoints and correlation IDs added.
- Initial Russian, Kazakh, and English application shell added.
- Backend and frontend smoke tests added.
- Canonical local Make verification commands defined.
- The complete Compose stack starts successfully and all health checks pass.
- Backend formatter, container lint, PHPStan, and PHPUnit checks pass.
- Frontend formatter, ESLint, TypeScript, Vitest, and production build checks pass.
- System `users` and `oauth_identities` tables and their first migration are implemented.
- UUIDv7 users, administrator and manager roles, active status, and last-login tracking are implemented.
- The console-only `app:user:create-admin <email>` workflow is implemented.
- Administrators can list, pre-create, disable, and enable manager accounts; they cannot disable themselves.
- Google OAuth and a provider-neutral adapter contract are implemented.
- Mock OAuth exists only in development/test and is additionally blocked in production at construction time.
- OAuth state validation, verified-email enforcement, session authentication, inactive-user checks, and CSRF protection are implemented.
- The frontend now provides login and administrator user-management screens in Russian, Kazakh, and English.
- The system MySQL migration is applied locally; the full mock OAuth session flow works through Nginx.
- Backend: 8 tests and 66 assertions pass. Frontend: 2 tests pass, and the production build succeeds.
- System `client_connections` table and migration are implemented and applied locally.
- Client-database usernames and passwords use authenticated XChaCha20-Poly1305 encryption with random nonces.
- Client MySQL access is isolated behind a `ClientDatabaseConnector` port and a short-lived Doctrine DBAL adapter.
- Administrators can list, create, edit, test, enable, and disable client connections.
- Connection creation and editing perform safe read-only health checks and retain a safe status, server version, and check time.
- API responses never expose usernames, passwords, ciphertext, encryption keys, or raw driver errors.
- The frontend provides connection management in Russian, Kazakh, and English.
- Backend: 13 tests and 111 assertions pass. Frontend: 2 tests pass, and the production build succeeds.
- System `user_database_access` and `user_table_permissions` tables and their migration are implemented.
- Administrators can assign a client database to a manager using either default-deny or default-allow mode and can revoke that assignment.
- Per-table `SELECT`, `INSERT`, `UPDATE`, and `DELETE` decisions independently allow, deny, or inherit the assignment default.
- Missing assignments deny access; administrators retain full access; inactive users and connections are always denied.
- Managers can list only their assigned active client databases.
- The frontend provides access-assignment and table-rule management in Russian, Kazakh, and English.
- Backend: 18 tests and 171 assertions pass. Frontend: 2 tests pass, and the production build succeeds.
- An isolated local client MySQL service, checksum-verified Northwind and Sakila imports, and idempotent `make demo` provisioning are available.
- The local demo manager is assigned both sample databases with data-only CRUD credentials; demo provisioning is disabled in production.
- Backend: 19 tests and 180 assertions pass after adding demo-provisioning coverage.
- Read-only client schema discovery is implemented behind a dedicated DBAL port.
- Managers only receive assigned tables for which `SELECT` is currently allowed; administrators can browse all active connections.
- Live metadata verifies every table, sort column, and filter column before DBAL quotes the identifier.
- The API provides server-side pages of 25, 50, or 100 rows, deterministic primary-key ordering, explicit sorting, exact filters, and `NULL` filtering.
- Views and tables without a primary key are marked read-only; invalid UTF-8 binary cells are safely represented as base64 values.
- The Vue browser supports database/table selection, schema types, pagination, sorting, filters, `NULL`, binary cells, and Russian/Kazakh/English labels.
- A real manager session successfully browses both demo connections and a 25-row Northwind page through Nginx.
- Backend: 23 tests and 240 assertions pass. Frontend: 3 tests pass, and the production build succeeds.
- System `audit_operations` and `audit_snapshots` tables and their migration are implemented and applied locally.
- Permission-aware single-row INSERT, UPDATE, and DELETE run through a dedicated DBAL port using live metadata, quoted identifiers, bound values, and client-side transactions.
- UPDATE and DELETE lock and target a complete simple or composite primary key and must affect exactly one row.
- Generated, automatic, binary, and primary-key columns are protected from editing; views and tables without a primary key remain read-only.
- Successful operations retain typed before/after snapshots and diffs; rejected conflicts and safe driver failures are also recorded without leaking raw errors.
- The data browser provides row creation, editing, explicit delete confirmation, and distinct NULL controls according to current permissions.
- Database-wide paginated history is available to assigned managers and administrators with actor, status, primary key, diff, and correlation ID.
- A real manager API session successfully completed and cleaned up an INSERT → UPDATE → DELETE cycle against Northwind, producing three audit records.
- Backend: 27 tests and 321 assertions pass. Frontend: 3 tests pass, and the production build succeeds.
- Undo INSERT, UPDATE, and DELETE are implemented as new append-only compensating audit operations linked to their originals.
- Undo verifies live schema types and current row contents before writing; conflicts and database constraints stop the transaction without forced overwrite.
- Deleted rows are restored with their original primary key, including old auto-increment values.
- Managers can undo only their own operations and must retain permission for the reverse mutation; administrators can undo all supported CRUD.
- System-row locking ensures an original operation can be successfully undone only once, while failed conflict attempts remain auditable and retryable.
- The history interface exposes localized Undo controls and explicit success, conflict, and error states in Russian, Kazakh, and English.
- Real manager API tests against the isolated Northwind demo verified all three compensations, original-key restoration, conflict detection, and cleanup.
- Backend: 31 tests and 425 assertions pass. Frontend: 3 tests pass, and the production build succeeds.
- The phpMyAdmin MySQL AST parser validates exactly one custom SELECT, INSERT, UPDATE, or DELETE and rejects DDL, transaction control, file access, locking reads, multiple statements, and cross/system-database access.
- Permissions are checked for every write target, JOIN, union, INSERT SELECT source, and nested SELECT both before queuing and again inside the worker.
- System `jobs`, `sql_executions`, and `temporary_query_results` tables and migrations are implemented and applied locally.
- RabbitMQ messages contain only resource UUIDs. Jobs are atomically claimed before client access, write retries are disabled, and duplicate delivery cannot reapply a completed mutation.
- Queued cancellation is guaranteed; running cancellation is represented as a best-effort request.
- Custom writes require explicit risk acknowledgement, cannot be undone, and report the actual affected-row count after completion.
- SELECT returns at most 1000 JSON-safe rows, reports truncation, remains private to the job owner or administrator, and is physically deleted by a delayed worker message after one hour.
- The localized Jobs interface supports submission, polling, status inspection, cancellation, affected-row display, and tabular SELECT results in Russian, Kazakh, and English.
- Real Nginx → Symfony → RabbitMQ → worker → Northwind tests verified SELECT results, the 1000-row cap, affected rows for INSERT/UPDATE/DELETE, delayed cleanup scheduling, and test-row cleanup.
- Backend: 47 tests and 542 assertions pass. Frontend: 3 tests pass, and the production build succeeds.
- System `notifications` storage and migration are implemented and applied locally with per-recipient indexes and unread timestamps.
- Successful, failed, and queued-cancelled SQL jobs create persistent language-neutral notifications for their actor.
- Notification list, single-read, read-all, and private realtime-token APIs enforce per-user isolation.
- Short-lived Centrifugo JWTs subscribe each authenticated user to exactly one private `user:{uuid}` channel.
- Realtime publications contain only notification ID, type, and creation time; SQL, credentials, and query results are never published.
- The Vue interface shows localized Russian, Kazakh, and English notifications, an unread navigation badge, read controls, and live job refresh.
- A realtime outage cannot lose a notification because persistence completes first and publication failure is reduced to a safe log event.
- A real Nginx → Symfony → RabbitMQ → worker → system MySQL → Centrifugo test verified job completion, notification persistence, unread state, JWT issuance, and successful publication.
- Backend: 50 tests and 593 assertions pass. Frontend: 3 tests pass, and the production build succeeds.

## Known issues

- No known Stage 0 blockers.
- GitHub Actions is intentionally disabled to avoid hosted-runner costs; checks run locally for now.
- Google sign-in requires project-specific OAuth credentials and an authorized callback URL; local mock sign-in is ready without them.
- Production requires a unique backed-up `CLIENT_CREDENTIALS_KEY`; automatic key rotation is not implemented yet.
- TLS certificate options for client MySQL connections are not exposed in the current connection form yet.
- Editing an existing primary-key value is intentionally disabled in the current CRUD form.

## Next recommended step

Define and begin Stage 9: production hardening, deployment configuration, and release acceptance checks.
