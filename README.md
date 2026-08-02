# Dice Goblins

Dice Goblins is a browser-based tactical RPG/roguelite in active alpha launch, built with an Angular frontend and a PHP API backend.

## Tech Stack
- Frontend: Angular 20, TypeScript, Bootstrap grid/utilities, Phaser 3 only for optional canvas-hosted playback surfaces
- Backend: PHP 8.3 (built-in server)
- Database: MySQL 8.4
- Local orchestration: Docker Compose

## Repository Layout
- `frontend/` Angular frontend application
- `backend/` PHP API, repositories, services, and migrations
- `documentation/` game design, architecture, systems, and UX docs
- `agent/` agent workflow, backlog, role, and evaluation docs
- `raw-assets/` source art assets

## Prerequisites
- Docker Desktop (or Docker Engine + Compose)
- Node.js 22+ and npm for root/frontend convenience scripts

Docker is the supported local runtime for the backend, PHP, MySQL, and backend tests. If Docker is not running during local work, ask the user to start Docker before running backend or database commands. CI/pipeline scripts still run on the host tools installed by GitHub Actions.

## Quick Start (Docker)
1. Start services:
```bash
docker compose up --build
```
2. Initialize database schema (first run, and after migration changes):
```bash
docker compose exec -T db mysql -udice -pdicepass dice_goblins < backend/migrations/schema_all.sql
```
3. Provision and reset the dedicated backend test database:
```bash
docker compose exec -T db mysql -uroot -prootpass < backend/docker/mysql/init/01-create-test-db.sql
docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"
docker compose exec -T db mysql -uroot -prootpass goblin_test < backend/migrations/schema_all.sql
```
PowerShell equivalent:
```powershell
Get-Content -Raw backend/docker/mysql/init/01-create-test-db.sql | docker compose exec -T db mysql -uroot -prootpass
docker compose exec -T db mysql -uroot -prootpass -e "DROP DATABASE IF EXISTS goblin_test; CREATE DATABASE goblin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON goblin_test.* TO 'dice_test'@'%'; FLUSH PRIVILEGES;"
Get-Content -Raw backend/migrations/schema_all.sql | docker compose exec -T db mysql -uroot -prootpass goblin_test
```
Note:
The provision command is safe to rerun and is especially useful for existing Docker volumes, since MySQL init scripts only run on first container initialization.
4. Open apps:
- Frontend: http://localhost:5173
- Backend health endpoint: http://localhost:8080/api/v1/health
- phpMyAdmin: http://localhost:8081

## Environment
Backend env lives in `backend/.env`.
Backend test env lives in `backend/.env.test.local`.

If you need to reset local env values:
1. Copy `backend/.env.example` to `backend/.env`
2. Verify these dev defaults:
- `APP_URL=http://localhost:8080`
- `FRONTEND_URL=http://localhost:5173`
- `DEV_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173`
- DB host/user/pass match `docker-compose.yml`

## Frontend Commands
From `frontend/`:
```bash
npm install
npm run dev
npm run build
npm run test -- --watch=false --browsers=ChromeHeadless
```

## Backend Test Commands
Run backend tests in CI or any host environment with Composer/PHP installed:
```bash
npm run test:backend
```

Run backend tests locally through Docker:
```bash
npm run test:backend:docker
```

## API Surface (Current Core)
- Auth/session/profile:
  - `GET /api/v1/session`
  - `GET /api/v1/profile`
  - `POST /api/v1/auth/logout`
- Run flow:
  - `GET /api/v1/runs/current`
  - `POST /api/v1/runs`
  - `POST /api/v1/runs/:runId/nodes/:nodeId/resolve`
- Battles:
  - `GET /api/v1/battles/:battleId/log`
  - `POST /api/v1/battles/:battleId/claim`
- Squads/warband management:
  - `POST /api/v1/teams`
  - `POST /api/v1/teams/:teamId/activate`
  - `PUT /api/v1/teams/:teamId`

## Documentation
Start with:
- `documentation/00-overview/00-project-overview.md`
- `documentation/05-technical/00-tech-stack.md`
- `documentation/README.md`

## Agent Workflow
- `AGENTS.md` is the root entrypoint for coding agents.
- `agent/README.md` is the index for agent-specific workflow files.
- `agent/ISSUES.md` and `agent/MILESTONES.md` are the active execution sources of truth.

## Workflow Commands
From repository root:
```bash
npm run startup:check
npm run backlog -- --help
npm run backlog:validate
npm run llm:check
npm run docs:lint
npm run bundle:check
npm run verify:full
npm run watch:repo
```

`npm run watch:repo` starts a PowerShell watcher that safely fetches remote changes, fast-forwards only on a clean non-diverged branch, validates backlog docs when `agent/MILESTONES.md` changes, triggers a non-interactive `codex exec` run for active ready issues in the current open milestone, and then auto-commits plus pushes finished work only when the tree was clean before the Codex run.

`npm run backlog -- ...` provides a CLI for listing, retrieving, adding, updating, completing, moving, deleting, validating, and activating backlog items without manually editing the markdown files. Prefer this command surface for issue and milestone state changes.

Defaults:
- runs for 60 minutes
- checks every 5 minutes

Example overrides:
```bash
powershell -ExecutionPolicy Bypass -File scripts/watch-repo-sync.ps1 -DurationMinutes 120 -PollMinutes 2
```

## Local Skills
- `skills/backlog-ops/SKILL.md` for issue/milestone lifecycle operations.
- `skills/startup-verification/SKILL.md` for startup validation workflow.
- `skills/scene-screenshot/SKILL.md` for deterministic Phaser scene screenshots through the debug scene loader and capture command.
- `skills/ux-scene-review/SKILL.md` for iterative UX review loops on scenes using screenshot capture, code fixes, and QA review.

## Troubleshooting
- CORS/session issues: verify `DEV_ALLOWED_ORIGINS` in `backend/.env` includes both `http://localhost:5173` and `http://127.0.0.1:5173`.
- Scene screenshot capture: run `npm run capture:scene -- --scene <scene> --base-url http://127.0.0.1:5173/` one capture at a time against the shared local frontend.
- Empty/missing data: re-apply `backend/migrations/schema_all.sql` to local DB.
- Backend integration tests cannot connect: make sure Docker Desktop is running, then rerun the Docker test DB provision/reset commands above.
- Frontend cannot reach backend: verify the backend is listening on `:8080` and, if needed, inject a runtime API base URL via `window.__DICE_GOBLIN_CONFIG__.apiBaseUrl`.
