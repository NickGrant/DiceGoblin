---
Title: "Biome and Region Design Reference"
Status: Transitional vNext Reference
Last Updated: 2026-09-10
Owner: Content Design + Narrative Design
Depends On:
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/vnext-progression-state-model.md
Category: 03-content
Tags: [content, biomes, regions, vnext]
---

# Biome and Region Design Reference

## Opening Regions
- **The Farm** - pigs; Mudking; first full vNext combat/run slice; associated with Pig Kin.
- **Mountains** - kobold tinkerers/trap makers; first region-generalization test; associated with Lizard Kin.
- **Swamps** - Cajun-coded frogmen; later parity/repeatability region; associated with Frog Kin.
- **Mystic Cave** - special Chaos/Whim region used for onboarding and recurring special content; no standard enemy-derived kin.
- **The Library** - final special biome associated with The Archivist and Order forces; no standard enemy-derived kin currently approved.

The complete planned base-game sequence/family allocation is owned by `07-development-path/01-base-game-content-roster.md`.

## Progression Rule
Do not persist a generic region-complete flag by default. Boss/completion events grant the next region unlock directly when appropriate. Add completion history only if a concrete future mechanic needs it.

## vNext Content Rule
Region definitions, node/generation configuration, enemy/encounter references, rewards, and tuning become canonical JSON as each milestone is implemented. Exact prototype Energy costs, unlock scenes, dialogue seen-state, or Wrong Machine custody/recovery logic are not canonical merely because older content documents or services contained them.

Farm is implemented first, Mountains proves generalization, and Swamps later proves repeatability after the broader metagame/encounter systems exist. Mystic Cave onboarding is intentionally later in the overhaul roadmap rather than a prerequisite for proving the initial technical architecture.
