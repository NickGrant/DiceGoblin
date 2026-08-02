---
Title: "Content Source Map"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design + Engineering
Depends On:
  - documentation/03-content/README.md
  - documentation/05-technical/09-seed-catalog-ownership.md
Category: 03-content
Tags:
  - content
  - source-map
---

# Content Source Map

## Purpose

- Map concrete game-content categories to their current implementation sources.
- Keep `03-content/` useful before full catalog docs are written.
- Provide a clean stopping point before deeper review of existing doc sections, seed rows, and player-facing copy.

## Content Categories

| Category | Current source of truth | Notes |
| --- | --- | --- |
| Biomes and regions | `backend/migrations/02_regions.sql`, `backend/src/Services/RunGraphGenerator.php`, `frontend/src/app/core/regions/region-catalog.ts` | Region docs should reconcile seed data, generated-map behavior, and visual theme helpers. |
| Unit types | `backend/migrations/03_unit_types.sql`, `backend/migrations/30_seed_unit_types.sql`, later unit rebalance migrations | Full catalog requires reviewing tier names, stats, ability sets, and unlock rules. |
| Kin and lineages | legacy splice migrations/services plus Wrong Machine services | Player-facing docs should use kin/lineage language while noting legacy storage only when relevant. |
| Enemies | `backend/migrations/06_enemy_templates.sql`, `backend/migrations/31_seed_enemy_templates.sql`, rebalance migrations, encounter templates | Full catalog requires enemy stats, families, abilities, regions, and boss status. |
| Dice definitions and affixes | `backend/migrations/04_dice_definitions.sql`, `backend/migrations/37_seed_dice_definitions.sql`, dice affix migrations | Full catalog should separate dice size/rarity from affix behavior. |
| Items and consumables | generic item migrations/services and reward grant paths | Full catalog should distinguish progression materials, catalysts, healing items, and energy items. |
| Encounters | `backend/migrations/13_encounter_templates.sql`, `backend/migrations/34_seed_encounter_templates.sql`, run generation services | Full catalog should identify combat, boss, loot, rest, dialogue, hazard, shrine, and chaos use. |
| Hazards and shrines | `backend/src/Services/EncounterPrimitiveCatalog.php` | Current behavior is summarized in `documentation/02-systems/07-hazard-severity-and-downsides.md`; content catalog can later list each slug. |
| Dialogue and lore unlocks | `frontend/public/assets/data/dialogue/dialogue-scripts.json`, dialogue services, run graph generation | Full catalog requires narrative review and should not be inferred only from file names. |
| Codex entries | current codex ownership/services and upcoming Codex Discovery Reward Rework | The ownership model is actively changing, so catalog docs should stay light until that work lands. |

## Catalog Writing Rule

Catalog docs should describe concrete authored content and point to system docs for rules. For example, an enemy catalog can list enemy family, biome, stats, and abilities, but combat targeting or XP formulas belong in `02-systems/`.

## Review Boundary

Creating complete content catalogs requires reviewing seed SQL, code-owned catalogs, frontend data files, and existing documentation sections for duplicated or misplaced content. That deeper pass is intentionally deferred from this structural cleanup.
