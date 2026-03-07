# First-Session Onboarding and Objective Framing
----

Status: active
Last Updated: 2026-03-07
Owner: UX + Game Design
Depends On: `documentation/03-ux/09-first-session-player-journey.md`

## Purpose
- Define onboarding messaging and objective framing for a new player.
- Reduce confusion during first login and first run start.

## Onboarding Goals
- Establish immediate objective: "Start and finish your first run."
- Explain player agency: squad prep, region choice, node choice.
- Reinforce progression loop: battle outcomes feed long-term squad growth.

## Messaging Contract by Stage
- `HomeScene`
  - Primary objective copy: "Start your first run."
  - Secondary cue: "You can adjust squad and dice before committing."

- `WarbandManagementScene`
  - Explain squad purpose: "Squads define who enters runs and where they start."
  - Encourage minimum action: "Confirm one viable squad before run start."

- `RegionSelectScene`
  - Explain choice impact: "Region selection determines encounter style and pacing."
  - Locked region feedback must include cause and next-step expectation.

- `MapExplorationScene`
  - Explain map intent: "Choose available nodes to progress toward exit."
  - Clarify affordances: rest = management, other nodes = resolution.

- `RunEndSummaryScene`
  - Explain outcome implications: completed/failed/abandoned consequences.
  - Prompt next action: return home and iterate squad strategy.

## Acceptance Criteria
- Objective copy exists at first critical touchpoints (home, region select, run map).
- No critical transition relies on hidden rules or external knowledge.
- Blocked/error states include concrete next-step guidance.
