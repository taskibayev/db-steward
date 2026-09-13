# DB Steward development rules

- Read `docs/TECHNICAL_SPEC.md`, `docs/ARCHITECTURE.md`, and `docs/CURRENT.md` before changing behavior.
- Work on one documented stage at a time and update `docs/CURRENT.md` when it is completed.
- Never read or print `.env` or real secrets. Use `.env.example` for safe local defaults.
- Treat every unknown client database as production. Never migrate, truncate, seed, or destructively test it.
- Doctrine ORM is for the system database only. Use DBAL behind explicit interfaces for client databases.
- Values are parameterized. Identifiers come from verified metadata and are quoted by DBAL.
- Authorization is enforced by the backend and rechecked by workers.
- Audit records are append-only. Undo is a new compensating operation and never overwrites history.
- Preserve unrelated user changes and never create commits unless explicitly requested.
- Every behavior change requires appropriate tests and all relevant checks before completion.

