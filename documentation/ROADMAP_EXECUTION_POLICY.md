# Roadmap Execution Policy
----

Status: active
Last Updated: 2026-03-02
Owner: Product + Engineering
Depends On: `MILESTONES.md`, `ISSUES.md`, `AGENTS.md`

## Purpose
- Define milestone execution order and how the active milestone is selected.
- Enforce one current delivery lane at a time.
- Keep milestone transitions explicit and auditable.

## Execution Order
1. Milestone 9 - Product and Backlog Governance
2. Milestone 2 - Server-Side Battle Resolution
3. Milestone 3 - Run Progression and Attrition
4. Milestone 12 - Combat Determinism and Progression Integrity
5. Milestone 11 - QA Coverage and Automation
6. Milestone 4 - Encounter Flow UI
7. Milestone 5 - Unit and Dice Management
8. Milestone 6 - Playability and Stability Pass
9. Milestone 10 - Engineering Maintainability and Contracts
10. Milestone 13 - Player Experience and UX Flow

## Current Milestone Selection Rules
- Exactly one milestone may be `is_current: yes`.
- The current milestone must have `execution_window: open`.
- Prefer the earliest not-complete milestone in execution order unless:
  - a blocking dependency requires an upstream milestone to open first, or
  - the user explicitly overrides sequence.

## Milestone Open Rule
A milestone can be opened (`execution_window: open`) when:
- dependency blockers in earlier milestones are either complete or explicitly accepted as deferred risk,
- at least 3 issues in that milestone are actionable (`execution: active`, `ready: yes`) or ready to be made actionable,
- acceptance/verification expectations are documented.

## Milestone Close Rule
A milestone can be closed (`execution_window: closed`) when:
- all must-have issues are complete, archived, or explicitly deferred with rationale,
- no high-priority unresolved blockers remain in active scope for that milestone,
- milestone resolution is documented before archival.

## Override Rule
- The user may override milestone order and open state at any time.
- When overridden, update `MILESTONES.md` and briefly capture the rationale in `documentation/CHANGELOG.md`.
