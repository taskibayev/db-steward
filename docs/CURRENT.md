# Current status

## Stage

Stage 3 — manager database assignments and CRUD permissions (complete).

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

## Known issues

- No known Stage 0 blockers.
- GitHub Actions is intentionally disabled to avoid hosted-runner costs; checks run locally for now.
- Google sign-in requires project-specific OAuth credentials and an authorized callback URL; local mock sign-in is ready without them.
- Production requires a unique backed-up `CLIENT_CREDENTIALS_KEY`; automatic key rotation is not implemented yet.
- TLS certificate options for client MySQL connections are not exposed in the current connection form yet.
- Table rules currently accept a safe manually entered identifier; selection from verified live schema metadata arrives with Stage 4.

## Next recommended step

Begin Stage 4: read-only schema discovery and permission-aware table browsing with server-side pagination, sorting, and filters.
