# Promotion and Dice Management Sequencing
----

Status: active  
Last Updated: 2026-03-03  
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
  - Promotion.
  - Persistent squad membership edits.
  - Permanent dice reassignment.
- Messaging:
  - Surface a non-blocking hint: `Management changes are available outside active runs.`

### Rest Node (Within Active Run)
- Allowed:
  - Rest-node-specific run actions only (heal/recover actions when implemented).
  - Read-only unit and dice details.
- Blocked:
  - Promotion and inventory reconfiguration that changes persistent profile state.
- Messaging:
  - Show `Run-only adjustments` language to avoid implying full management access.

## Sequencing Rules
- Promotion checks are evaluated only in between-run management context.
- Dice management changes apply immediately to persistent profile state and are not exposed mid-run.
- Run UI must never imply that blocked actions are available at rest nodes.

## Error and State Handling
- If user navigates to a blocked action from an active-run entrypoint:
  - keep current screen stable,
  - show concise toast/banner explaining availability window,
  - offer one-tap navigation back to `Warband` after run completion.
- If backend reports active run while user is on management screen:
  - switch to read-only mode and refresh action enablement.

## Acceptance Criteria
- Promotion CTA is visible and enabled only when not in an active run.
- Dice management CTA is visible in run context but disabled with explicit reason text.
- Rest node UI does not expose persistent squad/promotion/dice mutation controls.
- Navigation labels consistently distinguish `Run actions` vs `Management actions`.

