---
Title: "Approved Base-Game Biome, Enemy, and Kin Roster"
Status: Canonical
Last Updated: 2026-08-02
Owner: Product + Content Design
Depends On:
  - documentation/07-development-path/00-gameplay-systems-roadmap.md
  - documentation/03-content/02-kin-types.md
  - documentation/03-content/03-enemy-types.md
  - documentation/03-content/06-biomes-and-regions.md
Category: 07-development-path
Tags:
  - development-path
  - biomes
  - enemies
  - kin
  - base-game
---

# Approved Base-Game Biome, Enemy, and Kin Roster

## Purpose

Define the approved biome, native enemy-family, and goblin-kin roster for the complete base game.

This document is canonical for the planned release roster. It does **not** make future biomes, enemy units, kin statistics, encounters, rewards, or reconstruction recipes current playable content. Current content remains owned by the catalogs under `documentation/03-content/` and must be promoted there as each phase enters implementation.

## Naming Standard

The official region names are plural:

- **Mountains**, not Mountain
- **Swamps**, not Swamp

The existing stable region keys remain `mountains` and `swamps`.

## Base-Game Structure

The complete base game contains:

- ten standard progression biomes;
- Mystic Cave as the introductory and recurring special biome;
- The Library as the final special biome;
- ten biome-associated kin across the standard roster;
- Basic Goblin as the origin identity established in Mystic Cave rather than a standard enemy-derived kin.

The current demo milestone still ends with the player's first Pig Kin reconstruction. Approval here does not move later kin or biomes into the active demo scope.

## Standard Biome Roster

| Order | Biome | Native enemy family | Associated kin | Planning notes |
| ---: | --- | --- | --- | --- |
| 1 | The Farm | Pigs | Pig Kin | Farmyard occupiers and the first reconstructable kin. The current demo scope reaches this kin. |
| 2 | Mountains | Kobolds | Lizard Kin | Kobold tinkerers and trap makers provide the creature-family basis for Lizard Kin. The biome and enemies are current; the kin remains post-demo content. |
| 3 | Swamps | Frogmen | Frog Kin | Frogman rulers and scavengers provide the creature-family basis for Frog Kin. The biome and enemies are current; the kin remains post-demo content. |
| 4 | Island | Monkeys | Monkey Kin | Agile thieves and climbers. Order has organized their loose troops into harvesting crews, coastal patrols, and treetop guards. |
| 5 | Tundra | Walrusfolk | Walrus Kin | Large, quarrelsome scavengers gathered around fishing grounds and wreckage. Order divides them into hauling crews, hunters, and armored defenders. |
| 6 | Volcano | Salamanders | Salamander Kin | Heat-resistant scavengers drawn to hot metal, charcoal, and volcanic glass. Order has transformed them into foundry workers and weapon makers. |
| 7 | Wasteland | Vultures | Vulture Kin | Patient carrion scavengers who follow conflict and pick through the remains. Order has made them battlefield cleaners, corpse collectors, and equipment reclaimers. |
| 8 | River | Otters | Otter Kin | Playful thieves that steal food, tools, and building materials. Order has forced them to operate dams, ferries, locks, and river checkpoints. |
| 9 | Orchard | Wasps | Wasp Kin | Irritable fruit thieves that nest in trees and abandoned equipment. Order has reorganized them into rigid harvesting, pollination, and defense castes. |
| 10 | Savanna | Hyenas | Hyena Kin | Cruel, cackling pack scavengers that torment weaker creatures. Order has imposed hunting ranks, coordinated formations, and strict pack hierarchies. |

The listed order is the approved working base-game sequence. Reordering a future biome requires updating this document before or alongside dependent narrative and progression work.

## Special Biomes

| Biome | Enemy identity | Kin relationship | Campaign role |
| --- | --- | --- | --- |
| Mystic Cave | Chaos beings and manifestations associated with The Whim | Establishes the Basic Goblin origin; no standard enemy-derived kin pairing | Introductory region, narrative staging area, Wrong Machine home, and recurring chaos content. |
| The Library | The Archivist's order-aligned forces, including armored humanoid defenders | No standard enemy-derived kin pairing currently approved | Final base-game biome and campaign confrontation with The Archivist. |

## Content Promotion Boundary

A planned roster entry becomes current content only after the relevant canonical catalogs define its complete package.

At minimum, promotion requires:

- a stable biome key, display identity, progression placement, energy cost, and visual direction;
- concrete enemy unit types, roles, statistics, abilities, rewards, and encounter formations;
- a complete kin identity with statistics or mechanics, reconstruction inputs, discovery rules, and reward eligibility;
- boss identity and catalyst direction where applicable;
- dialogue, Codex, item, reward, hazard, shrine, and run-generation integration;
- implementation and player-facing art.

The approved family and kin names in this document must not be treated as sufficient runtime definitions.

## Relationship to Earlier Planning

This roster supersedes the proposed Chapters 5-11 in `documentation/01-lore/01-story-and-biome-progression.md`, including the Ordered Hive, Haunted Ruins, Deep Stoneworks, Cloudbreak Aerie, Sunscorched Expanse, Frozen Tundra, and Fungal Wilds sequence.

Narrative principles from that document may still be reused, but those older biome names and enemy-family assignments are no longer the approved base-game roster.

## Expansion Boundary

Ruins, Meadow, Cemetery, Raccoons, Moths, Crows, and their associated kin are not base-game content. They are reserved for the first expansion, **Night**, in `documentation/07-development-path/02-night-expansion-content-roster.md`.

## Maintenance Notes

- Keep Mountains and Swamps plural in player-facing and planning documentation.
- Update this document when the approved roster, sequence, or biome-family-kin pairing changes.
- Update current content catalogs separately when a planned entry enters implementation.
- Do not move Night expansion content into the base-game roster without an explicit release-scope decision.
