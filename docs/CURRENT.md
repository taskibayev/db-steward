# Current status

## Stage

Stage 0 — foundation (complete).

## Completed

- New Git repository initialized on `main`.
- Symfony and Vue 3 TypeScript skeletons created.
- System MySQL, RabbitMQ, worker, Centrifugo, Nginx, and frontend Compose services defined.
- Backend liveness/readiness endpoints and correlation IDs added.
- Initial Russian, Kazakh, and English application shell added.
- Backend and frontend smoke tests added.
- Canonical Make commands and GitHub Actions checks defined.
- The complete Compose stack starts successfully and all health checks pass.
- Backend formatter, container lint, PHPStan, and PHPUnit checks pass.
- Frontend formatter, ESLint, TypeScript, Vitest, and production build checks pass.

## Known issues

- No known Stage 0 blockers.
- The current web interface is only the multilingual foundation shell; product features start in Stage 1.

## Next recommended step

Begin Stage 1: system users, roles, console-only administrator creation, and Google OAuth.
