# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## First Pig Kin Demo Release

**Status:** Active

Prepare a formal demo build whose critical path ends when a player creates their first Pig Kin through the Wrong Machine.

Success criteria:

- A fresh player can understand and complete the route from first session through Wrong Machine recovery.
- The player can collect or receive the required Pig Kin materials, Raw Chaos, and catalyst without random progression failure.
- The Wrong Machine clearly explains and completes the first Pig Kin reconstruction.
- Run hazards, shrines, chaos nodes, consumables, objectives, and reward screens are stable enough to support the demo path.
- Core demo UI surfaces are readable and navigable on desktop and mobile.
- Release gates, migrations, generated artifacts, and production configuration are checked before the demo handoff.

### Related Issues

- Complete first Pig Kin critical-path UAT
- Finish required demo dialogue and repeat-run story clarity
- Rework objectives for demo guidance
- Validate and revise chaos nodes for demo
- Finish hazard behavior needed for demo UAT
- Verify consumable unlocks, inventory, and balance
- Rework Wrong Machine first-reconstruction UI
- Stabilize academy promotion flow for demo
- Polish warband and unit management for demo
- Review guide and codex navigation/content
- Convert run-node resolution toward modal presentation
- Enable post-Wrong-Machine Farm generated-map behavior
- Complete demo release hardening

## Codex Discovery Reward Rework

**Status:** Planned

Rework Codex acquisition so enemy and biome records feel stolen or earned, while unlockable systems record themselves when the player actually receives access.

Success criteria:

- Defeated enemy copies can roll one-time enemy Codex page drops as combat loot.
- Bosses grant their enemy page and biome page on first completed run for that biome.
- Features, unit types, kin, affixes, and items grant Codex entries when the player first obtains or unlocks the thing.
- Lore/stolen pages continue to unlock when encountered through dialogue.
- Codex entries show useful known data such as enemy stats and abilities, with placeholders for locked entries.

### Related Issues

- Add durable Codex entry ownership and profile payload support
- Award enemy and biome Codex pages through combat/run rewards
- Award Codex entries from unlock and item acquisition events
- Expand Codex and reward UI for useful discovered entries
