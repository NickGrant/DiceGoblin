# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 4 - Mudking authored boss content + deterministic boss-combat adaptation

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-3 established finalized rewards, authoritative Loot/Rest resolution, and the Farm path through an available but intentionally unsupported Boss node.

The persisted Farm graph is now:

`Combat -> Loot -> Rest -> Boss -> Exit`

The Boss node has no authored encounter yet, and the deterministic vNext combat kernel does not yet support Mudking's distinct `mud_slam` active ability.

Package 4 adapts the useful Mudking prototype behavior/art into canonical vNext authored content and the existing infrastructure-free combat kernel. It proves the boss fight can be assembled and simulated deterministically, but it does **not** make the Boss node resolvable. Package 5 owns the authoritative Boss-node transaction, rewards/XP, Mountains unlock, battle persistence through live node resolution, and Exit unlock.

#### Authority and reuse
Use current vNext source and accepted combat/content docs as authority:
- `documentation/02-systems/combat-resolution.md`;
- `documentation/02-systems/target-resolution.md`;
- `documentation/07-development-path/vnext-authored-content-model.md`;
- `documentation/07-development-path/vnext-prototype-code-disposition.md`.

Prototype Mudking definitions are behavioral evidence only. Preserve the accepted behavior below; do not revive SQL-authored catalogs, prototype node orchestration, reward logic, or `DeterministicRunNodeResolver`.

#### Canonical Mudking content
Add canonical server-authored definitions for:

##### Enemy
`enemy_unit_type.mudking`

Accepted facts:
- display name: **Mudking**;
- role: `frontline`;
- art key: `enemy_mudking`;
- stats:
  - HP = 30;
  - Attack = 5;
  - Defense = 4;
  - Precision = 5;
  - Resolve = 7;
- ordered active abilities:
  1. `ability.basic_attack_melee`
  2. `ability.wrestle`
  3. `ability.mud_slam`
- passive abilities:
  - `ability.thick_hide`;
- virtual ability die:
  - d6;
  - `dice_profile.cardboard_plain`.

Do not add Speed. The tick scheduler remains defined by authored ability `action_delay`.

##### Mud Slam
Add server-only `ability.mud_slam`.

Accepted behavior:
- active, one die slot;
- action delay = 8;
- resolution priority = 18;
- target rule = `enemy_front_prefer`;
- damaging melee attack;
- power ratio = 1.20;
- on a successful damaging hit, apply `cracked_armor`;
- cracked armor reduction = 3 Defense;
- duration = 2 rounds;
- harmful status uses the existing Precision/Resolve resistance rules;
- misses do not apply the harmful status.

Use the existing combat/status pipeline. Extend the accepted handler/config classification only as needed for this behavior; do not add a boss-only simulation branch.

`ability.thick_hide` already exists and remains the canonical +2 flat Defense passive. Reuse it rather than creating a Mudking-specific equivalent.

##### Encounter
Add:

`encounter.the_farm_mud_boss_1`

Accepted facts:
- region = `region.the_farm`;
- difficulty = 2;
- one combatant:
  - key = `mudking`;
  - enemy type = `enemy_unit_type.mudking`;
  - position = `{ x: 2, y: 1 }`.

Use concise current-vNext display/description text consistent with the existing Farm content. Do not make prose gameplay-authoritative.

#### Farm boss-node encounter identity
Set the authored Farm Boss node's `encounter_id` to:

`encounter.the_farm_mud_boss_1`

Update authored-content validation so an encounter reference is legal for the current Combat **or Boss** node types and still rejected for Loot/Rest/Exit.

Update the Farm-specific structural contract so:
- the first Combat node still references `encounter.the_farm_mud_combat_1`;
- the Boss node references `encounter.the_farm_mud_boss_1`;
- the Boss node still has no reward/event identity in this package.

Fresh run generation must carry that encounter ID into the generated graph and persisted `run_nodes.encounter_id`.

The current-run client projection must continue to omit encounter IDs and private enemy/encounter definitions.

Do not add content-version pinning or a migration chain; fresh-baseline rules remain in force.

#### Deterministic vNext combat adaptation
Extend the existing vNext combat rule classification for `mud_slam`:
- supported active handler;
- damaging;
- melee;
- `enemy_front_prefer` target rule;
- exact config validation for power ratio, `cracked_armor`, flat Defense reduction, and duration.

Use the existing generic CombatEngine damage, dice, critical/miss, position, harmful-status resistance, status replacement/expiration, and playback paths.

The Mudking encounter must normalize through `ContentRegistry -> CombatSnapshotNormalizer -> CombatInput` with:
- exact authored stats;
- ordered active abilities;
- Thick Hide passive;
- one deterministic virtual d6 per active ability slot;
- stable/unique virtual die keys;
- no PDO/repository access inside the kernel.

No new engine/playback schema version is required merely for Mud Slam if the current version-1 event contract can express all resulting facts.

#### Determinism and behavioral proof
Add focused characterization/regression coverage proving at minimum:
- same Mudking snapshot + same seed produces deep-equal combat result/playback;
- the canonical boss snapshot contains exactly the authored Mudking facts above;
- `mud_slam` is classified as melee and damaging;
- a successful Mud Slam uses the 1.20 power ratio and applies `cracked_armor` with Defense reduction 3 for 2 rounds;
- Mud Slam miss/status-resistance behavior follows the existing rules and deterministic RNG order;
- Thick Hide contributes the existing +2 flat Defense and is not duplicated;
- boss encounter normalization uses the existing plain cardboard d6 rules;
- standard Farm combat behavior remains unchanged.

Do not special-case the Mudking identity in CombatEngine when the behavior can be expressed by authored ability/passive configuration.

#### Presentation asset
The existing Mudking art assets under `frontend/public/assets/ui/units/` are retained.

Extend the current BattleScene art-key mapping so `enemy_mudking` resolves to the existing Mudking battle asset. Add focused mapping coverage.

Do not build a new boss UI, dialogue flow, animation system, or live Boss action in this package.

#### Boss node remains unsupported
Package 4 must **not** register a Boss resolution handler with `ResolveRunNodeCommand`.

Even when Rest has completed and Boss is available:
- RunScene keeps Boss non-actionable;
- a direct resolve request for the Boss remains the established unsupported-node response;
- no battle row is created;
- no HP/node/run/reward/unlock/revision mutation occurs.

This is intentional. Package 5 will connect the authored encounter to authoritative run mutation.

#### Content secrecy
Enemy unit types, server-only enemy abilities, encounters, and generated-node encounter identities remain private.

At minimum prove client content/current-run projection does not expose:
- `enemy_unit_type.mudking`;
- `ability.mud_slam`;
- `encounter.the_farm_mud_boss_1`;
- Mud Slam handler config;
- persisted Boss `encounter_id`.

The existing player-safe Mudking art asset does not make private combat configuration public.

#### Tests
At minimum cover:
- exact canonical Mudking enemy/ability/encounter facts;
- malformed Mudking stat/ability/passive/virtual-die references;
- malformed Mud Slam target/config/status values;
- boss encounter enemy/position/reference validation;
- Farm Boss encounter reference required by current Farm structure;
- generated/persisted Boss retains its encounter ID;
- private boss content remains absent from client projection/current-run;
- deterministic boss snapshot and CombatEngine behavior;
- existing standard Farm combat regression;
- available Boss remains unsupported/non-mutating through the live endpoint;
- `enemy_mudking` battle art mapping resolves to the retained asset.

#### Verification
Run applicable `agent/QUALITY_GATES.md` gates.

At minimum report:
- content validation/revision generation;
- focused Farm combat/Mudking content tests;
- focused vNext CombatEngine/normalizer tests;
- fixed-graph generation and run-start persistence regression;
- live resolve endpoint regression proving Boss remains unsupported;
- Milestone 4 combat regression;
- Package 3 Loot/Rest regression;
- complete backend Docker suite;
- focused frontend battle-art tests;
- complete frontend suite if frontend source changes;
- production frontend build;
- bundle check when applicable;
- `npm run llm:check`;
- `npm run docs:lint`;
- `git diff --check`.

Do not claim unavailable CI or host-only checks passed.

#### Explicitly out of scope
Do not implement:
- Boss-node resolution;
- `BossNodeResolutionHandler`;
- boss completion event/reward definition;
- boss XP reward amount;
- Mountains unlock grant;
- Exit unlock from boss victory;
- live Boss Fight button/action;
- battle persistence for the Boss through a run;
- Farm successful termination;
- Exit resolution;
- Tooth Collector/Shop/Wrong Machine progression;
- Pig Ear/Crown Fragment inventory behavior;
- Mountains gameplay;
- general visual overhaul.

#### Completion requirements
Before architectural review:
1. implement only Package 4;
2. preserve the existing deterministic combat architecture and Package 3 run-node behavior;
3. keep the Boss node unsupported at the live mutation boundary;
4. run/report gates honestly;
5. leave Package 4 **In Progress**;
6. do not promote Package 5;
7. report:
   - exact implementation SHA;
   - authored Mudking, Mud Slam, and boss encounter facts;
   - persisted Boss encounter-ID path;
   - CombatRules/kernel adaptation;
   - deterministic boss-combat evidence;
   - client secrecy/art mapping evidence;
   - exact verification results.
