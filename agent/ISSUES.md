# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish Warband persistence foundation

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 1 intentionally stopped at account-level player state. Milestone 2 now needs a durable normalized persistence model for real player-owned units, dice, saved squads, ability loadouts, and exact dice bindings before APIs or Phaser configuration screens are built. Extend the clean vNext baseline to the accepted Warband storage model without reviving prototype catalog tables, `teams` compatibility, starter-pack provisioning, or later run/progression systems.

#### Required Context
- `documentation/07-development-path/vnext-storage-model.md` — canonical unit, dice, squad, loadout, lifecycle, and active-squad persistence responsibilities
- `documentation/02-systems/warband-and-formation.md` — saved squads, nine positions, active squad semantics, terminology
- `documentation/02-systems/ability-loadouts-and-dice-binding.md` — durable ability order and exact die binding model
- `documentation/02-systems/dice-profiles-and-aspects.md` — dice instance/profile identity boundary
- `documentation/02-systems/unit-stat-advancement.md` — unit level/XP vs authored derived stats
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — prototype Unit/Dice/Team repository reuse/removal guidance
- Current `backend/migrations/vnext_baseline.sql` and vNext database baseline/integration tests

Inspect prototype `UnitRepository`, `DiceRepository`, `TeamRepository`, and related tests only for useful ownership/constraint/locking evidence. Their schemas and `team` compatibility terminology are not authoritative.

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Extend the fresh vNext baseline rather than replaying the prototype migration chain. There is still no current player/runtime migration requirement on this branch.
- Add the accepted Warband persistence domains needed by Milestone 2:
  - `unit_instances`
  - `unit_promotions`
  - `unit_abilities`
  - `unit_ability_loadout`
  - `dice_instances`
  - `unit_ability_dice`
  - `squads`
  - `squad_units`
  - nullable `user_state.active_squad_id` or the equivalent accepted active-squad reference.
- Runtime rows reference authored unit type, kin, ability, and dice profile definitions using stable string IDs. Do not recreate SQL catalogs or database foreign keys to authored definitions that live in JSON.
- `unit_instances` owns player/unit identity, current unit type, kin, display name, level, XP, lifecycle status, and ordinary lifecycle timestamps/operational fields as required. Do not persist derived combat stats such as HP/Attack/Defense/Precision/Resolve as instance columns merely because prototype tables did.
- `unit_promotions` preserves promotion history without making promotion implementation part of this package.
- `unit_abilities` stores durable per-unit ability ownership. Capstones do not receive a separate persistence domain.
- `unit_ability_loadout` stores the equipped ordered ability configuration. Persist only configuration identity/order required by the accepted model; authored ability mechanics remain JSON.
- `dice_instances` remains intentionally small: ownership, die size, authored profile ID, lifecycle status, and ordinary lifecycle metadata. Do not duplicate material, rarity, or aspect catalogs onto each instance.
- `unit_ability_dice` stores the exact unit + ability + slot -> owned die relationship. Add only constraints that are unambiguously part of the accepted storage contract; leave gameplay eligibility/duplicate-use rules that depend on the complete configuration to later application validation rather than over-constraining the schema speculatively.
- `squads` uses vNext `squad` terminology and supports multiple named saved squads per user.
- `squad_units` represents the fixed positions `0` through `8`, enforces one occupant per position, and prevents the same unit from occupying multiple positions within the same squad. A unit may belong to multiple different saved squads.
- Active-squad storage is nullable and does not manufacture a default squad for a fresh account. Account registration remains the Milestone 1 behavior; production starter-unit/dice/squad provisioning is deferred to onboarding in Milestone 12.
- Preserve database-level ownership/lifecycle referential integrity where appropriate without trying to encode cross-row authored-content validation in MySQL.
- Do not add runs, active-run lock tables, Shop/economy tables, promotion transactions, unit stat-growth persistence, inventory, grants/rewards, or idempotency tables merely because later milestones will need them.
- Do not expose new player-facing API routes in this package.
- Do not modify Phaser/Angular gameplay presentation in this package.
- Add focused fresh-schema/database tests covering table inventory, important foreign keys/unique/check constraints, nullable active-squad behavior, nine-position bounds, same-squad duplicate-unit prevention, and representative valid/invalid Warband rows.
- Existing Milestone 1 account/bootstrap/database tests must remain valid. Bootstrap may continue returning `active_squad: null` until a later package implements Warband reads/integration.

#### Completion
Run the relevant context/docs checks plus fresh-database and backend database tests, including the repository-supported Docker path where required for real MySQL constraint behavior. Run broader gates only where this schema change makes them relevant; do not repeatedly run unrelated visual captures during a persistence-only package.

Leave this package **In Progress** for architectural review. Do not promote or begin package 2 in the same coding-agent change.
