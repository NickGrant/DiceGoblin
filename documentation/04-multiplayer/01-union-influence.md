# Union Influence System

Status: future  
Last Updated: 2026-03-07  
Owner: Product + Systems Design  
Depends On: `documentation/04-multiplayer/00-multiplayer-philosophy.md`, `documentation/00-overview/00-project-overview.md`

## Overview

The **Union Influence System** is a long-term, shared-world meta mechanic intended for v3/v4 of Dice Goblin. It introduces indirect player interaction through economic and social pressure rather than direct PvP.

Players influence fictional **trade unions** by investing resources for personal gain. As unions gain power, they unlock **new options** for the entire shard while subtly distorting the economy for everyone else. The result is a cold-war-style dynamic: cooperation, rivalry, favoritism, and resentment without hard blockers or player-to-player attacks.

This system is designed to:

* Create social tension without griefing
* Give veteran players meaningful outlets for influence
* Generate emergent narratives at the shard/season level
* Remain optional, recoverable, and resettable

---

## Design Pillars

The Union Influence System adheres to the following principles:

* **No Hard Lockouts** – Nothing is ever removed or blocked; only availability and efficiency shift.
* **Indirect Harm Only** – Players never directly target or attack other players.
* **Option Expansion, Not Power Scaling** – Unions unlock new choices, not raw stat bonuses.
* **Public State, Private Contribution** – World effects are visible; individual inputs are intentionally fuzzy.
* **Seasonal Reset** – All influence is temporary and refreshed regularly.

---

## Shard Scope

Union influence operates at the **shard level**, not globally across all players.

A shard represents a shared-world economic simulation containing roughly **~100 active players**. All union meters, thresholds, and unlocks apply only within that shard.

Shard segmentation allows the system to:

* Preserve meaningful player impact
* Prevent influence dilution at large scale
* Allow independent seasonal outcomes
* Support shard merges or splits if population changes

Players do not directly choose their shard. Shards are assigned and managed by the system.

---

## Unions

### Definition

A **Union** is a fictional trade organization representing a segment of the world economy (logistics, crafting, magical labor, transport, etc.). Unions are not NPCs, player guilds, or ideological factions. They exist to control **how options appear**, not whether they exist.

### Key Properties

Each Union has:

* A seasonal **Influence Meter**
* One or more **Rival Unions**
* A set of **Threshold Offerings** unlocked by collective investment

Unions do **not**:

* Punish or retaliate against players
* Enforce ideologies
* Target individuals or clans

---

## Player Interaction

Players may choose to:

* Invest gold or a meta-currency into one or more unions
* Call in favors or sponsor union activity
* Align softly with unions for efficiency or prestige

Reasons to invest:

* Personal discounts or efficiency
* Faster progression
* Access to better or more consistent options
* Social visibility or notoriety

Participation is **optional but tempting**.

---

## Baseline World Pressure

Union influence affects the shard economy globally.

Even players who never invest in unions experience minor economic distortions such as:

* Slight shifts in option availability
* Pricing pressure
* Supply bias toward dominant unions

These effects are intentionally **mild and reversible**. Participation in the system allows players to influence these pressures rather than avoid them entirely.

---

## Rivalry System

Unions exist in a web of **rivalries**.

Supporting one union increases its influence and applies **soft suppression** to rival unions.

### Rival Standing Effects

When a player invests in a union:

* Their standing with that union increases
* Their standing with rival unions decreases

This represents economic competition rather than ideological conflict.

Rival standing penalties are **soft and reversible**. They influence investment efficiency and threshold progress but never block participation.

### Suppression Effects

Suppression may appear as:

* Slower influence gain for rival unions
* Higher effective investment costs
* Delayed threshold timing

Suppression **never removes unlocked options or blocks access**.

---

## Seasonal Threshold Offerings

### Core Concept

Each Union has **seasonal thresholds**. When a threshold is reached:

* A **new option** becomes available to **all players** in the shard
* The option persists for the remainder of the season
* Unlocks are additive, not mandatory

Thresholds reset at season end.

### Threshold Tiers

| Tier   | Intent       | Notes                           |
| ------ | ------------ | ------------------------------- |
| Tier 1 | Onboarding   | Expected to unlock most seasons |
| Tier 2 | Competitive  | Primary source of shard tension |
| Tier 3 | Aspirational | Rare, season-defining unlock    |

---

## What Counts as an Option

Options are **new affordances**, not buffs.

Valid option examples:

* New shop categories or item pools
* Additional reroll or refinement choices
* New contract, bargain, or risk-reward offers
* Alternate crafting or modification paths
* Preview or forecasting tools

Invalid option examples:

* Flat stat bonuses
* Permanent discounts
* Multiplicative power increases

Design heuristic:

> Players gain the ability to **do a new thing**, not become stronger at existing actions.

---

## Visibility & UI Expectations

Players should always be able to see:

* Union influence meters
* Threshold milestones and rewards
* Active rivalries

Players should never see:

* Exact per-player investment amounts
* Precise contribution breakdowns

This preserves social ambiguity while maintaining system transparency.

---

## Influence Scaling

Union influence scaling may be adjusted depending on shard population.

During early development or low population periods:

* Individual player contributions may carry greater influence
* Threshold values may be reduced

As shard populations grow, scaling should be adjusted so that:

* Individual impact remains meaningful
* No single player can dominate union progress

---

## Seasonal Reset & Summary

At the end of a season:

* All union influence meters reset
* All unlocked options expire
* Prestige or cosmetic recognition may be awarded

Optional seasonal summaries may include:

* Most influential union
* Closest missed threshold
* Major contributors (anonymized or opt-in)

---

## Long-Term Intent

The Union Influence System is designed as a **modular pressure layer**.

* It does not directly affect combat balance
* It can be tuned primarily through numeric thresholds
* It can be themed or adjusted per season

Its purpose is to give Dice Goblin a distinctive identity as:

> *A game about selfish optimization inside a shared, stressed economy.*

---

## Versioning & Scope

* **Target Version:** v3 / v4
* **Dependencies:** None (meta-system)
* **Risk Level:** Low
* **Primary Audience:** Veteran players

This feature should be introduced without gating early progression and without mandatory tutorials.
