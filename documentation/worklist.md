# Dice Goblins — Development Milestones & Roadmap

This document defines the authoritative milestone structure for the Dice Goblins project.
All implementation discussions, task breakdowns, and progress tracking should reference
these milestones and their exit criteria.

---

## Current Status

**Active Milestone:** Milestone 2 — Technical & Contract Alignment  
**Active Task:** _(update as work progresses)_

---

## Milestone 0 — Technical & Contract Alignment (Non-Playable) - COMPLETE

### Objective
Establish a stable, unambiguous foundation by aligning database schema, backend repositories,
and data contracts. No gameplay work should proceed until this milestone is complete.

### Scope
- Backend schema vs repository alignment
- Canonical JSON contract definitions
- Canonical ability catalog
- Migration validation

### Tasks
1. **Backend schema ↔ repository alignment**
   - Audit all repositories for assumed columns
   - Resolve mismatches (remove assumptions or update schema)
   - Ensure repositories reflect documented data model

2. **Canonical JSON contract definitions**
   - Finalize `base_stats_json` structure
   - Finalize `ability_set_json` structure
   - Ensure contracts support speed, dice costs, ordering, and targeting

3. **Canonical ability catalog**
   - Define stable ability IDs
   - Define effect payloads per ability
   - Map abilities to supported status effects
   - Decide storage location (config vs DB)

4. **Migration validation**
   - Run all migrations from a clean database
   - Verify API boots and core endpoints respond
   - Fix ordering or dependency issues

### Exit Criteria
- Schema, repositories, and services are fully aligned
- Unit types and abilities can be authored without ambiguity
- Fresh install succeeds with no manual fixes

---

## Milestone 1 — Core Combat Content & Data (Non-Playable) - COMPLETE

### Objective
Populate the database with real, usable content so battles can exist conceptually.

### Scope
- Unit types and promotions
- Enemies
- Encounters
- Loot tables

### Tasks
1. **Unit content**
   - Finalize 13 unit types and promotion chains
   - Assign base stats, growth, max levels
   - Assign ability sets
   - Create unit type seed migration

2. **Enemy and encounter content**
   - Define enemy templates
   - Define encounter templates (combat, rest, loot, boss)
   - Define loot tables and boss drops
   - Create seed migrations

### Exit Criteria
- Database contains valid unit, enemy, encounter, and loot data
- All content references valid ability IDs and stat contracts

---

## Milestone 2 — Server-Side Battle Resolution (Playable via API)

### Objective
Enable the server to deterministically resolve battles and produce replayable results.

### Scope
- Battle generation
- Combat resolution
- Rewards and XP

### Tasks
1. **Battle generation**
   - Resolve run node into battle
   - Enforce idempotency per node

2. **Combat resolver**
   - Execute rounds, ticks, and abilities
   - Generate deterministic combat logs
   - Persist outcomes

3. **Rewards and XP**
   - Calculate XP for surviving units
   - Roll loot tables
   - Implement reward claim endpoint

### Exit Criteria
- Battles can be started, replayed, and claimed via API
- XP, loot, and deaths are applied correctly

---

## Milestone 3 — Run Progression & Attrition (First True Game Loop)

### Objective
Support a full run lifecycle with persistence and failure states.

### Scope
- Node progression
- Run-scoped unit state
- Run completion and failure

### Tasks
1. **Node progression**
   - Mark nodes cleared
   - Unlock connected nodes

2. **Run-scoped unit state**
   - Persist HP and defeat state
   - Enforce attrition rules

3. **Run end handling**
   - Detect full warband defeat
   - Apply XP reset rules
   - Clean up run-scoped data

### Exit Criteria
- Runs can be completed or failed
- Attrition persists correctly across encounters

---

## Milestone 4 — Encounter Flow UI (Playable End-to-End)

### Objective
Allow players to play the full encounter loop through the UI.

### Scope
- Encounter setup
- Combat replay
- Encounter completion

### Tasks
1. **Encounter setup screen**
   - Node selection → encounter setup
   - Team selection and validation

2. **Encounter replay screen**
   - Visual combat log playback
   - Basic playback controls

3. **Encounter completion screen**
   - Display rewards, XP, and defeats
   - Trigger reward claim

### Exit Criteria
- Player can start and resolve encounters visually
- Rewards and progression are visible in UI

---

## Milestone 5 — Unit & Dice Management (Strategic Depth)

### Objective
Enable players to manage roster and builds between runs.

### Scope
- Unit inventory
- Unit details and promotion
- Dice inventory and inspection

### Tasks
1. **Unit inventory**
   - Paginated unit list
   - Navigation to unit details

2. **Individual unit management**
   - Equip/unequip dice
   - View stats, abilities, and XP
   - Perform promotions

3. **Dice inventory**
   - Paginated dice list
   - Dice details view

### Exit Criteria
- Players can meaningfully customize units and teams

---

## Milestone 6 — Playability & Stability Pass

### Objective
Prepare the game for closed testing.

### Scope
- End-to-end verification
- Progression validation
- UX and error handling

### Tasks
1. **Full loop verification**
   - Start run → encounters → boss → end run

2. **Progression validation**
   - XP and leveling correctness
   - Promotion and loot economy sanity

3. **UX and stability**
   - Loading states
   - Graceful error handling

### Exit Criteria
- Game is stable enough for external testers

---

## Usage Notes

- Conversations should reference **milestone number + task number**
  - Example: “Milestone 0, Task 0.1”
- No work from later milestones should block earlier exit criteria
- This document is the authoritative roadmap for Dice Goblins
