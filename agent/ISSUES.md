# Active Execution Issue

## Milestone 4 Package 1 - Canonical combatant stats + authoritative run HP initialization

**Status:** Open
**Priority:** High

### Purpose
Milestone 3 passed manual UAT on 2026-09-15. Milestone 4 begins by closing the deliberate M3 combat-state gap before adapting the combat engine.

`run_unit_state.current_hp` was intentionally left nullable because vNext had not yet established canonical unit stat math. The current authored unit types now provide `base_stats` and `growth_per_level`, and the retained prototype `UnitProgressionService` confirms the useful level-scaling behavior to preserve. Establish that behavior as a small vNext domain rule and make newly-created runs begin with authoritative full HP.

This package is **not** the combat engine package. Do not invent damage, healing, targeting, statuses, dice combat effects, battle events, or battle persistence here.

### Canonical stat rule
For each of the five current combat stats:

`resolved_stat = base_stat + growth_per_level * (level - 1)`

Stats:
- HP
- Attack
- Defense
- Precision
- Resolve

Rules:
- unit level is the persisted authoritative `unit_instances.level` and must be at least 1;
- HP base value must be at least 1;
- Attack/Defense/Precision/Resolve base values must be non-negative integers;
- all five growth values must be non-negative integers;
- level 1 resolves exactly to authored base stats;
- resolved HP is the unit's max HP for this package;
- do not add Speed;
- do not add random variance or implicit tier scaling;
- do not incorporate kin, passive abilities, dice, run modifiers, combat statuses, or other future modifiers into this **base level-stat resolver**. Those later layers must remain explicit rather than silently changing this rule.

Do not recover or introduce an XP curve/max-level policy in this package. XP/progression remains later work.

### Required implementation boundary
Create a small infrastructure-free domain value/result + resolver for the five resolved stats. It may consume a normalized authored unit-type stat definition plus level, but it must not own PDO, repositories, HTTP, or `ContentRegistry` traversal.

Application/integration code may use `ContentRegistry` to obtain the unit type definition and then invoke the pure resolver.

Do not reuse `backend/src/Services/UnitProgressionService.php` as the vNext domain boundary. It is retained prototype evidence and uses old `max_hp`/separate-growth inputs. Preserve only the level-scaling behavior needed here.

### Authored-content validation
The canonical checked-in `unit_type` definitions must be sufficient for the resolver. Extend structural/semantic content validation as needed so every vNext `unit_type` fails validation before runtime if:
- any of the five `base_stats` keys is absent or not an integer;
- any of the five `growth_per_level` keys is absent or not an integer;
- HP base is below 1;
- another base stat is below 0;
- any growth value is below 0.

Do not add SQL unit/stat catalogs. Do not copy resolved stats into authored generated bundles beyond the existing exposure model merely for convenience.

### Authoritative run HP initialization
Update new-run creation so every participating `run_unit_state` row is inserted with `current_hp = resolved max HP` for that owned unit at its persisted level.

Requirements:
- resolve each participating unit from its authoritative owned instance (`unit_type_id`, level) plus canonical authored unit type;
- fail the run-start transaction if a participating unit cannot be resolved coherently; do not spend Energy or leave a partial run;
- preserve existing active-squad/formation/loadout validation and ownership behavior;
- persist HP inside the existing run-start transaction;
- preserve start-run idempotency: a retry with the same finalized key returns the same result and does not recreate/reinitialize the run or spend Energy twice;
- do not expose new HP fields in the compact run-start response or bootstrap unless an existing contract already requires them;
- the existing current-run aggregate may continue returning participating run-unit state, now with a concrete integer `current_hp` for newly-created runs.

Because vNext has no player/runtime data migration obligation, tighten the fresh baseline `run_unit_state.current_hp` from its temporary nullable M3 form to a non-null non-negative integer if that is the cleanest invariant. Continue using the single `backend/migrations/vnext_baseline.sql`; do not create a migration chain or backfill path.

Do **not** persist max HP, Attack, Defense, Precision, Resolve, or a combat-stat snapshot in `run_unit_state` in this package. `current_hp` is mutable run state; base combat stats remain derived from canonical content + unit level until a later requirement proves another durable snapshot is necessary.

### Documentation
Update `documentation/02-systems/unit-stat-advancement.md` so the level-stat rule above is canonical rather than unresolved. Keep XP curves/max-level progression explicitly unresolved/later if they are not required here.

Only adjust other current docs if necessary to remove a direct contradiction created by this package. Do not write speculative combat-engine documentation ahead of Package 2.

### Tests / verification
Add focused automated coverage proving at minimum:
- level 1 returns authored base values for all five stats;
- multiple higher levels use exactly `base + growth * (level - 1)` for all five stats;
- invalid level/stat input is rejected rather than silently defaulted;
- malformed/missing/negative authored unit stats fail content validation;
- no Speed field is required or produced;
- fresh MySQL baseline enforces the chosen non-null/non-negative current-HP invariant if tightened;
- real run start initializes every participating unit's `current_hp` to its resolved max HP, including at least one non-level-1 fixture/unit;
- failed stat/content resolution rolls back run creation and Energy mutation;
- same-key idempotent run-start retry does not recreate/reinitialize HP or spend again;
- current-run read returns the persisted initialized HP after start/reload;
- existing M1-M3 backend/content/run tests remain green.

Run the repository's supported backend/content/MySQL verification gates. Use actual MySQL 8 for persistence/integration assertions; do not represent an unavailable host-only aggregate as passed.

### Explicitly out of scope
Do not implement or scaffold:
- damage or healing formulas;
- target-resolution algorithms;
- combat scheduler/tick adaptation;
- dice material/aspect combat behavior;
- statuses/conditions;
- Farm enemy/encounter combat content;
- battle/result/playback DTOs;
- `battles` or `battle_playback` tables;
- combat/node-resolution endpoints;
- rewards, XP grants, objectives, or progression resolution;
- Phaser `BattleScene` behavior;
- cleanup/rewrite of the large prototype `DeterministicRunNodeResolver` beyond an unavoidable compile/test correction.

### Completion/reporting
Leave this issue **In Progress** when implementation is ready for architectural review; do not promote Package 2 yourself.

Report:
- exact commit SHA;
- new vNext stat resolver/value objects and their boundary;
- exact validation added for authored unit stats;
- whether/how `run_unit_state.current_hp` was tightened in the baseline;
- how run start obtains authoritative unit type + level and persists HP atomically;
- concrete level-1 and higher-level examples from tests;
- idempotency/rollback/current-run evidence;
- exact verification commands and pass counts, including any environment-limited gate.
