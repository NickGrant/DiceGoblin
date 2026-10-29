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

## Test authoring rules
- Test behavior, not current application content. Do not pin flexible catalog membership, display names, balance values, reward amounts, or incidental ordering in functional tests.
- Prefer small purpose-built content fixtures and persisted rows. A test should contain only the data needed to exercise its contract.
- Put exhaustive behavior combinations at the owning unit/service layer. Integration tests should earn their cost by proving an API, security, transaction, persistence, or cross-boundary contract.
- Keep one primary reason to fail per test. Split or remove scenarios that assert multiple independent systems when narrower coverage already protects those systems.
- Avoid duplicate controller/service assertions. At the controller layer, assert the envelope/boundary and representative result rather than repeating every lower-level field assertion.
- Canonical authored-content checks belong in content validation unless an exact authored shape is itself an accepted product contract.

See `documentation/06-testing-release/00-testing-strategy.md` for the canonical policy.

## Notes
- `DatabaseTestCase` wraps each test in a transaction and rolls back after each test.
- `IntegrationTestCase` is used where application code owns transactions; track created users so teardown can remove their cascaded state efficiently.
- Use SQL fixtures under `backend/tests/Fixtures/`.
- Reset the test database by loading `backend/migrations/vnext_baseline.sql` into the Docker `goblin_test` database.
- The canonical PHPUnit suite discovers current vNext `*Test.php` files under `backend/tests/`. It runs against the fresh vNext baseline without permanent prototype-schema skips. Retained pre-vNext migration evidence for unfinished Milestones 11-13 lives in `backend/prototype-test-evidence/` and is intentionally outside discovery; port useful cases to current tests when those milestones replace the behavior.

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
