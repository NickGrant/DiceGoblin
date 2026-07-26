# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Inventory Scale and Actions

### Add pagination to inventory collections

**Milestone:** Inventory Scale and Actions
**Status:** Open
**Priority:** High

#### Problem
Inventories for dice, units, and related collections will become unwieldy as player collections grow.

#### Acceptance Criteria

- Dice inventory supports pagination or an equivalent chunking control.
- Unit inventory supports pagination or an equivalent chunking control.
- Empty, filtered, and final-page states are clear and stable.
- Existing sort/filter behavior continues to work with pagination.

### Complete unlocked dice action affordances

**Milestone:** Inventory Scale and Actions
**Status:** Open
**Priority:** Medium

#### Problem
Dice inventory should avoid duplicate clutter and expose salvage only when the player has unlocked the Wrong Machine.

#### Acceptance Criteria

- Dice inspect modal includes salvage only after Wrong Machine unlock.
- Players cannot earn or salvage Raw Chaos until Wrong Machine is unlocked.
- Raw Chaos tracker appears in the controls area after Wrong Machine unlock.
- Rarity and "Raw Chaos ready" badges are removed from dice inventory tiles.
- Duplicate `.dg-proto-chip` information is removed where it does not add unique value.
