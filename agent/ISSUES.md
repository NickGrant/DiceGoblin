# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Kin Pool and Balance Completion

### Gate random kin rewards by owned lineages

**Milestone:** Kin Pool and Balance Completion
**Status:** Open
**Priority:** High

#### Problem
Pig Kin reconstruction is complete, but random recruitment and unit reward pools must be verified so accounts only roll kin they own unless a reward explicitly grants a specific lineage.

#### Acceptance Criteria

- New random unit grants use Basic Goblin plus account-owned lineages as the default eligible kin pool.
- Explicit reward payloads may still grant a specific kin when authored to do so.
- Frontend profile/debug surfaces continue to show owned lineages clearly.
- Backend tests cover default accounts, Pig Kin unlocked accounts, and explicit kin grants.

### Run kin balance simulation review

**Milestone:** Kin Pool and Balance Completion
**Status:** Open
**Priority:** Medium

#### Problem
Kin should add identity without making Basic Goblins obsolete or overpowering class and promotion choices. The available simulation tooling should be used before adding more lineages.

#### Acceptance Criteria

- Representative simulations compare Basic Goblin and Pig Kin units in Farm, Mountains, and Swamps contexts.
- Results identify any stat/passive tuning risks before more kin are added.
- Findings are documented with recommended balance changes or a clear no-change decision.

### Plan legacy splice storage retirement

**Milestone:** Kin Pool and Balance Completion
**Status:** Open
**Priority:** Medium

#### Problem
Player-facing copy now favors kin and lineage terminology, but legacy `splice_variant` storage and API compatibility remain. A compatibility-aware migration plan is needed before renaming durable fields.

#### Acceptance Criteria

- Current `splice_variant` storage/API usage is inventoried.
- A forward migration and response compatibility plan is documented.
- No player-facing UI introduces new "splice" terminology.
- The plan names tests required before any storage rename ships.

## Encounter Primitive Framework

### Define hazard and shrine effect primitives

**Milestone:** Encounter Primitive Framework
**Status:** Open
**Priority:** High

#### Problem
Hazards and shrines should grow from a reusable effect vocabulary instead of bespoke one-off behavior. The framework needs backend-owned primitives before the content pack is seeded.

#### Acceptance Criteria

- Hazard primitive vocabulary supports HP attrition, temporary modifiers, currency/item pressure, route pressure, and kin-flavored mitigations.
- Shrine primitive vocabulary supports small rewards, cleansing, bargains, reroutes, and controlled risk.
- Primitive definitions are data-driven or code-cataloged consistently with seed ownership rules.
- Backend tests cover representative primitive resolution and idempotency.
- Documentation explains authoring constraints and player-facing expectations.

### Populate hazard nodes from authored rules

**Milestone:** Encounter Primitive Framework
**Status:** Open
**Priority:** Medium

#### Problem
Hazard node placement exists, but regions need authored population rules so hazards can be selected intentionally by biome, depth, and progression context.

#### Acceptance Criteria

- Hazard node selection respects region eligibility and weighting.
- Generated runs remain connected and preserve existing boss, rest, loot, shrine, chaos, and exit guarantees.
- Fallback behavior is documented for regions with sparse hazard catalogs.
- Backend generator coverage protects the placement contract.

## Encounter Content Pack

### Seed initial hazard catalog

**Milestone:** Encounter Content Pack
**Status:** Open
**Priority:** Medium

#### Problem
The July roadmap calls for ten authored hazards. The content pack should seed enough variety for early regions without creating ten bespoke backend systems.

#### Acceptance Criteria

- Ten hazard definitions are seeded or cataloged with stable slugs.
- Each hazard uses approved hazard primitives.
- Region eligibility, weight, player-facing title, and result copy are authored.
- Tests or seed validation prove all enabled hazards resolve through supported primitives.

### Seed initial shrine catalog

**Milestone:** Encounter Content Pack
**Status:** Open
**Priority:** Medium

#### Problem
Shrine encounters exist, but the roadmap calls for ten authored shrine definitions with distinct bargains, rewards, or risks.

#### Acceptance Criteria

- Ten shrine definitions are seeded or cataloged with stable slugs.
- Each shrine uses approved shrine primitives.
- Region eligibility, weight, player-facing title, and result copy are authored.
- Tests or seed validation prove all enabled shrines resolve through supported primitives.

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
