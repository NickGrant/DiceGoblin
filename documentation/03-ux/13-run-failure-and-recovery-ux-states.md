# Run Failure and Recovery UX States
----

Status: active
Last Updated: 2026-03-07
Owner: UX + Game Design
Depends On: `documentation/02-systems-mvp/06-run-resolution-scope.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`

## Purpose
- Define clear UX states for partial defeat, total defeat, and abandonment.
- Ensure recovery behavior feels intentional and understandable.

## Failure-State Definitions
- Partial defeat
  - Encounter lost but run can continue through supported recovery path.
  - Player receives explicit explanation of what was lost and what remains.

- Total defeat
  - Run ends with failure status.
  - Player receives terminal summary and clear next step back to home loop.

- Abandonment
  - Player-triggered terminal run exit.
  - Requires confirmation and displays distinct summary messaging.

## UX Expectations
- `MapExplorationScene`
  - Abandon path must require explicit in-scene confirmation.
  - Failure to abandon (API error) shows retryable feedback.

- `NodeResolutionScene`
  - Defeat outcomes should explicitly state whether the run continues or ends.
  - Retry messaging only appears when retry path is valid.

- `RunEndSummaryScene`
  - Distinct messaging for `completed`, `failed`, and `abandoned`.
  - Consistent section structure regardless of terminal status.

## Recovery Guidance
- Always state the immediate next action (continue/map/home).
- Avoid ambiguous language for irreversible outcomes.
- Keep failure messaging short, specific, and actionable.

## Acceptance Criteria
- Each terminal status has unique copy and expected CTA.
- Partial vs total defeat behavior is visible to player without hidden rules.
- Abandon flow remains clearly user-initiated and reversible until confirmation.
