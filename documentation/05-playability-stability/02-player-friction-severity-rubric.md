# Player Friction Severity Rubric
----

Status: active  
Last Updated: 2026-03-04  
Owner: Design + Product  
Depends On: `documentation/03-ux/00-ux-and-debug-scope.md`, `ISSUES.md`

## Purpose
- Standardize severity assignment for UX and playability defects.
- Improve prioritization consistency during Milestone 6 polish and triage.

## Dimensions
- Clarity:
  - Can players understand what happened and what to do next?
- Progression continuity:
  - Can players continue expected flow without workaround?
- Frustration cost:
  - How much time or repeated effort is lost?
- Recoverability:
  - Can the player self-recover without reload/reset/support intervention?

## Severity Levels
- `high`:
  - Player is blocked, soft-locked, or misled into irreversible bad state.
  - Core loop cannot continue reliably.
  - Requires immediate fix before milestone close.
- `medium`:
  - Player can continue, but flow is confusing, error-prone, or disproportionately frustrating.
  - Causes repeat retries, unclear outcomes, or likely abandonment risk in first sessions.
- `low`:
  - Cosmetic or minor comprehension friction with clear workaround.
  - No significant progression disruption.

## Triage Guidance
- Default upward:
  - If uncertain between two severities, choose the higher severity until validated.
- First-session bias:
  - Defects in onboarding/start-run/first-claim flow get one severity bump when ambiguity exists.
- Evidence-required downgrade:
  - Downgrade only when playtest evidence confirms low user impact.
