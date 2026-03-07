# Post-Battle Progression Summary Contract
----

Status: active
Last Updated: 2026-03-07
Owner: UX + Product
Depends On: `documentation/03-ux/03-encounter-flow-transition-matrix.md`, `documentation/03-ux/05-unit-dice-details-acceptance.md`

## Purpose
- Define the player-facing summary contract immediately after battle resolution.
- Standardize feedback loops for rewards, progression, and next action.

## Required Summary Sections
- Outcome
  - victory/defeat status
  - run status implications when terminal

- Progression
  - XP gained
  - level changes (before -> after)
  - promotion indicators if applicable

- Rewards
  - newly granted resources/items
  - clear "none granted" fallback when empty

- Squad Impact
  - surviving units
  - defeated/disabled units if relevant

- Next Step
  - explicit CTA (continue to map, continue to summary, return home)

## Scene Contract
- `NodeResolutionScene`
  - Shows immediate battle resolution summary and unlocked-node note.
  - Determines branch to map return or end-of-run summary.

- `RunEndSummaryScene`
  - Aggregates run-level progression and reward outcomes.
  - Provides final continuation CTA back to home.

## Acceptance Criteria
- Summary sections are present even when data is empty (with fallback copy).
- Progression and reward terminology matches backend payload semantics.
- Every summary state has a single primary continuation action.
