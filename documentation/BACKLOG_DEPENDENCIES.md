# Backlog Dependencies
----

Status: active
Last Updated: 2026-03-02
Owner: Product + Engineering + QA
Depends On: `ISSUES.md`, `MILESTONES.md`, `documentation/ROADMAP_EXECUTION_POLICY.md`

## Purpose
- Define cross-milestone dependencies that drive execution order.
- Define issue-level dependency metadata usage (`blocked_by`, `enables`).
- Make Milestones 4-6 sequencing explicit.

## Cross-Milestone Dependency Map
- Milestone 9 -> Milestones 2, 3, 4, 5, 6, 10, 11, 12, 13
  - governance/schema/sequence rules should be stable before major execution.
- Milestone 2 -> Milestones 3, 4, 11, 12, 13
  - core battle/reward correctness is prerequisite for progression, UI flow, and deterministic QA.
- Milestone 3 -> Milestones 4, 6, 13
  - attrition/retry/failure rules must exist before encounter UX polish and stability pass.
- Milestone 4 -> Milestones 6, 13
  - encounter flow UI is prerequisite for playability validation and player-experience tuning.
- Milestone 5 -> Milestones 6, 13
  - unit/dice management surfaces are prerequisite for stability and onboarding clarity.
- Milestone 11 -> Milestones 6, 12
  - automation depth improves confidence for playability hardening and deterministic rules work.

## Issue Dependency Metadata Policy
- `blocked_by` and `enables` are optional fields in issue entries.
- Use exact issue titles in each list item for stable linking.
- Apply fields when:
  - execution order is non-obvious, or
  - multiple milestones share coupled behavior.
- Keep dependency scope minimal:
  - list direct blockers only (not full transitive chains).

## Milestones 4-6 Sequencing Policy
- Milestone 4 (Encounter Flow UI) starts after:
  - Milestone 2 battle/reward contracts are stable enough for encounter transitions and node status rendering.
- Milestone 5 (Unit and Dice Management) starts after:
  - acceptance criteria and payload contracts are stable for unit/dice details and formation behavior.
- Milestone 6 (Playability and Stability Pass) starts after:
  - core behaviors from Milestones 4 and 5 are implemented,
  - baseline QA gates from Milestone 11 are available for regression confidence.

## Operational Rules
- If an issue has unresolved blockers, keep:
  - `execution: deferred`
  - `ready: no`
- When blockers clear, update to:
  - `execution: active`
  - `ready: yes`
- If a dependency target is archived, retain the exact archived title to preserve traceability.
