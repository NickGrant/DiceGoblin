# LLM Context Manifest
----

## Purpose
Keep coding-agent context small, current, and aligned with the vNext overhaul.

## Always Include
- `AGENTS.md`
- `agent/ISSUES.md`
- `agent/MILESTONES.md`
- `agent/CONTEXT_ROUTER.md`

## vNext Context Rule
The active branch intentionally removes superseded documentation instead of keeping an in-tree legacy archive. Git history is the historical source.

For implementation work, prefer:
1. the active milestone in `agent/MILESTONES.md`;
2. `documentation/07-development-path/vnext-game-overhaul.md`;
3. the narrow accepted vNext decision documents required by the task;
4. current system/content documents when they describe gameplay that must be preserved;
5. source/tests as implementation evidence.

Do not use prototype behavior to override an accepted vNext decision.

## Include On Demand
- `documentation/00-overview/` for product terminology and core loop.
- `documentation/01-lore/` for setting and character voice.
- `documentation/02-systems/` for preserved gameplay rules.
- `documentation/03-content/` for retained content-design references.
- `documentation/04-ux/01-visual-design-guide.md` for visual direction.
- `documentation/05-technical/` plus accepted vNext decision docs for technical work.
- `documentation/06-testing-release/00-testing-strategy.md` and `agent/QUALITY_GATES.md` for verification.
- `documentation/08-operations/` for engineering/documentation standards.
- backlog files only when explicitly doing backlog/planning work.
- archive files under `agent/` only for historical investigation or reopened work.

## Prefer Excluding
- `frontend/dist/`
- `frontend/node_modules/`
- `raw-assets/`
- generated artifacts unrelated to the task
- Git history and deleted documentation unless history is explicitly required

## Context Budget
Load summaries/indexes first, then the narrow contract, then implementation. Do not recursively load a whole documentation bucket merely because one file in it is relevant.
