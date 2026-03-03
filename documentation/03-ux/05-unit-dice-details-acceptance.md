# Unit and Dice Details Acceptance Criteria - MVP
----

Status: active  
Last Updated: 2026-03-03  
Owner: Product + UX + Frontend  
Depends On: `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/03-ux/02-warband-management.md`

## Purpose
- Define concrete acceptance criteria for Unit Details and Dice Details MVP surfaces.
- Remove ambiguity before implementation of typed view-model adapters and detailed UI panels.

## Unit Details Acceptance Criteria

### Required Fields
- Unit identity: display name and role/archetype label.
- Progression: `tier`, `level`, current `xp`, max-level state.
- Equipped dice list with slot index and die identifier.
- Ability inventory grouped by active/passive with stable ordering.

### XP/Level Presentation
- If unit is below max level:
  - show current XP value,
  - show progress affordance toward next level.
- If unit is at max level:
  - show explicit `MAX` state,
  - do not imply additional XP progress.

### Run-Scoped Read-Only Overlay (when active run exists)
- Show current run HP.
- Show current run status effects list (or `None`).
- Show `Defeated` state when applicable.
- Overlay is informational only; no direct mutation controls on this screen.

### Data Contract Expectations
- Missing optional fields must degrade gracefully (fallback text) and never crash scene rendering.
- Unknown ability IDs should render a safe fallback label while preserving row structure.

## Dice Details Acceptance Criteria

### Required Fields
- Die size (`d4`..`d10`).
- Rarity (`common|uncommon|rare`).
- Slot capacity.
- Affix list including value and type labeling.

### Affix Labeling Rules
- Conditional affixes must be explicitly labeled as conditional.
- Flat and percent affixes must use distinct notation.
- Empty affix slots should be represented consistently (for example `Empty`).

### Ownership and Equip Context
- Surface must indicate whether die is equipped and to which unit/slot when available.
- If not equipped, show inventory-only state without implying hidden assignment.

## Shared UX Criteria
- Desktop and mobile layouts preserve readability of all required fields.
- No hidden critical data behind hover-only affordances on mobile.
- Loading/error/empty states are explicit and non-blocking.

## Out of Scope
- Crafting, reroll, or upgrade interactions.
- Deep build-comparison tools.
- Cross-run diff visualizations.

## QA Sign-off Checks
- Unit details render correctly for: low-level, mid-level, max-level, and defeated-in-run units.
- Dice details render correctly for: no affixes, mixed affix sets, and conditional affix entries.
- Screens remain stable when backend omits non-required optional fields.
