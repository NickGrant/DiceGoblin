# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Encounter Content Pack

## General Inventory and Consumables

### Add between-encounter unit healing consumables

**Milestone:** General Inventory and Consumables
**Status:** Open
**Priority:** Low

#### Problem
The generic item foundation can support consumables, but unit healing items have not been implemented. They should help between encounters without adding mid-combat player input.

#### Acceptance Criteria

- Healing consumable definitions exist in the generic item catalog.
- Players can use healing consumables only outside active combat resolution.
- Item spending is backend-authoritative, transactional, and idempotent where retries are possible.
- Frontend inventory or run surfaces expose the action where it naturally belongs.

### Add player energy recovery consumables

**Milestone:** General Inventory and Consumables
**Status:** Open
**Priority:** Low

#### Problem
Energy recovery consumables are roadmap stretch work and should wait until the energy economy can absorb them safely.

#### Acceptance Criteria

- Energy consumable definitions exist in the generic item catalog.
- Use rules respect energy caps and do not bypass intended pacing.
- Item spending and energy restoration are backend-authoritative.
- Tests cover cap behavior, insufficient item cases, and duplicate requests.
