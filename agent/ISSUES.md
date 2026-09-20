# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 1 - Mountains authored combat foundation + deterministic kobold adaptation

**Status:** In Progress
**Priority:** High

#### Current Architectural Review Finding

The Taunting Guard, Shield Set, and Dumb Luck corrections at `59c0b441ef36150fcf6d4507ca7f66215feaee09` are accepted. One Patient Aim semantic mismatch remains:

- **Patient Aim must use the established wounded threshold of 30% HP or lower.** The retained deterministic resolver's `isWounded()` definition is `current_hp <= floor(max_hp * 0.3)`. The new vNext `TargetResolver` currently awards the Patient Aim wounded weight whenever `current_hp < max_hp`, so even a unit at 99% HP is treated as wounded. The new focused test encodes a 50/100 target as wounded, which locks in the wrong behavior. Correct the shared targeting rule to the established <=30% threshold and update the focused test to prove both sides of the boundary (for example, 30% qualifies and 31% does not). Keep the existing marked/backline/previous-target weights and deterministic tie behavior unchanged.

No other implementation change is requested. Keep Package 1 In Progress and do not promote Package 2.

#### Problem

Milestone 5 proved the full Farm loop and its manual UAT passed on 2026-09-19. Milestone 6 must now prove that the accepted region/run/combat architecture is not Farm-specific.

The first package establishes canonical vNext Mountains combat content before making Mountains startable. Do not add Camp region selection or live Mountains run entry yet.

#### Goal

Create the canonical authored combat vocabulary for `region.mountains` and adapt the established kobold behavior to the deterministic vNext combat engine without introducing a parallel combat path.

Canonical enemy roster:
- `enemy_unit_type.kobold_skirmisher` — backline grunt; HP 18, Attack 6, Defense 2, Precision 6, Resolve 4.
- `enemy_unit_type.kobold_shieldbearer` — frontline grunt; HP 28, Attack 3, Defense 6, Precision 4, Resolve 6.
- `enemy_unit_type.kobold_sharpshooter` — backline elite; HP 22, Attack 9, Defense 3, Precision 7, Resolve 4.
- `enemy_unit_type.kobold_warchief` — player-facing name **Kobold Chief Engineer**; backline boss; HP 42, Attack 11, Defense 4, Precision 7, Resolve 5.

Use the existing kobold visual assets. Preserve the legacy `kobold_warchief` implementation identity for now; do not create a migration solely to rename that stable key.

#### Behavioral evidence

The retained prototype/docs are behavior evidence, not architecture authority:
- `documentation/03-content/03-enemy-types.md`
- `documentation/03-content/05-enemy-abilities.md`
- `backend/migrations/54_rebalance_kobolds_frogmen.sql`
- `backend/migrations/55_rebalance_mountains_swamps_encounters.sql`

Preserve the established role identities:
- Skirmisher: `bomb_toss`, `basic_attack_ranged`, `sharpshooter`.
- Shieldbearer: `basic_attack_melee`, `taunting_guard`, `shield_set`, `wall_of_scrap`, `unmoving`.
- Sharpshooter: `basic_attack_ranged`, `disarming_shot`, `aimed_shot`, `sharpshooter`, `clean_shot`.
- Chief Engineer: `bomb_toss`, `basic_attack_ranged`, `aimed_shot`, `sharpshooter`, `patient_aim`, `dumb_luck`.

Adapt these through the accepted vNext ability/content/engine boundaries. Reuse existing deterministic ability semantics where they already exist. Do not revive the prototype combat service or introduce a second resolver.

#### Authored Mountains encounters

Add canonical Mountains encounter definitions using these retained compositions:

1. **Kobold Warband I**
   - Shieldbearer at `(0,1)`
   - Skirmisher at `(2,0)`
   - Skirmisher at `(2,2)`

2. **Kobold Warband II**
   - Shieldbearer at `(0,1)`
   - Skirmisher at `(2,0)`
   - Sharpshooter at `(2,2)`

3. **Kobold Warband III**
   - Shieldbearer at `(0,0)`
   - Shieldbearer at `(0,2)`
   - Skirmisher at `(2,0)`
   - Sharpshooter at `(2,2)`

4. **Kobold Command**
   - Shieldbearer at `(0,1)`
   - Sharpshooter at `(1,0)`
   - Skirmisher at `(2,2)`
   - Chief Engineer at `(2,1)`

Use stable vNext encounter IDs under the Mountains namespace and `region_id: "region.mountains"`.

#### Architecture constraints

- JSON in Git remains canonical authored gameplay content.
- PHP remains authoritative for combat.
- Reuse the existing CombatSnapshotAssembler / deterministic vNext engine / playback model.
- Kobold-specific abilities may be server-only; do not leak hidden handler configuration into the browser projection.
- Do not create database-authored enemy/ability configuration for vNext.
- Do not add Mountains run generation, rewards, events, region-start authorization, Camp selection, or Swamps unlocking in this package.
- Do not implement Lizard Kin; kin restoration remains a later milestone.

#### Required tests

Prove:
- all four enemy definitions validate with the exact canonical stats/roles/abilities above;
- all required kobold ability definitions resolve through supported deterministic vNext handlers;
- all four Mountains encounters validate and assemble with the exact authored formations;
- representative combat for each encounter is deterministic for identical authoritative input;
- Chief Engineer is treated as Boss content without a Farm-specific engine branch;
- playback/snapshot data carries the correct stable enemy/art identities;
- server-only combat configuration remains absent from the generated client projection;
- existing Farm combat remains unchanged.

#### Verification

Run `npm run verify:package` plus focused content/combat tests for the new Mountains definitions. If DB-backed tests are introduced or affected, run the applicable Docker backend gate and report skipped counts.

#### Out of scope

- Mountains run graph;
- starting a Mountains run;
- Camp region selector;
- Mountains Loot/Rest/Boss rewards or Exit lifecycle;
- Swamps unlock;
- Lizard Kin;
- broader visual/UI overhaul.

#### Completion

Implement only Package 1. Leave it **In Progress** for architectural review and do not promote Package 2 or make Mountains playable.
