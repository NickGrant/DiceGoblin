# Rework Normalization Pass

Status: planning  
Last Updated: 2026-04-18  
Owner: Product + Engineering  
Depends On: `documentation/02-systems-mvp/09-ability-loadout-combat-rework-plan.md`, `documentation/01-architecture/04-data-model.md`

## Purpose
- Capture cleanup and consolidation work that should follow the combat/loadout rework.
- Prevent the implementation from preserving too many legacy layers out of convenience.

## Normalization Goals

- Reduce documentation sprawl once authoritative replacements are live.
- Collapse migration history into a cleaner canonical baseline after rollout stabilizes.
- Remove duplicated test scaffolding built around superseded mechanics.
- Consolidate styling and scene-local UI rules where repeated patterns have emerged.

## Target Cleanup Areas

### 1. Documentation
- Archive or remove superseded planning notes once authoritative docs are fully implemented.
- Trim overlapping docs that say the same thing at different levels of certainty.
- Keep one canonical entry point per system.

### 2. Database and Migrations
- Compact legacy migration history after the new schema stabilizes.
- Remove transitional pooled-dice compatibility structures when no longer needed.
- Prefer a normalized long-term schema over permanent dual-write or fallback logic.

### 3. Tests
- Refactor tests that encode the old modulo scheduler or pooled-dice behavior.
- Consolidate duplicate fixtures that differ only because systems were added piecemeal.
- Prefer shared builders/helpers for unit loadouts, enemy loadouts, and seeded starter units.

### 4. Frontend Structure
- Identify repeated scene layout code that should become shared helpers or components.
- Review styles, panel spacing, and repeated typography rules for consolidation.
- Remove UI pathways that only exist to support superseded dice-management assumptions.

## Timing Guidance

- Do not block the core combat/loadout implementation on full normalization.
- Do schedule normalization immediately after the rework is functionally complete and verified.
- Prefer one deliberate cleanup lane over many small reactive cleanups spread through feature work.

## Success Criteria

The normalization pass is successful when:
- the repo has one clear authoritative doc path per reworked system
- legacy pooled-dice and modulo-scheduler assumptions are removed rather than merely hidden
- migrations and tests feel intentionally organized rather than historically accumulated
- frontend styling and repeated layout logic are materially more compact
