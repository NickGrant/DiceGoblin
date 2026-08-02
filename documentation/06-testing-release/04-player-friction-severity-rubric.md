---
Title: "Player Friction Severity Rubric"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + QA + Engineering
Depends On:
  - documentation/04-ux/00-ux-and-debug-scope.md
  - agent/ISSUES.md
Category: 06-testing-release
Tags:
  - testing-release
---

# Player Friction Severity Rubric

## Purpose
- Standardize severity assignment for UX and playability defects.
- Improve prioritization consistency during demo polish and triage.

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
  - Requires immediate fix before demo milestone close.
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
