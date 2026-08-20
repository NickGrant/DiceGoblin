---
Title: "Systems Documentation"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - documentation/README.md
Category: 02-systems
Tags:
  - systems
  - index
---

# Systems Documentation

## Purpose

This README is the canonical inventory and entry point for Dice Goblins gameplay systems.

- Every meaningful gameplay system belongs in this inventory even when its canonical documentation is missing.
- A missing document must be visible as a documentation gap rather than making the system disappear from the documentation map.
- System documents define rules, lifecycles, resolution, progression, and cross-system behavior. Content catalogs, UX documents, and technical contracts support those rules but do not replace them.
- This index separates current system contracts, approved target-state contracts, missing canonical coverage, legacy references, and future concepts.

## Documentation States

| State | Meaning |
| --- | --- |
| `Canonical Current` | Default system contract for behavior intended to describe the current implementation. If runtime behavior differs, reconcile the document. |
| `Canonical Target` | Approved target-state contract. Runtime may still contain documented migration drift. |
| `Missing Canonical` | The system is present in the current game, implementation, or active product surface, but no canonical system contract covers it adequately yet. |
| `Legacy Reference` | Historical MVP, evaluation, or rework material. Useful for context, but it does not override canonical documents or current implementation evidence. |
| `Future / Conceptual` | Design direction that is not part of the current gameplay contract. |

`Missing Canonical` describes documentation coverage, not implementation status.

## Naming and Navigation

Active system documents use semantic filenames rather than numeric prefixes. Directory order is not system priority, implementation order, or recommended reading order.

The files under `mvp-reference/` retain their numeric prefixes because those names are historical identifiers. Their numbers have no current ordering or status meaning, and duplicate numbers do not need to be repaired simply for sorting.

Start here, find the system, then follow its canonical document or supporting evidence.

## System Inventory

### Units and Warband

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Warband and squad composition | `Missing Canonical` | `documentation/04-ux/02-warband-management.md`; current Warband and squad routes/runtime |
| Unit naming | `Canonical Current` | `unit-naming.md` |
| Unit stats and advancement | `Canonical Current` | `unit-stat-advancement.md` |
| Unit promotion | `Missing Canonical` | `backend/src/Services/PromotionService.php`; `mvp-reference/10-promotion-structure-evaluation.md` |
| Ability loadouts and ability unlock progression | `Missing Canonical` | current combat/runtime ability model; `mvp-reference/09-ability-loadout-combat-rework-plan.md`; `mvp-reference/11-unit-progression-rework.md` |
| Formation and positioning | `Missing Canonical` | current combat targeting/runtime and warband UX |

### Combat

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Combat lifecycle and deterministic resolution | `Canonical Current` | `combat-resolution.md` |
| Ability execution and target resolution | `Missing Canonical` | `backend/src/Combat/Engine/`; current ability and targeting implementation |
| Damage, combat modifiers, and status effects | `Missing Canonical` | `backend/src/Combat/Engine/`; `mvp-reference/07-combat-math-and-modifiers.md` |
| Enemy action selection | `Missing Canonical` | current combat engine and enemy decision implementation |

### Dice

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Permanent dice identity and material model | `Canonical Target` | `dice-material-model.md` |
| Dice acquisition and ownership | `Missing Canonical` | current reward/inventory implementation; `mvp-reference/01-dice-system.md` |
| Dice equipment and loadouts | `Missing Canonical` | current Dice management surface and combat ability-slot model |
| Dice rolling, spending, and material effect resolution | `Missing Canonical` | `combat-resolution.md`; current combat Dice implementation |
| Dice salvage and Raw Chaos | `Canonical Current` | `dice-salvage-and-raw-chaos.md` |

### Runs and Encounters

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Run lifecycle, start/resume, completion, and failure | `Missing Canonical` | `backend/src/Services/RunLifecycleService.php`; `mvp-reference/05-save-and-resume-scope.md`; `mvp-reference/06-run-resolution-scope.md` |
| Run graph and node generation | `Canonical Current` | `run-node-generation.md` |
| Encounter/node resolution | `Missing Canonical` | current run-node controllers/resolvers; `mvp-reference/03-encounter-scope.md` |
| Dialogue flow and gating | `Canonical Current` | `dialogue-flow-determination.md` |
| Hazard severity and downsides | `Canonical Current` | `hazard-severity-and-downsides.md` |
| Rest and recovery nodes | `Missing Canonical` | current run rest route and run-node resolution behavior |
| Shrines and temporary run effects | `Missing Canonical` | current shrine generation/claim behavior in `loot-determination.md` and run lifecycle implementation |
| Chaos encounters | `Canonical Current` | `chaos-encounter-reels.md` |

### Rewards and Economy

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Loot determination | `Canonical Current` | `loot-determination.md` |
| Reward materialization and owned-item inventory | `Missing Canonical` | `backend/src/Services/UserAssetGrantService.php`; `backend/src/Services/ItemInventoryService.php` |
| Consumables and inventory use | `Canonical Current` | `consumables-and-inventory-use.md` |
| Teeth and player currencies | `Missing Canonical` | current player-state, reward, Academy, and shop behavior |
| Shop and purchasing | `Missing Canonical` | current Shop route and feature gate |
| Energy and regeneration | `Missing Canonical` | `backend/src/Services/EnergyService.php` |
| Objectives and demo guidance | `Canonical Current` | `objectives-and-demo-guidance.md` |
| Bounty Board | `Canonical Current` | `bounty-board.md` |

### Progression, Unlocks, and Collection

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Academy and unit-type unlocks | `Missing Canonical` | `backend/src/Services/AcademyService.php`; `mvp-reference/12-academy-and-feature-unlocks.md` |
| Feature unlocks and progression gates | `Missing Canonical` | current unlock services and route feature guards; `mvp-reference/12-academy-and-feature-unlocks.md` |
| Region/biome access and progression | `Missing Canonical` | current Regions surface, run creation rules, and lore/content progression references |
| Codex discovery and ownership | `Canonical Current` | `codex-ownership-and-discovery.md` |
| Kin reconstruction and first ownership | `Canonical Target` | `kin-reconstruction.md` |

### Multiplayer

| System | Documentation | Canonical document or current reference |
| --- | --- | --- |
| Multiplayer philosophy | `Future / Conceptual` | `multiplayer/multiplayer-philosophy.md` |
| Union influence | `Future / Conceptual` | `multiplayer/union-influence.md` |

## Existing Canonical System Documents

- `unit-naming.md`
- `unit-stat-advancement.md`
- `combat-resolution.md`
- `dialogue-flow-determination.md`
- `run-node-generation.md`
- `loot-determination.md`
- `hazard-severity-and-downsides.md`
- `dice-material-model.md`
- `kin-reconstruction.md`
- `objectives-and-demo-guidance.md`
- `consumables-and-inventory-use.md`
- `codex-ownership-and-discovery.md`
- `chaos-encounter-reels.md`
- `bounty-board.md`
- `dice-salvage-and-raw-chaos.md`

The inventory above, not this shorter list, defines the expected breadth of systems documentation.

## Cross-Layer Ownership

Use the following boundary when deciding where new information belongs:

- `documentation/02-systems/` owns gameplay rules, resolution, lifecycles, progression rules, and interactions between systems.
- `documentation/03-content/` owns authored catalogs and concrete content such as unit types, kin, enemies, items, materials, encounters, rewards, and Codex entries.
- `documentation/04-ux/` owns player-facing presentation, interaction, information hierarchy, onboarding, and page behavior.
- `documentation/05-technical/` owns APIs, storage, schemas, frontend state, implementation architecture, and compatibility/migration surfaces.
- Implementation and tests remain evidence for what currently runs.

When implementation shape matters, pair the system contract with:

- `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
- `documentation/05-technical/03-backend-api-contracts.md`
- `documentation/05-technical/04-data-model.md`
- `documentation/05-technical/09-seed-catalog-ownership.md`

## Known Target-State Drift

### Dice

The approved target model is size plus material. Runtime storage and payloads may still expose independent rarity, affix definitions, per-instance affixes, or affix-based value/Codex behavior. Those fields are migration and compatibility surfaces, not competing target-state identity.

### Kin Reconstruction

The approved target model is repeatable unit production with first-ownership side effects. Runtime behavior may still contain legacy one-time lineage gates, legacy costs, incomplete request-level idempotency, or lineage-oriented response shapes. Those behaviors do not override `kin-reconstruction.md`.

## Legacy and Future References

- `mvp-reference/` preserves older MVP contracts, evaluations, and rework plans. Read it only when current canonical coverage is absent or historical context is required.
- `multiplayer/` contains future multiplayer design direction and is not part of the current gameplay critical path.

## Maintenance Rules

- Add every new gameplay system to this inventory when it becomes an active design or implementation concern, even if its system document does not exist yet.
- Do not remove a system from the inventory merely because its document is incomplete, stale, or being rewritten.
- When a canonical document is created, replace `Missing Canonical` with its status and path rather than creating a second competing inventory.
- Prefer semantic filenames for active system documents. Do not introduce numeric prefixes to imply order.
- Keep content, UX, technical, and legacy references linked from the system row instead of copying their responsibilities into this folder.
- If implementation and a `Canonical Current` document disagree, reconcile the document.
- If implementation and a `Canonical Target` document disagree, record and resolve the implementation drift deliberately.
