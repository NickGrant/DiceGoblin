# Encounter Scope

Status: active  
Last Updated: 2026-06-21  
Owner: Systems Design  
Depends On: `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/05-save-and-resume-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose

- Define the encounter and run-map scope used by the current alpha build.
- Replace older biome and encounter assumptions with the live route and generator behavior.

## Encounter Types

The active run map exposes five node types:

1. `combat`
2. `loot`
3. `rest`
4. `boss`
5. `exit`

Behavior by type:

- `combat`: resolves into a battle log, reward preview, and possible run failure
- `loot`: resolves immediately into a non-combat reward preview
- `rest`: opens a separate rest page and finalizes manually
- `boss`: uses combat rules and gates the final exit path
- `exit`: is not resolved through node resolution and instead completes the run through the dedicated exit endpoint

## XP Scope

- Combat and boss nodes are the active XP-awarding encounter types.
- Loot and rest do not directly award combat XP.
- Exit is a run-completion action, not an XP source by itself.

## Region Scope

The current alpha region set is:

- `the_farm`
- `mountains`
- `swamps`

## Region Structure

### The Farm

- Intro region
- Fixed linear graph
- Current path: combat -> loot -> rest -> boss -> exit

### Mountains

- Procedural graph
- Branching paths
- Higher pressure than the farm
- Current player-facing copy describes it as the gate to swamps

### Swamps

- Procedural graph
- Wider branching layout than mountains
- Frogmen-themed destination region in the current guide copy

## Map Generation Rules

- Every run belongs to exactly one region.
- Each run has one active map at a time.
- The first node starts available.
- Procedural regions use a horizontally advancing route graph that scrolls left-to-right toward the boss and exit.
- Downstream nodes unlock when parent progression clears.
- Exit nodes remain separate from normal node resolution.
- The current generator guarantees at least one rest node in procedural regions.
- Procedural regions may include optional dead-end branches, but they must never block the guaranteed boss route.

## Encounter Exclusions

The current alpha build does not expose separate route types for:

- merchants
- narrative-only event nodes
- puzzle nodes
- hazard-only nodes
- social choice chains

Those concepts may still appear in design history, but they are not part of the current run route set.

## Region Energy

- Region energy cost is region-specific.
- Current frontend region cards show:
  - Farm: 3 energy
  - Mountains: 5 energy
  - Swamps: 5 energy
- Player profile energy still tracks current, max, regen rate per hour, and last regen timestamp.

## Run Shape Expectations

- Runs do not span multiple regions.
- Boss nodes sit near the end of the route.
- Exit becomes the terminal success path after boss progress is satisfied.
- Attrition is carried through the run via run-unit state.

## Validation Rules

The encounter scope is aligned when:

- docs describe five node types, including exit
- docs include Farm, Mountains, and Swamps
- farm is documented as a fixed introductory lane
- mountains and swamps are documented as procedural regions
