# Backend Test Harness

## Purpose
- Provide a minimal integration-test foundation for API and repository regression tests.

## Prerequisites
1. Install dependencies in `backend/`:
   - `composer install`
2. Set test database environment variables:
   - `TEST_DB_DSN` (example: `mysql:host=127.0.0.1;port=3306;dbname=dice_goblins_test;charset=utf8mb4`)
   - `TEST_DB_USER`
   - `TEST_DB_PASS`
   - Recommended local file: `backend/.env.test.local` (loaded automatically by `tests/bootstrap.php`)

## Run
- `composer test`

## Notes
- `DatabaseTestCase` wraps each test in a transaction and rolls back after each test.
- Use SQL fixtures under `backend/tests/Fixtures/`.
