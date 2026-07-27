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
