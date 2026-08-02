---
Title: "Unit Stat Advancement"
Status: Canonical
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/UnitProgressionService.php
  - backend/src/Repositories/UnitRepository.php
  - backend/src/Repositories/RunRepository.php
  - backend/src/Services/RunLifecycleService.php
Category: 02-systems
Tags:
  - systems
---

# Unit Stat Advancement

## Purpose

Unit stat advancement determines level-scaled combat stats, XP thresholds, automatic leveling, and run HP persistence.

## Stat Resolution Flow

```mermaid
flowchart TD
  A[Load owned units] --> B[Read unit type base_stats_json]
  B --> C[Apply kin stat modifiers]
  C --> D[Resolve attack, defense, max HP by level]
  C --> E[Resolve precision and resolve from base stats]
  D --> F[Return frontend unit model]
  E --> F
```

## Stat Defaults

`UnitProgressionService` accepts decoded arrays or JSON strings. Invalid or missing base stat data resolves to an empty array and then uses defaults.

| Stat | Base default | Minimum | Level scaling |
| --- | ---: | ---: | --- |
| `attack` | `1` | `1` | `base + attack_per_level * max(0, level - 1)` |
| `defense` | `0` | `0` | `base + defense_per_level * max(0, level - 1)` |
| `max_hp` | `1` | `1` | `base + max_hp_per_level * max(0, level - 1)` |
| `precision` | `5` | `0` | Does not scale with level. |
| `resolve` | `5` | `0` | Does not scale with level. |

Per-level values are clamped to `0` or higher before being applied.

## XP and Auto-Level Defaults

```mermaid
flowchart TD
  A[XP added to unit] --> B[RunRepository::applyAutoLevelForRunUnits]
  B --> C{level < max_level?}
  C -- no --> D[Stop]
  C -- yes --> E[Cost = tier * (level + 1) * 50]
  E --> F{xp >= cost?}
  F -- no --> D
  F -- yes --> G[Subtract cost and add one level]
  G --> C
```

| Value | Behavior |
| --- | --- |
| XP cost | `tier * (level + 1) * 50` |
| Level floor | `1` |
| XP floor | `0` |
| Max level floor | `1` |
| Tier floor | `1` |
| XP to next at max level | `0` |

Auto-leveling can consume multiple thresholds in one pass if enough XP has been banked.

## Run Reward Application

Battle claims apply XP through `RunLifecycleService::applyBattleRewardsAndXp()`.

```mermaid
sequenceDiagram
  participant C as Battle claim
  participant L as RunLifecycleService
  participant R as UnitRepository
  participant G as RunRepository

  C->>L: claimBattle(userId, battleId)
  L->>L: verify battle is unclaimed
  L->>R: add XP to surviving non-mastered units
  L->>G: persist run HP changes
  G->>G: applyAutoLevelForRunUnits
  L-->>C: claim snapshot with progression
```

Only combat-like nodes apply XP and run HP changes: `combat`, `boss`, and `chaos`. Defeated units do not receive XP. Units already at max level are skipped.

## HP Model

- In normal unit inventory views, `current_hp` is presented as resolved `max_hp`.
- During active runs, authoritative HP is stored in `run_unit_state`.
- Combat logs are preferred for HP changes. If no detailed HP event data is available, `RunLifecycleService` applies a deterministic fallback HP loss percentage.
