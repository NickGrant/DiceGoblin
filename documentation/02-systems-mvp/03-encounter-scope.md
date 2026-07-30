# Encounter Scope

Status: active  
Last Updated: 2026-07-27  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/05-save-and-resume-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose

- Define the encounter and run-map scope used by the current alpha build.
- Replace older biome and encounter assumptions with the live route and generator behavior.

## Encounter Types

The active run map exposes nine node types:

1. `dialogue`
2. `combat`
3. `loot`
4. `rest`
5. `boss`
6. `hazard`
7. `shrine`
8. `chaos`
9. `exit`

Behavior by type:

- `dialogue`: presents authored narrative, records one-time discovery state where applicable, and advances the run without combat rewards
- `combat`: resolves into a battle log, reward preview, and possible run failure
- `loot`: resolves immediately into a non-combat reward preview
- `rest`: opens a separate rest page and finalizes manually
- `boss`: uses combat rules and gates the final exit path in combat regions
- `hazard`: resolves as a backend-authored non-combat obstacle, clears progression, and grants no rewards by default
- `shrine`: resolves as a backend-authored non-combat encounter, persists a generated favor result, and may grant a small deterministic non-XP reward
- `chaos`: presents persisted slot-style reel results and one bounded reroll before later combat/reward finalization work
- `exit`: is not resolved through node resolution and instead completes the run through the dedicated exit endpoint

## XP Scope

- Combat and boss nodes are the active XP-awarding encounter types.
- Dialogue, loot, rest, hazard, shrine, and chaos nodes do not directly award combat XP.
- Exit is a run-completion action, not an XP source by itself.

## Region Scope

The current alpha region set is:

- `mystic_cave`
- `the_farm`
- `mountains`
- `swamps`

The current completion unlock sequence is:

- Mystic Cave unlocks The Farm.
- The Farm unlocks Mountains.
- Mountains unlocks Swamps.

## Region Structure

### Mystic Cave

- Initial unlocked region
- Zero-energy onboarding run
- Fixed two-node graph
- Current first-run path: dialogue with The Whim -> exit
- Does not contain combat, loot, rest, or boss nodes
- Completing it unlocks The Farm
- Later visits may use an authored reminder dialogue after the first conversation has been seen

Mystic Cave is currently a narrative onboarding region rather than a combat biome.

### The Farm

- First combat region
- Fixed linear graph
- Current base path: combat -> loot -> rest -> boss -> exit
- Authored dialogue nodes may be inserted before boss or exit progression

### Mountains

- Procedural graph
- Branching paths
- Higher pressure than the farm
- Current player-facing copy describes it as the gate to swamps
- Authored dialogue may be inserted at the start of the run

### Swamps

- Procedural graph
- Wider branching layout than mountains
- Frogmen-themed destination region in the current guide copy

## Dialogue Rules

- Dialogue nodes store an authored dialogue identifier in node metadata.
- A dialogue definition may be one-time or repeatable.
- One-time dialogue already seen by the player is removed or replaced according to authored graph rules.
- Dialogue discovery state may feed codex lore entries.
- Dialogue nodes do not grant combat XP unless a future authored system explicitly changes that rule.

## Map Generation Rules

- Every run belongs to exactly one region.
- Each run has one active map at a time.
- The first node starts available.
- Mystic Cave uses a fixed narrative graph.
- The Farm uses a fixed introductory combat graph.
- Procedural regions use a horizontally advancing route graph that scrolls left-to-right toward the boss and exit.
- Downstream nodes unlock when parent progression clears.
- Exit nodes remain separate from normal node resolution.
- The current generator guarantees at least one rest node in procedural combat regions.
- Procedural regions may include optional dead-end branches, but they must never block the guaranteed boss route.
- Procedural regions may include shrine nodes as optional non-combat branches or late-path pauses.
- Procedural regions include at least one chaos node that presents persisted reel results.
- Authored dialogue nodes may be inserted into otherwise fixed or procedural graphs.

## Node Quality Art

Loot and shrine nodes carry optional display metadata so the map and node-detail screens can render the same quality-tier art:

- `node_quality_tier`: one of `poor`, `good`, or `great`
- `node_art_variant`: optional `a` or `b` override

Current procedural generation assigns quality tiers after node types are finalized:

- early loot or shrine nodes use `poor`
- ordinary loot or shrine nodes use `good`
- late-path or dead-end loot and shrine nodes use `great`

The frontend resolves the A/B variant from `node_art_variant` when present. Otherwise, it uses persisted node id parity so a node keeps the same visual variant across refreshes. Older runs without quality metadata fall back to `good` art.

### Target Procedural Replacement

- Mountains and Swamps currently use the seeded lane-walker implementation in `RunGraphGenerator`.
- `documentation/02-systems-mvp/15-pattern-based-run-map-generation.md` defines the proposed replacement contract.
- The replacement builds a guaranteed start-to-boss spine from authored socketed patterns before expanding optional branches.
- The replacement does not become current-state behavior until a region explicitly enables the new generator version.
- Existing active runs continue from persisted nodes and edges and are never regenerated during migration.

## Encounter Exclusions

The current alpha build does not expose separate route types for:

- merchants
- branching narrative event chains beyond authored dialogue nodes
- puzzle nodes
- social choice chains

Those concepts may still appear in future planning, but they are not part of the current run route set.

## Chaos Encounter Foundation

Slot-machine-style chaos encounters now have a backend persistence foundation and appear as generated run-map node types in procedural regions.

The foundation uses three authored reel responsibilities:

- enemy family: the broad enemy source or mixed-family pressure
- encounter shape: the formation or composition pressure
- rule and reward: the special rule, reward hook, or payout pressure

Generation rules:

- the backend generates and stores one result per run node
- repeated generate calls return the stored result
- reward multiplier is derived from the persisted risk score, not a separate hidden roll
- the player may reroll one reel once through the backend
- reroll state is stored with the result so refreshes and retries cannot reset it

Current behavior:

- finalizing a chaos result locks the reels to the node
- the backend binds the result to a deterministic combat template and resolves it through the normal battle path
- the player watches the resulting battle playback and claims rewards through the normal battle claim flow
- richer reel-specific combat modifiers remain follow-up work

Current foundation exclusions:

- exact authored enemy-shape construction from each reel combination remains follow-up work
- bolstered enemy starts, ambush positioning rules, guaranteed loot variants, and more granular Raw Chaos tuning remain follow-up work

## Hazard And Shrine Primitives

Hazards and shrines resolve through a backend-owned primitive vocabulary before richer authored catalogs are seeded. Shrine nodes use map metadata for quality/rendering context, but the actual shrine effect is generated when the shrine is encountered and then persisted in the battle log/reward result for idempotency.

Hazard primitives:

| Primitive | Intended use |
| --- | --- |
| `hp_attrition` | Bounded between-combat HP pressure on one or more run units. |
| `temporary_modifier` | Short-lived stat or status pressure that expires after a known scope. |
| `currency_pressure` | Small soft-currency or Raw Chaos costs, losses, or gated choices. |
| `item_pressure` | Generic item costs, losses, or required-key checks. |
| `route_pressure` | Path, branch, or node-state pressure without direct combat rewards. |
| `kin_mitigation` | Optional reduced risk or alternate copy for owned lineages. |

Shrine primitives:

| Primitive | Intended use |
| --- | --- |
| `small_reward` | Bounded non-XP rewards such as soft currency, items, or minor dice support. |
| `cleansing` | Remove or soften temporary run pressure. |
| `bargain` | Exchange currency, items, HP pressure, or risk for a visible reward. |
| `reroute` | Open, reveal, or improve a route choice without breaking graph guarantees. |
| `controlled_risk` | Offer an explicit chance or deterministic tradeoff with bounded downside. |

Current primitive-backed effects:

| Node type | Effect slug | Primitive | Eligibility | Current result |
| --- | --- | --- | --- | --- |
| `hazard` | `hazard_cautious_footing` | `route_pressure` | Farm, Mountains, and Swamps from depth 3 onward. | Clears the hazard with no XP or currency reward. |
| `hazard` | `hazard_mud_slick` | `temporary_modifier` | Farm from depth 3 onward. | Metadata-only precision pressure until temporary modifiers land. |
| `hazard` | `hazard_broken_fence` | `route_pressure` | Farm from depth 4 onward. | Metadata-only route pressure. |
| `hazard` | `hazard_loose_scree` | `hp_attrition` | Mountains from depth 4 onward. | Currently resolves as metadata-only pressure with no HP loss until attrition spending lands. |
| `hazard` | `hazard_thin_air` | `temporary_modifier` | Mountains from depth 5 onward. | Metadata-only resolve pressure until temporary modifiers land. |
| `hazard` | `hazard_toll_cairn` | `currency_pressure` | Mountains from depth 4 onward. | Metadata-only currency pressure until bargain costs land. |
| `hazard` | `hazard_rust_thicket` | `item_pressure` | Mountains and Swamps from depth 5 onward. | Metadata-only item pressure until item costs land. |
| `hazard` | `hazard_bog_mire` | `kin_mitigation` | Swamps from depth 4 onward. | Currently resolves as metadata-only Pig Kin mitigation setup until mitigation choices land. |
| `hazard` | `hazard_biting_reeds` | `hp_attrition` | Swamps from depth 3 onward. | Metadata-only HP pressure until attrition spending lands. |
| `hazard` | `hazard_sinking_cache` | `item_pressure` | Swamps from depth 5 onward. | Metadata-only item pressure until item costs land. |
| `hazard` | `hazard_wrong_turn` | `route_pressure` | Mountains and Swamps from depth 6 onward. | Metadata-only route pressure. |
| `shrine` | `shrine_bone_whisper` | `grant_teeth` | Farm, Mountains, and Swamps. | Grants bounded teeth, weighted more often on poor shrines. |
| `shrine` | `shrine_rust_blessing` | `grant_teeth` | Farm, Mountains, and Swamps. | Grants bounded teeth, weighted more often on poor shrines. |
| `shrine` | `shrine_clean_water` | `heal_random_unit` | Farm and Swamps. | Heals one wounded run unit when claimed. |
| `shrine` | `shrine_old_goblin_mark` | `squad_damage_next_combat` | Farm, Mountains, and Swamps. | Persists a next-combat run modifier for the squad; combat consumes it after one eligible fight. |
| `shrine` | `shrine_hidden_footpath` | `clear_random_combat_node` | Mountains and Swamps. | Clears one available combat node on the map when claimed. |
| `shrine` | `shrine_bog_luck` | `double_run_teeth` | Swamps. | Awards teeth equal to claimed teeth already earned earlier in the run. |
| `shrine` | `shrine_crooked_bargain` | `drain_highest_life_heal_rest` | Mountains and Swamps. | Presents a declineable bargain; accepting drains the healthiest unit to heal the rest, declining clears the claim without applying either side. |

Procedural hazard population:

- Procedural node selection includes hazards only when the region and depth have at least one eligible authored hazard effect.
- Hazard selection is weighted by the eligible effect definitions and stamped into node metadata as `encounter_family`, `encounter_effect_slug`, and `encounter_primitive`.
- Shallow opening columns do not generate hazards, preserving early route readability.
- If a generated hazard somehow has no eligible effect at metadata assignment time, the generator falls back to combat for that node instead of producing an unresolved hazard.
- Shrine resolution chooses from the authored shrine catalog using region and quality-weighted pools, then persists `title`, `result_copy`, `favor`, `quality`, `effect`, optional `cost`, and soft currency in the result payload.
- Costly shrine effects use the persisted result as an offer: accepting claims the reward and applies the cost exactly once, while declining marks the battle reward claimed with no positive or negative shrine effect.
- Next-combat run modifiers support generic `stat_multipliers` and `stat_adders` for `attack`, `defense`, `precision`, and `resolve`, plus a `damage` multiplier applied through combat affixes.

Authoring constraints:

- Primitive resolution is backend-authoritative and deterministic from the run/node seed context.
- Node effects must persist their `effect_slug`, `primitive`, and result payload in the battle log or reward payload.
- Hazard and shrine effects must be idempotent when a resolved node is revisited.
- Hazard and shrine effects must not award combat XP unless the encounter scope is explicitly updated.
- Content catalogs may choose effects by region, depth, weight, and progression context.

## Region Energy

- Region energy cost is region-specific.
- Current frontend region cards show:
  - Mystic Cave: 0 energy
  - Farm: 3 energy
  - Mountains: 5 energy
  - Swamps: 5 energy
- Player profile energy still tracks current, max, regen rate per hour, and last regen timestamp.

## Run Shape Expectations

- Runs do not span multiple regions.
- Mystic Cave completes through its dialogue and exit path without a boss.
- Combat-region boss nodes sit near the end of the route.
- Exit becomes the terminal success path after the region's required progression is satisfied.
- Attrition is carried through combat runs via run-unit state.

## Validation Rules

The encounter scope is aligned when:

- docs describe nine node types, including dialogue, hazard, shrine, chaos, and exit
- docs include Mystic Cave, Farm, Mountains, and Swamps
- Mystic Cave is documented as the initial zero-energy narrative run
- Farm is documented as the first fixed combat lane
- Mountains and Swamps are documented as procedural regions
- dialogue discovery and insertion rules match the live run graph behavior
