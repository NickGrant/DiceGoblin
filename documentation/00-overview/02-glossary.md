---
Title: "Game Glossary"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product
Depends On:
  - documentation/00-overview/00-project-overview.md
  - documentation/01-lore/00-world-and-lore.md
Category: 00-overview
Tags: [overview, glossary]
---

# Game Glossary

- **Camp** - between-run game space and mental model for management/progression systems.
- **Run** - one persisted attempt through a region from creation to success, failure, or abandonment.
- **Region / Biome** - themed run content area such as the Farm, Mountains, or Swamps.
- **Node** - one location/state in a generated or authored run graph.
- **Battle Playback** - presentation of a battle already resolved by the authoritative PHP combat engine.
- **Tick** - atomic scheduling step used by combat. It is not a persistent unit stat.
- **Unit** - one owned goblin combatant instance.
- **Unit Type** - authored combat/progression archetype such as Bruiser or Saboteur.
- **Kin** - a restored goblin form associated with traits recovered from a creature family. Kin is the player-facing term; do not revive legacy splice/lineage terminology in new implementation.
- **Squad** - a named saved configuration of units. A player may own multiple squads and selects one active squad outside a run.
- **Formation** - the squad's nine-position combat arrangement.
- **Ability Loadout** - a unit's ordered set of equipped combat abilities.
- **Dice Binding** - assignment of an exact owned die instance to a specific unit ability slot.
- **Die / Dice Instance** - owned mutable instance with size and an authored dice-profile ID.
- **Dice Profile** - authored composition of material, explicit rarity, aspects, and allowed sizes.
- **Material** - inherent authored die identity/behavior. Material does not determine rarity.
- **Aspect** - authored die modifier/effect with its own eligibility constraints.
- **Teeth** - ordinary repeatable currency for goods/services.
- **Raw Chaos** - scarce currency/essence primarily used to change capability; the Wrong Machine is its intentional repeatable sink.
- **Energy** - regenerating pacing resource spent when a run is successfully created; not a currency.
- **The Wrong Machine** - ancient chaos-working device used to reconstruct goblins/kin.
- **Event** - semantic fact emitted by a successful resolution that may feed rewards/objectives/progression.
- **Reward** - authored possible grant resolved from an event and finalized exactly once.
- **Effect / State Transition** - authoritative mutation such as damage, healing, spending, consumption, equipment change, or run movement; not a generic reward.
- **Unlock** - unique persistent access/capability reward.
- **Codex Entry** - unique persistent knowledge/discovery.
- **Objective** - persistent numeric progress toward an authored goal.
- **Bounty** - an objective type, not a separate persistence domain.
- **Player Revision** - monotonically increasing server value used by Phaser as a stale-cache/reconnect signal.
- **The Whim** - chaos-aligned entity from which goblins manifest.
- **The Archivist** - champion of Order and central antagonist.
