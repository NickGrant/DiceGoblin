# Backend Test Harness

## Purpose
- Provide a minimal integration-test foundation for API and repository regression tests.

## Prerequisites
1. Start Docker:
   - `docker compose up --build`
   - If Docker is not running during local work, ask the user to start Docker before running backend tests.
2. Provision and reset the Docker test database from the repository root:
   - `docker compose exec -T db mysql -uroot -prootpass < backend/docker/mysql/init/01-create-test-db.sql`
   - `docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"`
   - `docker compose exec -T db mysql -uroot -prootpass goblin_test < backend/migrations/vnext_baseline.sql`
   - PowerShell: `Get-Content -Raw backend/docker/mysql/init/01-create-test-db.sql | docker compose exec -T db mysql -uroot -prootpass`
   - PowerShell: `docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"`
   - PowerShell: `Get-Content -Raw backend/migrations/vnext_baseline.sql | docker compose exec -T db mysql -uroot -prootpass goblin_test`
3. Integration tests use these Docker service credentials:
   - `TEST_DB_DSN=mysql:host=db;port=3306;dbname=goblin_test;charset=utf8mb4`
   - `TEST_DB_USER=dice_test`
   - `TEST_DB_PASS=dicepass_test`

## Run
- CI or host Composer/PHP suite:
  - `npm run test:backend`
- Local Docker suite:
  - `npm run test:backend:docker`

## Notes
- `DatabaseTestCase` wraps each test in a transaction and rolls back after each test.
- Use SQL fixtures under `backend/tests/Fixtures/`.
- Reset the test database by loading `backend/migrations/vnext_baseline.sql` into the Docker `goblin_test` database.
- The fresh baseline activates only vNext database/auth/session integration tests. Prototype gameplay integration tests are retained as migration evidence and report explicit skips until their owning milestones replace their schema and routes.

## Controlled Warband fixture

For development/UAT, an authenticated account can replace only its own Warband
with representative canonical state by sending:

`POST /api/v1/debug/fixtures/warband`

The request uses the normal session cookie and `X-CSRF-Token`. It is enabled
only when `ENABLE_WARBAND_FIXTURES=1` and `APP_ENV` is exactly `dev`, `test`,
or `uat`; missing, differently cased, unexpected, and production values fail closed. Repeating the request
replaces that account's units, dice, abilities, bindings, and squads in one
transaction. It does not change registration or provision starter assets.

For a repeatable, non-replacing account seed on the Docker development backend,
run `npm run uat:warband:seed -- --display-name=Nick` (or use
`--user-id=<id>` when names are ambiguous). This command uses the same canonical
fixture only when the selected account owns no Warband assets. Repeating it for
an account with a complete Warband is a no-op, preserving IDs, revision, and
terminal run history. It refuses partial Warband state or an active run instead
of overwriting player data. It has the same explicit environment opt-in and
non-production restriction as the HTTP fixture.
