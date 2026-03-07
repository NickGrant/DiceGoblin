# Encounter Preview Risk/Reward UX
----

Status: active
Last Updated: 2026-03-07
Owner: UX + Game Design
Depends On: `documentation/03-ux/03-encounter-flow-transition-matrix.md`, `documentation/02-systems-mvp/08-encounter-reward-surface-rules.md`

## Purpose
- Define preview expectations so players understand node intent before committing.
- Improve perceived agency without over-revealing deterministic outcomes.

## Preview Model
- Preview should communicate:
  - node type (`combat`, `loot`, `rest`, `boss`, `exit`)
  - qualitative risk tier (`low`, `medium`, `high`)
  - qualitative reward intent (`progression`, `resources`, `recovery`, `run-end`)

- Preview should not communicate:
  - exact combat roll outcomes
  - exact loot tables
  - hidden unrevealed map state outside unlocked relationships

## Surface Contract
- `MapExplorationScene`
  - On hover/select, show a compact node preview panel.
  - Panel includes node icon, node label, risk tier, reward intent, and unlock relationship hint.

- `NodeResolutionScene`
  - Re-state the selected node intent and expected category before resolve action.

## Copy Guidelines
- Keep labels short and comparative (for example, "High Risk / High Progression").
- Prefer intent language over hard promises.
- Use consistent terms with systems docs and reward-surface docs.

## Acceptance Criteria
- Every actionable node type displays a preview surface.
- Preview language is consistent across map and resolution scenes.
- Preview helps decision-making without exposing deterministic internals.
