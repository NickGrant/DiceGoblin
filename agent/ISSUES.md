# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 2 - Farm combat authored content + deterministic engine adaptation

**Status:** In Progress
**Priority:** High

#### Problem
Package 1 established canonical level-derived combat stats and authoritative run HP. The next prerequisite is the actual combat-content and engine boundary that later persistence/API work can safely wrap.

The retained prototype `backend/src/Combat/Engine/DeterministicRunNodeResolver.php` is not that boundary. It mixes PDO/catalog access, run/node concerns, rewards/progression, non-combat node effects, combat simulation, and playback construction. Its old damage calculation and wall-clock metadata are evidence only, not canonical vNext rules.

This package must establish the smallest complete deterministic combat kernel needed for the **first normal Farm combat node**. It also promotes only the Farm enemy/encounter content required by that fight into current canonical authored JSON.

Do not add battle persistence, combat-node mutation, HTTP routes, rewards/XP, boss resolution, or Phaser behavior in this package.

#### Recovered Farm slice to preserve
Git history is evidence, not an active content source. Recover these intended Farm facts into current authored JSON rather than restoring removed SQL catalogs/docs:

- Mudwrestler: frontline; HP 16, Attack 3, Defense 2, Precision 5, Resolve 5; ordered actives `ability.basic_attack_melee`, `ability.wrestle`.
- Mudslinger: backline; HP 14, Attack 4, Defense 1, Precision 6, Resolve 4; ordered actives `ability.basic_attack_ranged`, `ability.mud_sling`.
- `ability.wrestle`: active, one die, action delay 8, front-enemy preference, `power_ratio = 1.05`, applies `wrestled` for 2 rounds.
- `ability.mud_sling`: active, one die, action delay 8, back-enemy preference, `power_ratio = 0.90`, applies `cracked_armor` with Defense -2 for 2 rounds.
- `encounter.the_farm_mud_combat_1`: Mudwrestler at `(x=2,y=1)` and Mudslinger at `(x=0,y=1)`, difficulty 1, description: `A pair of pigs lurches out of the muck, giving the warband its first real skirmish.`
- The first Farm `run_node_type.combat` node should reference this stable encounter ID.

The retained prototype supplied one virtual d6 for each required enemy ability die slot. Preserve that useful behavior deliberately as authored/snapshot behavior rather than as a hidden SQL/prototype default: these two Farm enemies use plain virtual d6 ability dice with no material/aspect effects.

Do **not** promote Mudking/boss content in this package. Mudking, boss resolution, run completion, rewards, and Mountains unlock remain Milestone 5. The existing Farm boss node may remain without a combat encounter reference until the milestone that owns it.

#### Authored-content model
Create the current JSON definitions needed for this slice. Keep stable IDs explicit and semantically validated.

Preferred conceptual types:
- `enemy_unit_type` for non-owned authored enemy combatants;
- `encounter` for authored enemy formation/composition;
- enemy-exclusive abilities remain the existing `ability` definition type so shared engine semantics stay shared.

Exact file organization may follow the established hybrid content layout, but do not create SQL gameplay catalogs.

Enemy definitions must provide enough server-side information to assemble a combat snapshot without prototype services: five stats, ordered active ability IDs, applicable passive ability IDs if any, art/display/role identity, and deliberate virtual-die configuration. Do not invent enemy level growth for these fixed authored enemies merely to reuse player-unit structures.

Encounter validation must reject invalid region/enemy/ability references, duplicate combatant keys or occupied positions, out-of-range 3x3 positions, empty combat encounters, and malformed difficulty/presentation fields.

Update `ContentRegistry` with focused server-side accessors for the new definitions. The content validator must cross-check all stable references.

#### Exposure boundary
Enemy/encounter **combat mechanics and hidden formation details are server-only by default**. Do not add full enemy stats, ability configs, encounter rosters, or hidden encounter details to the public content bundle merely because the engine needs them.

If the existing run-map projection needs player-facing encounter presentation, expose only the minimum explicitly allowlisted presentation fields required by an existing contract. The current persisted map must not reveal the hidden roster through generated metadata or public authored projection.

#### Deterministic combat boundary
Create a new vNext combat domain/kernel boundary. It must be infrastructure-free:
- no PDO;
- no repositories;
- no HTTP/controller types;
- no `ContentRegistry` traversal inside the kernel;
- no clocks/wall time;
- no run/node persistence;
- no reward/progression services.

The kernel consumes a complete normalized combat snapshot assembled outside it. The snapshot must include all authoritative facts the kernel needs, including:
- deterministic seed/context;
- stable combatant keys and side;
- 3x3 combat positions;
- max/current HP and the five resolved stats;
- ordered active abilities with normalized authored config;
- applicable passive abilities with normalized config;
- exact ability-die specs/profile effects needed by the action.

Do not make the kernel look up database IDs or authored catalogs while resolving.

The kernel returns an immutable/value-style result suitable for Package 3 persistence. At minimum it must contain:
- explicit engine/playback schema version;
- terminal outcome: `victory`, `defeat`, or `stalemate`;
- final player and enemy combatant HP/state;
- ending round/tick;
- ordered semantic playback events sufficient for later `BattleScene` presentation.

The deterministic result must not contain `createdAt`, current timestamps, random UUIDs, request data, DB IDs that the kernel did not receive, reward/XP/currency grants, or other wall-clock/environment-dependent fields.

#### Scheduling semantics
Combat remains tick-based and does **not** gain a persistent Speed stat.

Canonical scheduling for this package:
- 20 ticks per round;
- each living combatant begins by scheduling the first active ability in its ordered loadout at that ability's `action_delay`;
- after an ability executes, advance cyclically to the next active ability and schedule that next action by adding the **next ability's** `action_delay` to the current tick;
- a disabled/dead combatant does not execute an action for which it is ineligible;
- same-tick actions resolve by ascending `resolution_priority` (lower values first), then by a stable deterministic combatant-key tie-break; do not depend on PHP array/hash iteration order;
- cap combat at 200 rounds / 4000 ticks. If both sides still have living combatants after the cap, return `stalemate` rather than throwing or choosing a winner.

Playback must preserve the resolved action ordering so Phaser never recreates scheduler logic.

#### Dice and effective-stat semantics
An ability rolls each exact die bound to its required slots every time that ability executes. Dice are not destroyed/removed by use during battle.

For this package:
- roll values are uniform integers `1..sides` from deterministic engine RNG;
- multiple slot results sum to `roll_total`;
- `dice_aspect.explosive`: if the initial roll is the die maximum, roll that die exactly one additional time and add it; the extra roll cannot explode again;
- material currently has no combat effect beyond profile identity/eligibility;
- `dice_aspect.striking`: +1 flat damage when that die participates in the damaging action;
- `dice_aspect.executioner`: +15% damage when the target is strictly below half max HP;
- `dice_aspect.guarding`: +1 Defense while that die is equipped/bound;
- `dice_aspect.bulwark`: +10% Defense while that die is equipped/bound;
- `dice_aspect.precise`: +10% Attack while that die is equipped/bound.

Always-on equipped/bound stat effects compose across all dice supplied on the combatant snapshot. Apply flat stat modifiers first, then sum same-stat percentage modifiers and floor once after the percentage stage.

Applicable passive behavior required by the current seeded Raiders slice:
- `ability.thick_hide`: +2 flat Defense;
- `ability.sharpshooter`: +15% damage to ranged attacks.

Do not implement future materials/aspects/passives simply because prototype code contains them.

#### Canonical damage / Precision / Resolve math
Current vNext authored `power_ratio` is authoritative intent and must not be ignored. Do not preserve the prototype's unrelated `0.65 * Attack - 0.35 * Defense + random variance` formula. Dice provide the baseline action variance; there is no additional unexplained `-2..+2` damage variance.

For an intended damaging hit:
1. Build effective Attack/Defense from the normalized base stats plus always-on passive/dice stat modifiers: flats first, then summed percentages, floor after the percentage stage.
2. Apply any active status modifiers to those effective stats using the same flat-then-percentage convention.
3. Perform the Precision hit/critical check described below.
4. Roll the action's bound dice and resolve one-level explosive rerolls.
5. Compute `attack_component = floor(effective_attack * power_ratio)`.
6. Compute effective target Defense, including status reductions and explicit ability defense-ignore.
7. Compute `post_defense = max(0, attack_component + roll_total - effective_target_defense)`.
8. Add action flat damage such as Striking.
9. Apply conditional damage multipliers such as Executioner and Sharpshooter.
10. Apply position multipliers.
11. Apply critical `x1.5` when applicable and floor.
12. A successful action that is authored to deal damage deals at least 1 final damage. A miss deals 0 and applies no harmful on-hit status.

Position rules for this slice use side-relative 3x3 `x` coordinates, where `x=2` is front and `x=0` is back for both logical sides:
- melee attacker in front: dealt damage x1.10;
- any target in front: damage taken x1.10;
- melee target in back: damage taken x0.90;
- floor after applying the combined position multiplier.

Precision is neutral at 5:
- Precision < 5: miss chance `min(40, (5 - precision) * 8)` percent; no Precision crit chance;
- Precision = 5: no hit/crit RNG is consumed;
- Precision > 5: no Precision miss chance; crit chance `min(30, (precision - 5) * 5)` percent;
- a critical damaging hit multiplies damage by 1.5 at the stage above.

Harmful status resistance compares target Resolve to source Precision:
- buffs are not resisted;
- if target Resolve <= source Precision, no Resolve RNG is consumed;
- otherwise resistance chance is `min(45, (resolve - precision) * 8)` percent;
- resisted harmful status is not applied and is represented in playback.

Every RNG-consuming decision must use one deterministic RNG stream in a documented stable order. Re-running an identical input snapshot and seed must produce a deep-equal result and playback.

#### Target resolution for this slice
Implement only the currently required target rules plus forced targeting:
- `self`;
- `enemy_front_prefer`: prefer living enemies at x=2, then x=1, then x=0;
- `enemy_back_prefer`: prefer living enemies at x=0, then x=1, then x=2;
- `ally_lowest_hp_pct`: living ally with lowest `current_hp / max_hp`;
- `wrestled` forced target for the affected unit's next eligible enemy-targeted damaging attack when the wrestler remains alive/valid.

Equally valid ties are resolved from deterministic RNG, not insertion order. Playback/debug facts must retain enough target-reason information to explain preference/forced-target selection without requiring Phaser to run the algorithm.

Do not implement unused speculative target modes in this package.

#### Ability/status behavior required by the first Farm slice
The kernel must correctly support the current seeded Raiders active abilities plus the two Farm enemy-exclusive abilities:
- `ability.basic_attack_melee`;
- `ability.basic_attack_ranged`;
- `ability.heavy_strike`;
- `ability.aimed_shot`;
- `ability.shield_up`;
- `ability.bolster_ally`;
- `ability.sleep_dart`;
- `ability.wrestle`;
- `ability.mud_sling`.

Use current authored handler configs for ratios/status values rather than duplicating constants in engine conditionals where content already owns them.

Required statuses:
- `bolstered`: percentage Defense buff using authored value/duration;
- `sleep`: prevents action; expires by authored duration or immediately when damaged. If sleep ends on tick T, the unit remains ineligible to act on tick T and may act starting T+1;
- `cracked_armor`: flat Defense reduction using authored value/duration;
- `wrestled`: forces the affected unit's next eligible enemy-targeted damaging attack toward the wrestler while valid, then is consumed; it also expires normally by authored duration.

Status duration transitions must be deterministic and represented in playback. Do not add a general status scripting language.

Healing is not required by this first combat slice; do not invent a healing formula merely for completeness.

#### Playback/result contract
Define a small typed/value-style playback event model or equivalently strict normalized arrays with validation. It must be explicit/versioned enough for Package 3 to persist without interpreting prose logs.

Events should preserve semantic facts needed later for animation/presentation, such as:
- battle start/end;
- round/tick progression where needed;
- actor + ability action;
- chosen target and target reason;
- dice rolls/explosive reroll;
- hit/miss/critical;
- damage and resulting HP;
- status application/resistance/expiration/removal;
- death.

Do not make free-form message strings the authoritative event contract. Human-readable copy can be derived later.

#### Integration with Farm authored run content
Update `backend/content/run-generation/the-farm.json` so its first combat node has `encounter_id = encounter.the_farm_mud_combat_1` (or the equivalent stable ID if the repository's existing naming conventions require a different prefix).

Run generation remains deterministic and infrastructure-free. The generated/persisted node should carry only the stable encounter reference; it must not copy the full roster/stats into generated metadata.

Do not attach a boss encounter yet.

#### Documentation
Update current canonical docs only:
- `documentation/02-systems/combat-resolution.md` with the exact vNext scheduling/damage/dice/Precision/Resolve/status rules adopted here;
- `documentation/02-systems/target-resolution.md` with the exact required target/forced-target semantics and deterministic tie rule;
- update `documentation/02-systems/dice-profiles-and-aspects.md` only if needed to remove a direct ambiguity around the now-implemented aspect semantics.

Do not restore removed MVP/legacy documentation. Git history remains recovery evidence, not an active source of truth.

#### Tests / verification
Add focused deterministic tests proving at minimum:
- identical normalized snapshot + seed produces deep-equal result/playback, including no timestamps/environment values;
- different seed changes random rolls/choices where expected without changing deterministic rules;
- first ability scheduling, cyclic ordered loadout advancement, action delays, same-tick priority, and stable tie behavior;
- victory, defeat, death/action cancellation, and 200-round stalemate;
- exact baseline damage formula and rounding with `power_ratio`;
- no prototype `-2..+2` free variance;
- melee/front/back position multipliers;
- Precision miss/crit neutral and non-neutral cases;
- Resolve resistance and neutral no-RNG case;
- deterministic front/back/lowest-HP targeting and tie resolution;
- Wrestled forced-target behavior;
- current Raiders abilities listed above;
- Thick Hide and Sharpshooter;
- plain die rolls, multiple slots, Striking, Guarding, Bulwark, Precise, Executioner, and one-level Explosive behavior;
- Bolstered, Sleep, and Cracked Armor duration/removal behavior;
- Mudwrestler/Mudslinger definitions and encounter formation are semantically valid;
- malformed enemy/encounter references, positions, duplicate keys/cells, stats, ability references, and virtual die config fail authored-content validation;
- the first Farm combat node resolves its canonical encounter reference while the boss remains deliberately unbound;
- no combat/battle SQL tables, HTTP routes, run HP writes, rewards, or Phaser behavior are introduced;
- existing M1-M3 and Package 1 backend/content/run-generation tests remain green.

Use focused pure unit/content tests as the primary engine proof. Run the repository's supported backend/content regression gates. Do not represent an unavailable host-only aggregate or absent GitHub workflow as passed.

#### Explicitly out of scope
Do not implement or scaffold:
- `battles`, `battle_playback`, or other battle persistence tables;
- combat-node resolution commands/controllers/routes;
- mutation of `run_unit_state`, `run_nodes`, or run lifecycle as a result of combat;
- idempotency receipts for node resolution;
- battle/result read APIs;
- Phaser `BattleScene` or client combat DTOs;
- rewards, Teeth, XP, progression, objectives, unit/dice grants, or loot materialization;
- Mudking/boss combat, run completion, terminal Farm flow, or Mountains unlock;
- Rest/loot/Chaos node resolution;
- future enemy families or unused ability/passive/status/target catalogs;
- wholesale cleanup of the retained prototype combat source. Remove/retire only pieces that become directly superseded and can be proven unreachable without broadening the package.

#### Completion/reporting
Leave this issue **In Progress** when implementation is ready for architectural review; do not promote Package 3 yourself.

Report:
- exact implementation commit SHA;
- authored files/types/IDs added and client-exposure decision;
- Farm encounter reference added to run generation;
- normalized combat input/result/playback boundary;
- exact scheduling/tie/stalemate semantics;
- exact damage/rounding/Precision/Resolve/position rules implemented;
- target and status semantics implemented;
- dice/passive/aspect behavior implemented;
- what prototype code was mined, superseded, retained, or deliberately ignored;
- deterministic test examples/golden evidence;
- exact verification commands and results, including any environment-limited aggregate or absent CI status.
