# Dice Goblins

Dice Goblins is a browser-based tactical RPG/roguelite prototype built with an Angular frontend and a PHP API backend.

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

Optional (non-Docker local dev):
- Node.js 22+
- npm
- PHP 8.3+
- MySQL 8+

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
npm run test:db:provision
npm run test:db:reset
```
Note:
`test:db:provision` is safe to rerun and is especially useful for existing Docker volumes, since MySQL init scripts only run on first container initialization.
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
- `documentation/01-architecture/00-tech-stack.md`
- `documentation/README.md`

## Agent Workflow
- `AGENTS.md` is the root entrypoint for coding agents.
- `agent/README.md` is the index for agent-specific workflow files.
- `agent/ISSUES.md` and `agent/MILESTONES.md` are the active execution sources of truth.

## Workflow Commands
From repository root:
```bash
npm run startup:check
npm run backlog:validate
npm run llm:check
npm run docs:lint
npm run bundle:check
npm run verify:full
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
- Backend integration tests cannot connect: make sure Docker Desktop is running, then rerun `npm run test:db:provision` and `npm run test:db:reset`.
- Frontend cannot reach backend: verify the backend is listening on `:8080` and, if needed, inject a runtime API base URL via `window.__DICE_GOBLIN_CONFIG__.apiBaseUrl`.
