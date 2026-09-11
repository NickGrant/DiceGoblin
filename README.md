# Dice Goblins vNext

Dice Goblins is a browser-delivered tactical roguelite currently undergoing a deliberate vNext implementation reset on the `vnext-game-overhaul` branch.

## vNext Stack

- Web/platform shell: Angular + TypeScript
- Game client: Phaser 3
- Authoritative backend: PHP 8.3
- Runtime datastore: MySQL 8.4
- Authored gameplay content: Git-tracked JSON
- Local orchestration: Docker Compose

Angular owns the public site, authentication/account surfaces, and the `/game` host. Phaser owns gameplay. PHP owns gameplay authority. MySQL stores mutable player/runtime state; authored gameplay definitions do not use MySQL as their primary catalog.

## Current Branch Rule

The source tree still contains prototype implementation while vNext is built. Prototype code is useful implementation evidence, but it is not architectural authority when it conflicts with accepted vNext documentation.

Do not preserve old APIs, schema shapes, Angular gameplay pages, reward-claim flows, or SQL-authored content merely because they exist in the prototype.

## Documentation Authority

Start with:

1. `documentation/07-development-path/vnext-game-overhaul.md` - implementation plan and milestone status.
2. `documentation/README.md` - current documentation map.
3. The relevant accepted `documentation/07-development-path/vnext-*.md` decision document.
4. Relevant current gameplay/content documentation under `documentation/02-systems/` and `documentation/03-content/`.

Git history is the archive for superseded plans and prototype documentation. Historical documentation is intentionally not kept in the active vNext documentation tree.

## Agent Workflow

- `AGENTS.md` is the root coding-agent contract.
- `agent/LLM_CONTEXT.md` defines context-loading policy.
- `agent/CONTEXT_ROUTER.md` routes tasks to current vNext sources.
- `agent/ISSUES.md` and `agent/MILESTONES.md` contain active vNext execution work.
- `agent/QUALITY_GATES.md` owns current verification commands and quality gates.

## Repository Layout

- `frontend/` - Angular shell plus the evolving Phaser game client.
- `backend/` - PHP API, gameplay/domain code, repositories, and database infrastructure.
- `documentation/` - current game, content, architecture, UX, testing, and vNext decisions.
- `agent/` - coding-agent workflow and active execution state.
- `raw-assets/` - source art assets.

Use repository scripts and `AGENTS.md` for the current development/verification workflow rather than relying on prototype setup instructions from Git history.
