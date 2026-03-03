# Dice Goblins

Dice Goblins is a browser-based tactical RPG/roguelite prototype built with a Phaser frontend and a PHP API backend.

## Tech Stack
- Frontend: TypeScript, Vite, Phaser 3
- Backend: PHP 8.3 (built-in server)
- Database: MySQL 8.4
- Local orchestration: Docker Compose

## Repository Layout
- `frontend/` Phaser game client
- `backend/` PHP API, repositories, services, and migrations
- `documentation/` design, architecture, and systems specs
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
3. Open apps:
- Frontend: http://localhost:5173
- Backend health endpoint: http://localhost:8080/api/v1/health
- phpMyAdmin: http://localhost:8081

## Environment
Backend env lives in `backend/.env`.

If you need to reset local env values:
1. Copy `backend/.env.example` to `backend/.env`
2. Verify these dev defaults:
- `APP_URL=http://localhost:8080`
- `FRONTEND_URL=http://localhost:5173`
- `DEV_ALLOWED_ORIGINS=http://localhost:5173`
- DB host/user/pass match `docker-compose.yml`

## Frontend Commands
From `frontend/`:
```bash
npm install
npm run dev
npm run build
npm run preview
npm run db:schema
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

## Agent Workflow Files
This repo includes lightweight collaboration control docs:
- `AGENTS.md`
- `LLM_CONTEXT.md`
- `ROLES.md`
- `ISSUES.md`
- `ISSUES_BACKLOG.md` (planning/deferred)
- `ISSUES_ARCHIVE.md`
- `MILESTONES.md`
- `MILESTONES_BACKLOG.md` (planning/deferred)
- `MILESTONES_ARCHIVE.md`

Use `ISSUES.md` for active work only; move completed items to `ISSUES_ARCHIVE.md`.

## Backlog Validation
From repository root:
```bash
npm run backlog:validate
```

## LLM Workflow Commands
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

## Troubleshooting
- CORS/session issues: verify `DEV_ALLOWED_ORIGINS` in `backend/.env` and ensure frontend runs on `http://localhost:5173`.
- Empty/missing data: re-apply `backend/migrations/schema_all.sql` to local DB.
- Frontend cannot reach backend: verify `VITE_API_BASE_URL` and that backend is listening on `:8080`.
