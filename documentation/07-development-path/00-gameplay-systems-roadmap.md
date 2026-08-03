---
Title: "Gameplay Systems Roadmap"
Status: Needs Review
Last Updated: 2026-08-02
Owner: Product
Depends On:
  - agent/ISSUES.md
  - agent/MILESTONES.md
  - documentation/07-development-path/2026-07-30-first-pig-kin-demo-roadmap.md
  - documentation/07-development-path/01-base-game-content-roster.md
  - documentation/07-development-path/02-night-expansion-content-roster.md
Category: 07-development-path
Tags:
  - development-path
---

# Gameplay Systems Roadmap

## Purpose

Keep the current gameplay roadmap easy to scan.

The active execution target is the first Pig Kin demo release. Older roadmap work is historical context only unless it has been promoted back into `agent/ISSUES.md`.

## Active Roadmap

- Current roadmap: `documentation/07-development-path/2026-07-30-first-pig-kin-demo-roadmap.md`
- Active milestone: `First Pig Kin Demo Release`
- Execution source of truth: `agent/ISSUES.md` and `agent/MILESTONES.md`

## Demo Release Boundary

The next formal demo ends when a fresh player creates their first Pig Kin through the Wrong Machine.

Work before that point should improve:

- required story and dialogue clarity;
- current-objective guidance;
- chaos, hazard, shrine, and consumable reliability;
- Wrong Machine first-reconstruction presentation;
- academy, promotion, warband, unit, guide, Codex, and run-node usability;
- release hardening and UAT evidence.

## Approved Post-Demo Content Direction

The complete planned base game contains ten standard progression biomes plus two special biomes:

```text
Mystic Cave
  → The Farm
  → Mountains
  → Swamps
  → Island
  → Tundra
  → Volcano
  → Wasteland
  → River
  → Orchard
  → Savanna
  → The Library
```

The authoritative biome, enemy-family, and kin pairings are defined in `documentation/07-development-path/01-base-game-content-roster.md`.

Post-demo development expands toward:

- the remaining base-game kin lineages beyond Pig Kin;
- biome-specific enemy rosters, bosses, materials, hazards, shrines, encounters, rewards, dialogue, and art;
- richer dice progression after the current material model stabilizes;
- the final Library encounter arc;
- optional routes, backtracking, advanced progression, and repeatable endgame content.

The first expansion, **Night**, is separately allocated to Ruins, Meadow, and Cemetery with Raccoon, Moth, and Crow Kin. Its authoritative planning roster is `documentation/07-development-path/02-night-expansion-content-roster.md`.

Post-demo base-game work and Night expansion work remain in backlog tracker files until deliberately promoted into the active milestone.

## Roadmap Maintenance Rules

- Keep only the current implementation lane in active tracker files.
- Keep future campaign expansion in backlog tracker files until promoted.
- Use the approved roster documents for future biome, enemy-family, and kin allocation.
- Keep Mountains and Swamps plural in player-facing and planning documentation.
- Treat `documentation/07-development-path/2026-07-25-roadmap.md` and `documentation/07-development-path/2026-07-25-completion-analysis.md` as historical implementation context.
- Treat the older proposed Chapters 5-11 in `documentation/01-lore/01-story-and-biome-progression.md` as superseded by the approved base-game roster.
- Update this file when the active milestone or release boundary changes.
