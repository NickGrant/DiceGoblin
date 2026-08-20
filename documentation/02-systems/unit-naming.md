---
Title: "Unit Naming"
Status: Canonical
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/UnitNameGenerator.php
  - backend/src/Services/OwnedUnitGrantService.php
  - backend/src/Repositories/UnitRepository.php
Category: 02-systems
Tags:
  - systems
---

# Unit Naming

## Purpose

Unit naming controls the display name stored on each owned unit instance. Names are assigned when a unit is granted and can later be changed through the rename endpoint.

## Creation Flow

```mermaid
flowchart TD
  A[Grant unit request] --> B[Load unit type by slug or id]
  B --> C{displayName argument is null?}
  C -- yes --> D[UnitNameGenerator::generate]
  C -- no --> E[trim displayName]
  D --> F[Pick random first segment]
  F --> G[Pick random second segment]
  G --> H[Concatenate into one name]
  E --> I[Create unit_instances row]
  H --> I
  I --> J[Initialize unit loadout]
  J --> K[Return owned unit]
```

## Name Generation Defaults

`UnitNameGenerator::generate()` builds a name by selecting one random entry from each list and concatenating them without a space or delimiter.

| Field | Default behavior |
| --- | --- |
| First segment | Random value from the `FIRST` list, such as `Ash`, `Bog`, `Iron`, or `Thorn`. |
| Second segment | Random value from the `SECOND` list, such as `back`, `fang`, `tooth`, or `wort`. |
| Random source | PHP `random_int`, so generation is non-deterministic. |
| Example shape | `Ashback`, `Ironfang`, `Thornwort`. |

## Grant Defaults

`OwnedUnitGrantService::grantBySlug()` applies these defaults unless the caller overrides them.

| Value | Default |
| --- | --- |
| `tier` | Parsed from a `_tN` unit-type slug suffix. If no suffix exists, defaults to `1`. |
| `level` | `1` |
| `xp` | `0` |
| `locked` | `false` |
| `displayName` | Generated if the argument is `null`. If a non-null value is passed, it is trimmed and stored. |
| Kin variant | Provided slug if present; otherwise rolled through the backend kin variant service. |

## Rename Flow

```mermaid
flowchart LR
  A[Frontend rename request] --> B[GameplayController::renameUnit]
  B --> C[Trim display_name]
  C --> D{1-32 characters?}
  D -- no --> E[400 validation_error]
  D -- yes --> F[UnitRepository::renameUnit]
  F --> G{Unit owned by user?}
  G -- no --> H[validation_error]
  G -- yes --> I[Update unit_instances.display_name]
```

## Display Fallback

When units are loaded for a user, `UnitRepository::getUnitsWithEquippedDiceForUser()` maps both `name` and `display_name` from `unit_instances.display_name` when it is not `null`. If the stored display name is `null`, it falls back to the unit type name.

The grant layer only auto-generates when `displayName` is exactly `null`. Callers that want generated names should pass `null`, not a blank string.
