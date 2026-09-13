# Current status

## Stage

Stage 1 — users and authentication (complete).

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

## Known issues

- No known Stage 0 blockers.
- GitHub Actions is intentionally disabled to avoid hosted-runner costs; checks run locally for now.
- Google sign-in requires project-specific OAuth credentials and an authorized callback URL; local mock sign-in is ready without them.

## Next recommended step

Begin Stage 2: encrypted client-database connections, connection health checks, and administrator connection management.
