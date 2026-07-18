# Backend Test Harness

## Purpose
- Provide a minimal integration-test foundation for API and repository regression tests.

## Prerequisites
1. Start Docker:
   - `docker compose up --build`
   - If Docker is not running, ask the user to start Docker before running backend tests.
2. Provision and reset the Docker test database from the repository root:
   - `docker compose exec -T db mysql -uroot -prootpass < backend/docker/mysql/init/01-create-test-db.sql`
   - `docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"`
   - `docker compose exec -T db mysql -uroot -prootpass goblin_test < backend/migrations/schema_all.sql`
   - PowerShell: `Get-Content -Raw backend/docker/mysql/init/01-create-test-db.sql | docker compose exec -T db mysql -uroot -prootpass`
   - PowerShell: `docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"`
   - PowerShell: `Get-Content -Raw backend/migrations/schema_all.sql | docker compose exec -T db mysql -uroot -prootpass goblin_test`
3. Integration tests use these Docker service credentials:
   - `TEST_DB_DSN=mysql:host=db;port=3306;dbname=goblin_test;charset=utf8mb4`
   - `TEST_DB_USER=dice_test`
   - `TEST_DB_PASS=dicepass_test`

## Run
- Unit/backend suite:
  - `docker compose exec -T backend php vendor/bin/phpunit -c phpunit.xml.dist`
- Integration suite with Docker test DB env:
  - `docker compose exec -T backend sh -lc "TEST_DB_DSN='mysql:host=db;port=3306;dbname=goblin_test;charset=utf8mb4' TEST_DB_USER='dice_test' TEST_DB_PASS='dicepass_test' php vendor/bin/phpunit -c phpunit.xml.dist --stderr"`

## Notes
- `DatabaseTestCase` wraps each test in a transaction and rolls back after each test.
- Use SQL fixtures under `backend/tests/Fixtures/`.
- Reset the test database by loading `backend/migrations/schema_all.sql` into the Docker `goblin_test` database.
