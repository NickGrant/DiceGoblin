# Encounter Scope

Status: active  
Last Updated: 2026-07-24  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/05-save-and-resume-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose

- Define the encounter and run-map scope used by the current alpha build.
- Replace older biome and encounter assumptions with the live route and generator behavior.

## Encounter Types

The active run map exposes seven node types:

1. `dialogue`
2. `combat`
3. `loot`
4. `rest`
5. `boss`
6. `hazard`
7. `exit`

Behavior by type:

- `dialogue`: presents authored narrative, records one-time discovery state where applicable, and advances the run without combat rewards
- `combat`: resolves into a battle log, reward preview, and possible run failure
- `loot`: resolves immediately into a non-combat reward preview
- `rest`: opens a separate rest page and finalizes manually
- `boss`: uses combat rules and gates the final exit path in combat regions
- `hazard`: resolves as a backend-authored non-combat obstacle, clears progression, and grants no rewards by default
- `exit`: is not resolved through node resolution and instead completes the run through the dedicated exit endpoint

## XP Scope

- Combat and boss nodes are the active XP-awarding encounter types.
- Dialogue, loot, rest, and hazard nodes do not directly award combat XP.
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
- Authored dialogue nodes may be inserted into otherwise fixed or procedural graphs.

## Encounter Exclusions

The current alpha build does not expose separate route types for:

- merchants
- branching narrative event chains beyond authored dialogue nodes
- puzzle nodes
- social choice chains
- fully routed slot-machine-style generated encounters

Those concepts may still appear in future planning, but they are not part of the current run route set.

## Chaos Encounter Foundation

Slot-machine-style chaos encounters now have a backend persistence foundation, but they are not yet a generated run-map node type.

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

Current foundation exclusions:

- chaos nodes are not yet placed by the run graph generator
- chaos results do not yet finalize into combat or claim rewards
- Raw Chaos payouts, combat modifiers, and full frontend presentation remain follow-up work

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

- docs describe seven node types, including dialogue, hazard, and exit
- docs include Mystic Cave, Farm, Mountains, and Swamps
- Mystic Cave is documented as the initial zero-energy narrative run
- Farm is documented as the first fixed combat lane
- Mountains and Swamps are documented as procedural regions
- dialogue discovery and insertion rules match the live run graph behavior
