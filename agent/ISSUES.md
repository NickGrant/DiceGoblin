# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Encounter Content Pack

### Expand chaos reel catalogs

**Milestone:** Encounter Content Pack
**Status:** Open
**Priority:** Medium

#### Problem
Chaos encounters are battle-backed and documented, but the roadmap's full enemy-family, encounter-shape, and rule/reward reel breadth still needs authored catalog expansion.

#### Acceptance Criteria

- Enemy-family, encounter-shape, and rule/reward reels each contain ten enabled entries or documented launch equivalents.
- Entries are weighted and eligible by region where appropriate.
- Raw Chaos rewards remain gated behind Wrong Machine recovery.
- Backend tests or seed validation prove every enabled reel entry can finalize into a valid combat encounter.

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
