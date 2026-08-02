---
Title: "Ability Loadout, Dice Binding, and Combat Rework Plan"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/mvp-reference/00-combat-system.md
  - documentation/02-systems/mvp-reference/01-dice-system.md
  - documentation/02-systems/mvp-reference/02-units-and-progression.md
  - documentation/05-technical/04-data-model.md
  - documentation/04-ux/02-warband-management.md
Category: 02-systems
Tags:
  - systems
  - mvp-reference
---

# Ability Loadout, Dice Binding, and Combat Rework Plan

## Purpose
- Capture the approved redesign of unit abilities, dice binding, combat scheduling, promotion inheritance, and unit naming before implementation begins.
- Define what changes, what remains true, and what must be migrated across backend, frontend, save data, and UX.
- Provide a phased implementation plan so the rework can be executed deliberately instead of piecemeal.

## Status Boundary
- This document is the rollout and migration-planning companion for the rework.
- The current authoritative target contract now lives in:
  - `documentation/02-systems/mvp-reference/00-combat-system.md`
  - `documentation/02-systems/mvp-reference/01-dice-system.md`
  - `documentation/02-systems/mvp-reference/02-units-and-progression.md`
  - `documentation/05-technical/04-data-model.md`
  - `documentation/04-ux/02-warband-management.md`
- Use this file for sequencing, migration concerns, and implementation slicing rather than as the primary rules source.

## Confirmed Product Decisions

### 1. Ability Equips
- Units now equip abilities for combat resolution.
- Each unit has a 20-point equip budget.
- Ability equip cost is exactly equal to that ability's speed.
- Duplicate equips are allowed.
- No separate hard cap on number of equipped abilities is planned beyond the 20-point budget.
- In practice, this should keep most units at 5 or fewer equipped abilities.

### 2. Dice Binding
- Dice are no longer consumed from a shared unit pool.
- Dice bind to the base ability instance, not to individual copies of that ability in the loadout.
- An ability exposes as many dice slots as its roll behavior consumes.
- If the same ability is equipped multiple times, every copy uses the same configured dice slots for that base ability.
- Empty slots resolve as `1` whenever that slot is rolled.
- If an ability resolves multiple rolls separately, slot order is authoritative.

### 3. Combat Scheduling
- Combat still runs on a 20-tick round.
- Units trigger actions from their equipped abilities.
- Equipped abilities no longer trigger on multiples of speed.
- Instead, each round first queues the equipped loadout once at the cumulative ticks produced by loadout order.
- If the initial pass leaves unused ticks, the resolver fills them with repeatable hostile abilities that still fit, checking equipped order from the top again each time.
- Self-targeted and ally-targeted utility abilities do not repeat as filler actions.
- Example:
  - Loadout `6, 6, 8` fires at ticks `6, 12, 20`
  - Loadout `8, 6, 6` fires at ticks `8, 14, 20`
  - Loadout `6, 6, 6` fires at ticks `6, 12, 18`
  - Loadout `4, 10` with `basic_attack_melee` then `shield_up` fires at ticks `4, 14, 18`
  - Loadout `4, 8` with `basic_attack_melee` then `heavy_strike` fires at ticks `4, 12, 16, 20`
- The schedule resets at the start of each new round.
- The round still ends at tick 20.
- Enemies also use the new loadout-based cumulative scheduling format.
- Enemy loadouts are authored in enemy database definitions.
- All enemies of the same type use the same authored loadout.
- No per-enemy-instance loadout customization is planned.

### 4. Promotion
- Promotion still advances a unit by one tier and still respects current tier caps and promotion requirements.
- Promotion no longer replaces the unit's ability history.
- A unit keeps all previously accumulated abilities.
- Tier 2 units have Tier 1 abilities plus the selected Tier 2 package.
- Tier 3 units have Tier 1 + Tier 2 + Tier 3 abilities accumulated together.
- On promotion, the player may:
  - promote to the next type in the authored chain, or
  - promote sideways into a different type at the tier being exited, subject to prior path eligibility.
- Example:
  - a Tier 1 bruiser may promote to the Tier 2 bruiser chain target or sideways into another Tier 1-exit option such as guardian, marksman, bannerbearer, or saboteur
  - a bruiser cannot jump into a later bannerbearer-derived branch such as warcaller unless it previously became bannerbearer

### 5. Naming
- Units should have player-facing names.
- Names are labels only and must not be used as system identifiers.
- Duplicate names are allowed.
- Players rename units from the unit-details screen.
- Units should receive generated names at creation time.
- Name generation only needs enough variety to support a few hundred distinct-feeling names.

### 6. Starter Unit Seeding
- Initial player units should be seeded with a default equipped ability loadout.
- All starter-equipped ability slots should be filled with common `d4` dice.
- Starter units should begin as valid examples of the new system rather than presenting empty configuration.

## Rule Changes by System

### Dice System Changes
- Remove the current shared per-unit combat dice pool model.
- Remove dice consumption and pool refresh logic from combat resolution.
- Replace it with per-ability slot configuration.
- Each slot holds either:
  - an equipped die instance, or
  - an implicit empty-slot value of `1`.
- Dice affixes now modify the owning ability slot resolution rather than an abstract shared pool roll.
- Repeated copies of the same equipped ability reuse the same slot configuration.

### Ability System Changes
- The concept of "available abilities on a unit type" splits into:
  - cumulative unlocked ability catalog for the unit, and
  - equipped combat loadout for the unit.
- Passive abilities are no longer implicitly always-on just because the unit type owns them.
- The rework must define whether all inherited abilities are equip-eligible, passive-capable, or active-only.
  - Planning assumption: every combat-relevant ability that can trigger must be equipped to participate in scheduling.
- Ability ordering becomes a first-class gameplay property because order determines firing ticks.

### Combat Engine Changes
- Replace the current `tick % speed === 0` trigger model for player units.
- Replace the current `tick % speed === 0` trigger model for enemy units as well.
- Introduce a per-unit round schedule built from equipped ability order.
- Preserve deterministic tick-based processing and round length.
- Preserve same-tick ordering rules, but update them to use equipped ability instances rather than unit type defaults.
- Enemy type definitions must gain authored equipped-loadout data so enemy turns follow the same cumulative schedule model as player units.

### Progression Changes
- Promotion becomes cumulative rather than replacement-based for abilities.
- Promotion destination validation now depends on historical path state, not only current type and next-tier mapping.
- A unit's long-term identity becomes more persistent:
  - custom name persists
  - accumulated ability catalog persists
  - promotion path history persists
  - equipped loadout persists unless changed by the player

## Data Model Impact Plan

### New or Restructured Data Concerns
- Unit custom name:
  - `unit_instances` should own a player-facing name field if it does not already exist in a persistent way.
- Ability catalog:
  - unit types still define authored ability packages
  - unit instances need a way to persist cumulative unlocked abilities across promotions
- Equipped loadout:
  - unit instances need a persisted ordered list of equipped abilities for combat
- Ability dice slots:
  - per-unit, per-ability-slot die assignments must be persisted
  - because the binding is on the base ability instance, repeated copies of the same ability share slot assignments
- Promotion path history:
  - unit instances or promotion history records must support validating sideways promotion eligibility
- Enemy loadouts:
  - enemy templates need persisted authored equipped-ability order using the same scheduling semantics as player units
- Starter grants:
  - starter pack/bootstrap logic must seed units with default equipped abilities and common `d4` ability-slot assignments

### Data Migration Requirements
- Existing unit progression and promotion data must be migrated without destroying owned units.
- Existing equipped dice currently attached to the unit need a deterministic migration policy.
- Existing unit-type ability sets need conversion into:
  - inherited/unlocked ability catalog
  - default equipped loadout where possible
- Existing enemy template data needs conversion into authored equipped loadouts for the new scheduler.
- Existing runs and run snapshots are high risk.
  - safest likely approach: invalidate active runs during rollout or add an explicit migration guard that blocks resume on incompatible run snapshots.

## Backend Implementation Lanes

### Lane 1. Ability and Loadout Domain Model
- Define canonical ability metadata needed by the new system:
  - speed
  - point cost
  - slot count
  - slot-resolution mode
  - targeting
  - effect semantics
- Add server-side validators for:
  - unit equip budget
  - duplicate ability equips
  - slot assignment legality
  - enemy authored-loadout validity

### Lane 2. Dice-to-Ability Binding
- Replace `unit_dice`-style combat lookup with ability-slot lookup. Complete.
- Remove legacy `unit_dice` storage once ability-slot binding is canonical. Complete.
- Add starter-loadout seed behavior that assigns common `d4` dice into all default starter ability slots.

### Lane 3. Combat Scheduler Rewrite
- Build cumulative per-round schedules from equipped ability order.
- Build enemy schedules from authored enemy loadouts using the same cumulative per-round rules.
- Ensure deterministic ordering for:
  - same-unit abilities on the same tick
  - different units acting on the same tick
- Update battle-log payloads to identify:
  - which equipped ability instance fired
  - which slot(s) were read
  - whether an empty slot contributed `1`

### Lane 4. Promotion Rewrite
- Add promotion destination resolution based on current type and path history.
- Preserve cumulative abilities on the unit instance.
- Maintain existing promotion costs and item requirements unless intentionally revised later.

### Lane 5. Naming
- Add generated-name assignment during unit creation and unit grant flows.
- Add rename endpoint or expand existing unit update contract.

## Frontend and UX Impact Plan

### Warband / Unit Details
- The unit-details screen becomes the main place for:
  - unit renaming
  - viewing cumulative unlocked abilities
  - equipping ordered combat loadout
  - assigning dice to ability slots
- The current "manage dice" concept likely collapses into ability-centric slot editing.

### Inventory
- Dice inventory must show where a die is equipped by ability slot, not only by unit.
- Unequip flows must understand shared base-ability slot binding.

### Combat Viewer / Logs
- Combat logs must explain:
  - which equipped ability fired
  - which slot values were used
  - when empty slots contributed `1`
- Since ability order matters, the player needs a readable pre-combat summary of scheduled timing.

### Promotion UX
- Promotion UI must support destination choice.
- UI must surface:
  - straight promotion option
  - sideways eligible options
  - ineligible branches and why they are blocked
- Ability inheritance should be visible before confirm so the player understands what carries forward.

## Risks
- Save compatibility risk is high because this changes the identity of combat inputs.
- Migration risk is high because unit dice, abilities, promotions, and active runs all intersect.
- Combat readability risk is high because repeated copies of the same ability can now share slots but fire at different ticks.
- Balance risk is high because changing from repeated modulo triggers to cumulative once-per-round triggers will significantly alter throughput.
- UX risk is medium-high because loadout order now matters strategically and must be editable clearly.

## Recommended Delivery Phases

### Phase 0. Planning and Contract Lock
- Finalize system rules in docs.
- Decide migration treatment for active runs.
- Decide API contract shape for unit renaming and loadout editing.

### Phase 1. Data Model and API Foundations
- Add persistent unit naming.
- Add unlocked ability storage.
- Add equipped ability order storage.
- Add ability-slot dice binding storage.
- Add enemy-template equipped-loadout storage.
- Add migration scripts for legacy units and dice.

### Phase 2. Unit Details / Management UX
- Rework the unit-details screen around:
  - naming
  - loadout order
  - ability costs
  - slot editing
- Update inventory references and return flows.
- Ensure starter units arrive with meaningful default loadouts and common `d4` slot fills so the first editable state is coherent.

### Phase 3. Combat Engine Rewrite
- Switch player scheduling to cumulative per-round loadouts.
- Switch enemy scheduling to cumulative per-round authored loadouts.
- Remove shared dice-pool consumption from player combat.
- Update logs and replay contracts.
- Add deterministic combat coverage for the new schedule model.

### Phase 4. Promotion Rewrite
- Add cumulative ability inheritance.
- Add sideways promotion validation and selection.
- Update promotion UI and backend contracts.

### Phase 5. Cleanup and Legacy Removal
- Remove obsolete unit-dice assumptions from frontend and backend.
- Remove stale documentation, APIs, and migration shims once save compatibility decisions are complete.

## Acceptance Targets for the Rework
- A unit can equip duplicate abilities as long as total speed cost is 20 or less.
- A repeated equipped ability uses the same dice-slot configuration on every copy.
- Empty slots always resolve as `1`.
- A 20-tick round reproduces the cumulative scheduling examples exactly.
- Enemy types also resolve on authored cumulative loadouts rather than modulo triggers.
- Promotion preserves cumulative ability access and enforces sideways path eligibility.
- Units receive generated names and players can rename them from unit details.
- Starter units begin with a default equipped loadout and common `d4` dice assigned into those starter ability slots.
- Combat logs make slot and timing behavior understandable to testers.

## Out of Scope for This Rework
- Unique-name enforcement
- Advanced procedural naming
- Ability editing from the warband hub list directly
- Rebalancing every authored ability at planning time
- Multiplayer implications

## Immediate Follow-Up Documentation Needed During Implementation
- Add or revise API contract docs for unit rename, loadout update, and promotion destination selection.
- Add concrete schema docs once final table and column names are committed.
- Trim or archive this planning document once rollout sequencing is no longer needed.
