# Encounter Scope — MVP

This document defines the **authoritative encounter, biome, enemy, and run-level scope** for the Dice Goblins MVP. Any encounter type, biome, enemy, or meta-progression system not explicitly defined here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP encounter system must:
- Fully exercise the combat system and unit progression
- Support Tier 3 promotion gating through biome-specific items
- Keep run structure simple and repeatable
- Avoid long-term meta progression complexity

The intent is to validate the end-to-end run loop, not long-term retention systems.

---

## 2. Encounter Types (Closed List)

Exactly **four** encounter types exist in MVP:

1. **Combat**
2. **Loot**
3. **Rest**
4. **Boss**

### XP Award Rules (MVP)
- Combat, Boss, and Loot encounters award XP.
- Rest encounters do not award XP.
- XP is awarded to units that were fielded and not defeated (survivors only).
- Award is not split: all surviving fielded units receive the same XP amount.
- XP application timing:
  - Combat/Boss XP is applied through reward/claim flow.
  - Loot encounter XP is deterministic and applied by encounter resolution rules.

### Explicitly Excluded
- Merchants
- Narrative-only encounters
- Choice-driven branching encounters
- Environmental hazards
- Event chains

### Rest Encounters
- Primary function: Recover
- Secondary function: Allow editing the active run squad snapshot
  - adjust formation
  - swap units

---

## 3. Biome Scope

The MVP includes **exactly two biomes**.

### 3.1 Mountains Biome

- Theme: Rocky highlands
- Enemy Faction: Kobolds
- Tier 3 Advancement Item: **Roc Egg**

### 3.2 Swamps Biome

- Theme: Wetlands and marshes
- Enemy Faction: Frogmen
- Tier 3 Advancement Item: **Gator Head**

### Biome Rules
- Each biome has its own enemy pool
- Each biome has exactly one boss
- Tier 3 promotion items are biome-exclusive

---

## 4. Enemy Roster Scope (Per Biome)

Each biome supports the same **enemy role distribution**, reskinned per faction.

### Enemy Types Per Biome

| Role | Tier | Count |
|----|------|-------|
| Frontline | Tier 1 | 1 |
| Frontline | Tier 2 | 1 |
| Backline | Tier 1 | 1 |
| Backline | Tier 2 | 1 |
| Specialty | Tier 1 | 1 |

**Total enemies per biome:** 5

### Specialty Unit Guidelines
- Has a unique mechanic or status interaction
- Does not exceed Tier 1
- Exists to test edge-case combat behavior

---

## 5. Boss Encounters

Each biome contains:
- Exactly **one boss encounter**
- Bosses are biome-themed and mechanically distinct

Boss Rules:
- Boss encounters are always combat encounters
- Boss encounters are the primary source of Tier 3 promotion items
- Bosses do not introduce new mechanics beyond MVP scope

---

## 6. Run Structure

Each run:
- Occurs entirely within a single biome
- Contains:
  - Multiple combat encounters
  - A small number of loot and rest nodes
  - Exactly one boss encounter
- Rest nodes are the only nodes where run-snapshot squad editing is allowed mid-run
- Nodes are structured in a branching shape
- Nodes become unlocked when any Node with a connecting path is resolved (Victory for combat Nodes or just encountered for other Nodes)
- The first Node in a run starts unlocked
- Nodes cannot become locked again

Runs do not span multiple biomes.

---

## 7. Energy System Scope

### Energy Rules

- Maximum energy: **50**
- Energy regeneration rate: **1 energy per 5 minutes**
- Cost per run: **5 energy**

Energy Rules:
- Energy is required to start a run
- Energy is not consumed per encounter
- Energy is not refunded on run failure or abandonment

---

## 8. Meta Progression

### MVP Decision

- **No meta progression systems exist in MVP**

Explicitly Excluded:
- Permanent stat bonuses
- Unlock trees
- Account-wide modifiers
- Cross-run buffs

All progression is run-scoped or unit-scoped only.

---

## 9. Explicit Non-Goals

The MVP encounter system does **not** include:
- Cross-biome runs
- Biome-specific modifiers
- Enemy scaling beyond tier and level
- Dynamic encounter difficulty
- Player choice affecting encounter composition

---

## 10. MVP Validation Criteria

The encounter system is considered MVP-complete when:
- Players can complete full runs in both biomes
- Tier 3 promotion items can be earned in each biome
- Enemy role variety produces distinct combat scenarios
- Boss encounters feel meaningfully different from normal fights
- The energy system naturally paces play without frustration

---

This document is considered **locked** for MVP unless explicitly revised.
