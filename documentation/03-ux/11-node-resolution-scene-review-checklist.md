# Node/Rest/Summary Scene Review Checklist
----

Status: in-progress  
Last Updated: 2026-03-18  
Owner: Engineering + QA  
Depends On: `skills/scene-screenshot/SKILL.md`, `skills/ux-scene-review/SKILL.md`, `frontend/src/scenes/NodeResolutionScene.ts`, `frontend/src/scenes/RestManagementScene.ts`, `frontend/src/scenes/RunEndSummaryScene.ts`

## Purpose
- Track screenshot-driven review for node resolution, rest management, and run summary scenes.
- Record deterministic capture commands and iteration outcomes.
- Keep a concrete acceptance checklist for ongoing visual polish.

## Capture Setup
- Install capture browser once:
  - `npm run capture:scene:install`
- If dev server is already running on `127.0.0.1:4173`, prefer:
  - `npm run capture:scene -- --use-existing-server --scene <alias> ...`
- Current validated local capture base URL for this review cycle:
  - `http://127.0.0.1:4174/`

## Scene State Checklist
- [x] Node Resolution (combat) baseline captured.
- [x] Node Resolution (loot) baseline captured.
- [x] Rest Management baseline captured.
- [x] Run End Summary baseline captured.
- [x] Node Resolution (combat) accepted after QA visual pass.
- [x] Node Resolution (loot) accepted after QA visual pass.
- [x] Rest Management accepted after QA visual pass.
- [x] Run End Summary accepted after QA visual pass.

## Deterministic Capture Commands
- Node combat:
  - `npm run capture:scene -- --use-existing-server --scene node --auth authenticated --scene-data "{\"runId\":\"91\",\"nodeId\":\"501\",\"nodeType\":\"combat\"}" --output artifacts/screenshots/node-combat-round1.png --settle-ms 1800`
- Node loot:
  - `npm run capture:scene -- --use-existing-server --scene node --auth authenticated --scene-data "{\"runId\":\"91\",\"nodeId\":\"502\",\"nodeType\":\"loot\"}" --output artifacts/screenshots/node-loot-round1.png --settle-ms 1800`
- Rest:
  - `npm run capture:scene -- --use-existing-server --scene rest --auth authenticated --scene-data "{\"runId\":\"91\",\"nodeId\":\"503\"}" --output artifacts/screenshots/rest-round1.png --settle-ms 1800`
- Summary:
  - `npm run capture:scene -- --use-existing-server --scene summary --auth authenticated --scene-data "{\"status\":\"completed\",\"rewards\":[\"Soft Currency +40\",\"Unit XP Award +25 each\"],\"progression\":[\"Unit 1: L8 (205 XP)\",\"Unit 2: L10 (285 XP)\"],\"survivors\":[\"Bogblade\",\"Grizzle Hex\"],\"defeated\":[\"Rivet Fang\"]}" --output artifacts/screenshots/summary-round1.png --settle-ms 1800`

## Iteration Log
1. Round 1 baseline capture completed.
   - `artifacts/screenshots/node-combat-round1.png`
   - `artifacts/screenshots/node-loot-round1.png`
   - `artifacts/screenshots/rest-round1.png`
   - `artifacts/screenshots/summary-round1.png`
2. Round 2 recapture attempted against stale compose frontend (`5173`) and rejected (invalid identical node images).
3. Round 3 valid baseline recapture completed against local updated frontend (`4174`).
  - `artifacts/screenshots/node-combat-round3.png`
  - `artifacts/screenshots/node-loot-round3.png`
  - `artifacts/screenshots/rest-round3.png`
  - `artifacts/screenshots/summary-round3.png`
4. Round 4 polish + recapture completed.
  - `artifacts/screenshots/node-combat-round4.png`
  - `artifacts/screenshots/rest-round4.png`
  - `artifacts/screenshots/summary-round4.png`
5. Round 5 polish + recapture completed.
  - `artifacts/screenshots/node-loot-round5.png`
  - `artifacts/screenshots/rest-round5.png`
  - `artifacts/screenshots/summary-round5.png`
6. Accepted iteration for this cycle: Round 5.
7. Primary fixes validated in loop:
  - Node/summary action-column button centering
  - Node tick header/track spacing to reduce overlap
  - Rest action-column vertical spacing and status-text alignment
  - Summary content panel readability for denser lists

## Acceptance Criteria
- Node combat: tick controls are readable, compact, and do not overlap combat summary panel.
- Node combat: autoplay stops at final event tick and does not wrap.
- Node loot: no timeline controls shown; rewards receipt and loot visual are clear.
- Rest: unit cards, grid, and action stack are readable at 1440x900 without overlap.
- Summary: rewards/progression/survivor sections remain legible without clipping.
- All scenes: no text collisions, overflow clipping, or stretched imagery in baseline viewport.
