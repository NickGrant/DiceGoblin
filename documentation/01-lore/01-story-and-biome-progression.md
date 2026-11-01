---
Title: "Dice Goblins - Story and Biome Progression"
Status: Canonical
Last Updated: 2026-10-31
Owner: Narrative Design
Depends On:
  - documentation/01-lore/00-world-and-lore.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/vnext-progression-state-model.md
Category: 01-lore
Tags: [lore, story, progression, biomes]
---

# Dice Goblins - Story and Biome Progression

## Purpose

This document is the canonical narrative progression authority for story-gated biome and system unlocks. It records what the player learns, what event advances the opening campaign, and which durable gameplay access follows from that event.

Implementation must preserve these narrative dependencies while expressing durable state through the accepted vNext progression, reward, unlock, dialogue, Codex, and authored-content systems. Narrative sequencing does not justify a parallel generic story-flag or tutorial-history system.

`documentation/01-lore/00-world-and-lore.md` remains authoritative for setting fundamentals. The base-game content roster remains authoritative for biome/enemy/kin allocation.

## Opening Arc

The opening arc is deliberately directed. The player progresses through Mystic Cave -> Farm -> Mountains -> Swamps before the broader world opens.

### 1. The Whim and the First Goblins

At the start of the game, the player's only meaningful gameplay option is to interact with The Whim.

The Whim has the introductory conversation with the player and turns the player from Chaos into goblin form. This establishes the player's first Warband and grants access to The Farm.

The Whim points the new goblins toward their first problem: visit the Mudking and rescue The Whim's friend, the Tooth Collector.

Canonical consequences:
- the player receives the initial Basic Goblin Warband;
- The Farm becomes available;
- Mountains and Swamps remain unavailable;
- the Wrong Machine may be introduced as future knowledge, but the player does not possess or have access to it.

### 2. The Farm and the Tooth Collector

The player's first regional objective is to challenge the Farm, confront the Mudking, and recover the Tooth Collector.

The first Mudking victory rescues the Tooth Collector. Returning with the Tooth Collector establishes the ordinary Shop/economy relationship.

After the Tooth Collector is recovered, The Whim asks the player to find the missing Wrong Machine and suggests beginning with the kobolds in the Mountains. This story beat unlocks the Mountains.

Canonical consequences:
- first Mudking victory rescues the Tooth Collector;
- rescuing the Tooth Collector unlocks the Shop;
- the post-Farm Whim conversation establishes the search for the Wrong Machine;
- that progression unlocks the Mountains;
- the player still does not possess or have access to the Wrong Machine.

The Farm boss reward and the Mountains unlock are therefore related story progression, but implementations should preserve the player-facing return/conversation beat rather than collapsing the entire narrative into an unexplained boss reward when presentation work exists for it.

### 3. Mountains and the Wrong Machine Trail

The Mountains continue the search for the Wrong Machine. The kobolds provide the next link in the trail rather than the Machine itself.

During the Mountains chapter, the player learns that the Wrong Machine has already been moved. Defeating the Mountains boss establishes that it was taken to the Swamps and unlocks the Swamps as the next destination.

Canonical consequences:
- the Mountains reveal that the Wrong Machine is no longer there;
- first Mountains boss victory establishes the Swamps as the Machine's current destination;
- the Swamps become available;
- the Wrong Machine remains unavailable to the player.

Exact kobold responsibility, motive, and custody details may be expanded in authored dialogue, but they must not contradict the core fact that the player follows the Machine's trail from Mountains to Swamps.

### 4. Swamps, the Archivist, and Recovery of the Wrong Machine

The player's first visit to the Swamps is the first direct encounter with The Archivist.

This is the Archivist's introduction to the player-facing story. Earlier lore may establish the Archivist's existence or historical role, but normal campaign presentation must not stage a direct player/Archivist encounter before this Swamp introduction unless this document is deliberately revised.

The player continues through the Swamps and confronts its boss. The first successful Swamp boss victory recovers the Wrong Machine and grants permanent access to it.

Canonical consequences:
- first Swamp visit introduces The Archivist directly;
- first Swamp boss victory grants `capability.wrong_machine_access` through the normal permanent reward/unlock architecture;
- repeat boss victories do not grant duplicate machine access;
- the Wrong Machine becomes available for its accepted reconstruction gameplay;
- Frog Kin belongs to the Swamps/Frogmen lineage relationship and integrates through the normal Wrong Machine reconstruction architecture.

Mystic Cave knowledge of the Wrong Machine is not access. Farm or Mountains knowledge of its trail is not access. The first successful Swamp boss victory is the canonical recovery/unlock event.

### 5. First Reconstruction and the Broader Map

Recovering the Wrong Machine is not itself the end of the directed opening arc. The player must use the Wrong Machine for the first time.

After the player's first successful Wrong Machine reconstruction/use, The Whim gains new dialogue encouraging the player to venture farther into the world and discover additional kinds of goblins.

That beat opens the broader map beyond the Farm -> Mountains -> Swamps opening path.

Canonical consequences:
- the first successful Wrong Machine use is a meaningful progression event;
- afterward, The Whim has new dialogue about exploring farther and finding new goblin forms;
- completion of that progression beat opens the remaining broader-map region progression;
- later biome ordering and individual unlock requirements remain owned by their canonical content/progression definitions and may be authored incrementally.

The implementation should persist the smallest durable gameplay result needed for this progression. Do not add a generic `story_progress`, `chapter`, `has_seen_dialogue`, or catch-all history state solely to represent these beats. Where a durable unlock or capability can represent the resulting state, prefer that accepted model.

## Canonical Opening Progression Summary

1. **Start -> The Whim only.** Introductory Whim interaction.
2. **The Whim -> Basic Goblins.** Player receives the first Warband and Farm access.
3. **Farm -> Mudking.** First victory rescues the Tooth Collector and unlocks the Shop.
4. **Return -> The Whim.** The Whim sends the player after the missing Wrong Machine; Mountains unlock.
5. **Mountains -> boss.** Player learns the Machine was moved to the Swamps; Swamps unlock.
6. **First Swamp visit.** The player encounters The Archivist directly for the first time.
7. **Swamp -> boss.** First victory recovers/unlocks the Wrong Machine.
8. **First Wrong Machine use.** The player performs their first reconstruction/use.
9. **Return -> The Whim.** The Whim encourages wider exploration and the broader map opens.

## Development Constraints

- New story content must not bypass or reorder the opening progression without an explicit canonical update.
- Do not introduce The Archivist directly to the player before the first Swamp visit.
- Do not grant Wrong Machine access before the first successful Swamp boss victory.
- Do not open the broader post-Swamp map merely because the Swamp was discovered or completed; the intended story beat follows the player's first Wrong Machine use and subsequent Whim dialogue.
- Region/system unlocks should use existing permanent unlock/reward/capability authority where semantically appropriate.
- Dialogue and Codex may communicate story state but should not become a generic hidden story-state system.
- Repeat runs must not duplicate one-time permanent story rewards.
- Later narrative details remain flexible unless explicitly marked canonical in this document or another current canonical lore authority.
