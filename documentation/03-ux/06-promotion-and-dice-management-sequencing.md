# Promotion and Dice Management Sequencing
----

Status: active  
Last Updated: 2026-03-04  
Owner: UX + Product + Frontend  
Depends On: `documentation/03-ux/02-warband-management.md`, `documentation/03-ux/05-unit-dice-details-acceptance.md`, `documentation/02-systems-mvp/02-units-and-progression.md`

## Purpose
- Define when players can modify squads, promote units, and manage dice.
- Keep run flow readable by separating read-only run state from editable management surfaces.

## Interaction Windows

### Between Runs (Home / Warband Context)
- Allowed:
  - Squad membership and formation edits.
  - Unit promotion actions (if requirements are met).
  - Dice equip/unequip and inventory management.
- Messaging:
  - Show as `Manage Squad`, `Promote`, and `Manage Dice` primary actions.
  - If no active run exists, all three actions are fully enabled.

### During Active Run - Map Screen
- Allowed:
  - Read-only unit and dice details inspection.
- Blocked:
  - Direct management mutations from map context.
- Messaging:
  - Surface a non-blocking hint: `Management changes are available at rest nodes.`

### Rest Node (Within Active Run)
- Allowed:
  - Full out-of-run management action set:
    - squad membership and formation updates,
    - promotion,
    - dice equip/unequip.
  - Rest-specific recovery effects.
- Contract:
  - Rest uses explicit workflow: `open -> edit -> finalize`.
  - Finalize consumes rest node and applies backend-authoritative auto-level pass.
  - Writes are all-or-nothing across run snapshot and saved squad state.

## Sequencing Rules
- Promotion checks are evaluated:
  - between runs,
  - and during open rest workflow.
- Dice management changes are blocked in active run outside rest workflow.
- Unit-details surface is the canonical entry for promotion actions.

## Error and State Handling
- If user attempts blocked management action outside rest during active run:
  - keep current screen stable,
  - show concise toast/banner explaining rest-only availability.
- If backend reports active run while user is on management screen:
  - keep read-only mode unless current context is rest workflow.

## Acceptance Criteria
- Promotion CTA is visible in unit details:
  - enabled between runs,
  - enabled at rest,
  - disabled in active-run non-rest context.
- Dice management actions are available between runs and at rest only.
- Rest UI includes explicit `Finalize Rest` action and summary of changes:
  - healed units with heal amounts,
  - progressed units (level/promotion deltas).
- Navigation labels consistently distinguish `Run actions` vs `Rest management`.
