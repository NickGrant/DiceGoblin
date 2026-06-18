# Unit Progression Rework

Status: active design reference  
Last Updated: 2026-06-18  
Owner: Systems Design + Engineering  
Depends On: `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/09-ability-loadout-combat-rework-plan.md`, `documentation/02-systems-mvp/10-promotion-structure-evaluation.md`

## Purpose

This document defines the revised unit progression, promotion, capstone, and branching class rules for Dice Goblins.

The goal is to create a meaningful choice between:

- promoting early at level 6 for faster tier advancement
- continuing to level 10 to unlock a passive capstone that follows the unit through future promotions

The rework should also make higher-tier unit types more specialized than Tier 1 units. Tier 2 and Tier 3 promotions should feel like class choices, not only stat increases.

## Core Progression Rules

- Every unit type has a max level of 10.
- Promotion eligibility begins at level 6.
- Level 10 unlocks a choice between two passive capstone abilities.
- Capstone abilities follow the normal ability inheritance model when a unit promotes.
- Capstones must be passive abilities.
- Tier 2 and Tier 3 unit types grant an active ability and a passive ability immediately when the class is chosen.
- Tier 2 and Tier 3 unit types also unlock a passive capstone choice at level 10.
- The player is not asked to make decisions on normal level-up except for capstone selection and promotion.
- If a unit promotes before level 10, it skips that unit type's capstone unless it later has another valid way to return to that class and reach mastery.

## Capstone Choice UX

Normal level-up is currently non-interactive, so capstone selection needs a dedicated interaction point.

Recommended behavior:

- When a unit reaches level 10, mark the unit as ready for capstone selection.
- Surface capstone choice on the unit detail screen and/or the promotion screen.
- If a level 10 unit is promoted before choosing a capstone, require the capstone choice before the promotion is confirmed.
- Promotion preview should clearly show whether the unit has mastered the current type and which passive capstone will be inherited.

## Active Ability Dice Rule

All active abilities must consume at least one die.

Each active ability therefore needs at least one variable component that can scale from the die value. The variable component does not always need to be direct damage. It can also scale:

- damage
- healing
- shield amount
- number of defense stacks
- debuff strength
- buff strength
- poison or damage-over-time value
- mark strength
- number of affected targets, if supported safely

For stack-style defensive buffs, use a half-die value when full die scaling would be too large:

```text
halfDie = ceil(dieValue / 2)
```

Example: if an active defensive ability uses a d6 roll of 5, it can grant `ceil(5 / 2) = 3` defense stacks.

## Shared Mechanical Terms

### Wounded

A unit is wounded when its current HP is at or below 30% of max HP.

Use this threshold consistently for wounded-target and wounded-self effects.

### One-Attack Defensive Stacks

Some defensive abilities grant stacks that protect against only the next incoming attack.

Rule:

- Stacks can accumulate up to the ability's maximum.
- All stacks apply to the next incoming attack.
- After that attack resolves, all stacks clear.
- This prevents temporary defense from becoming permanent armor.

### Round-Limited Reactions

Some passives are limited to once per round.

Rule:

- A once-per-round effect can trigger once for each unit during a combat round.
- It resets at the start of the next combat round.
- Reflected effects must not recursively trigger additional reflection.

### Different Debuff Types

Effects that count debuffs should count distinct debuff types, not stacks.

Example:

- Marked = 1 debuff type
- Marked + Poisoned = 2 debuff types
- Marked + Poisoned + Cracked Armor = 3 debuff types

Recommended initial cap: count up to 3 different debuff types for damage-scaling effects.

## Targeting Weight Requirements

Because combat is automated, several abilities require deterministic target weighting. They should not feel random when they imply focus fire or priority targeting.

Targeting weights should support factors such as:

- wounded enemies
- marked enemies
- debuffed enemies
- enemies with the most debuff types
- backline enemies
- lowest HP enemies
- highest threat enemies
- the unit's previously marked or previously attacked target
- enemies affected by class-specific debuffs, such as Cracked Armor or Disarming Shot

Abilities that depend on this include:

- Patient Aim
- Pick Your Mark
- Mark Target
- Kill Lane
- Follow the Weakness, if implemented later
- any future focus-fire support ability

## Unit Tree Overview

| Tier 1 | Tier 2 Option A | Tier 2 Option B |
|---|---|---|
| Bruiser | Enforcer | Pit Fighter |
| Marksman | Deadeye | Trapper |
| Guardian | Bulwark | Shieldbreaker |
| Bannerbearer | Warcaller | Mascot |
| Saboteur | Trickshot | Plaguehand |

Tier 3 classes are not fully named in this document, but Tier 3 should follow the same structure:

- promotion grants one active ability and one passive ability immediately
- level 10 grants a choice between two passive capstones
- Tier 3 should be more specialized than Tier 2
- Tier 3 should represent squad-anchor or build-defining identity

## Bruiser

Tier: 1  
Role: frontline melee bruiser  
Promotion branches: Enforcer or Pit Fighter

### Baseline Abilities

- Heavy Strike: active melee attack. Must consume at least one die and scale damage from that die.
- Thick Hide: passive toughness.

### Level 10 Capstone Choice

#### Brawl Hardened

Type: passive capstone

Effect:

- When this unit is attacked, gain one stack of Brawl Hardened, up to 3.
- The next incoming attack consumes all Brawl Hardened stacks.
- Each consumed stack reduces damage from that attack.
- After that attack resolves, all stacks clear.

Design note: triggering from being attacked reinforces the fantasy of learning to take a beating.

#### Finisher

Type: passive capstone

Effect:

- This unit deals bonus melee damage to wounded enemies.
- Wounded means at or below 30% max HP.

## Enforcer

Tier: 2  
Promotes from: Bruiser  
Role: frontline pressure and execution damage

### Promotion Unlocks

#### Skullcrack

Type: active attack

Die use:

- Consumes at least one die.
- Die value scales the damage and/or the attack reduction strength.

Effect:

- Deals medium melee damage.
- Applies Cracked Skull.
- Cracked Skull reduces the target's attack damage.

#### Menacing Follow-Through

Type: passive

Effect:

- When Enforcer damages an enemy with an active ability, that enemy takes slightly increased melee damage until the end of the round or until hit once by another melee attack.

### Level 10 Capstone Choice

#### No Mercy

Type: passive capstone

Effect:

- Enforcer deals bonus damage to wounded enemies.
- This can stack with Bruiser's Finisher, but tuning should be conservative.

#### Brutal Suppression

Type: passive capstone

Effect:

- Enforcer's active attacks apply a small attack reduction.
- If the active attack already applies attack reduction, increase that reduction or its duration instead.

## Pit Fighter

Tier: 2  
Promotes from: Bruiser  
Role: frontline brawler and comeback fighter

### Promotion Unlocks

#### Desperate Swing

Type: active attack

Die use:

- Consumes at least one die.
- Die value scales damage.

Effect:

- Deals melee damage.
- Deals increased damage while the Pit Fighter is wounded.

#### Counterpunch

Type: passive

Effect:

- Once per round, when hit by a melee attack, retaliate with a light melee counterattack.

### Level 10 Capstone Choice

#### Last Goblin Standing

Type: passive capstone

Effect:

- Once per battle, when this unit would be reduced to 0 HP, it survives at 1 HP instead.

#### Crowd Favorite

Type: passive capstone

Effect:

- When this unit takes damage, gain one stack of Crowd Favorite.
- Max 5 stacks.
- Each stack increases attack damage slightly.
- Stacks last until the end of battle.

## Marksman

Tier: 1  
Role: backline ranged damage  
Promotion branches: Deadeye or Trapper

### Baseline Abilities

- Aimed Shot: active ranged attack. Must consume at least one die and scale damage from that die.
- Sharpshooter: passive ranged improvement.

### Level 10 Capstone Choice

#### Patient Aim

Type: passive capstone

Effect:

- Aimed Shot receives improved targeting priority and bonus damage.
- Target priority should prefer high-value targets such as wounded, marked, debuffed, backline, or low-HP enemies.

#### Pick Your Mark

Type: passive capstone

Effect:

- When this unit damages a target, it marks that target as its preferred target.
- Future single-target ranged attacks prefer that same target while it remains alive and valid.

Implementation warning: do not implement Pick Your Mark without targeting weight support.

## Deadeye

Tier: 2  
Promotes from: Marksman  
Role: backline single-target specialist

### Promotion Unlocks

#### Piercing Shot

Type: active attack

Die use:

- Consumes at least one die.
- Die value scales damage and/or amount of defense ignored.

Effect:

- Deals high ranged damage.
- Ignores part of the target's defense.

#### Vantage Point

Type: passive

Effect:

- Deadeye gains bonus ranged damage for each living allied unit positioned in a row ahead of it.
- Prefer row-based logic over loose board-wide counting so positioning matters.

### Level 10 Capstone Choice

#### Kill Lane

Type: passive capstone

Effect:

- Ranged attacks deal bonus damage to enemies in the enemy back row or enemies protected behind another living enemy.

#### Armor Gap

Type: passive capstone

Effect:

- Deadeye ranged attacks ignore a small amount of defense.

## Trapper

Tier: 2  
Promotes from: Marksman  
Role: backline utility and run-value specialist

### Promotion Unlocks

#### Mark Target

Type: active attack/debuff

Die use:

- Consumes at least one die.
- Die value scales mark strength and/or light damage.

Effect:

- Deals light ranged damage.
- Applies Marked.
- Marked enemies take increased damage from allied attacks.

#### Treasure Sense

Type: passive, out-of-combat

Effect:

- Small percent chance to discover up to one hidden treasure node per run.
- Recommended initial chance: 8-12% per run.
- Only the highest Treasure Sense value in the party applies at first.
- Max one hidden treasure node can be revealed per run.

### Level 10 Capstone Choice

#### Exposed Weaknesses

Type: passive capstone

Effect:

- Enemies take bonus damage for each different debuff type currently applied to them.
- Count different debuff types, not stacks.
- Recommended initial cap: 3 debuff types.

#### Barbed Mark

Type: passive capstone

Effect:

- Mark Target also applies Snared.
- Snared should count as a separate debuff type.
- Snared should avoid timing manipulation unless the combat scheduler supports it cleanly.
- Preferred initial effect: target takes increased ranged damage or deals slightly reduced ranged damage.

## Guardian

Tier: 1  
Role: tank and protector  
Promotion branches: Bulwark or Shieldbreaker

### Level 10 Capstone Choice

#### Bodyguard

Type: passive capstone

Effect:

- While this unit is alive, the lowest-health ally receives minor damage reduction.

#### Hold the Line

Type: passive capstone

Effect:

- Gain defense while positioned in the front row.
- Bonus improves if adjacent to another front-row ally.

## Bulwark

Tier: 2  
Promotes from: Guardian  
Role: dedicated tank

### Promotion Unlocks

#### Taunting Guard

Type: active defense

Die use:

- Consumes at least one die.
- Grants `ceil(dieValue / 2)` Guard stacks, up to the ability's maximum.

Effect:

- Causes the next enemy attack that can legally target this unit to target this unit.
- Grants defense stacks.
- Stacks can build, but the next incoming attack consumes all stacks and then clears them.

#### Shield Set

Type: passive

Effect:

- Each time this unit is attacked during a round, gain one defense stack until the round ends.
- Max 3 stacks.

### Level 10 Capstone Choice

#### Unmoving

Type: passive capstone

Effect:

- When Taunting Guard redirects an attack, reduce that attack's damage further.

#### Wall of Scrap

Type: passive capstone

Effect:

- Shield Set max stacks increase from 3 to 5, or each stack is slightly stronger.

## Shieldbreaker

Tier: 2  
Promotes from: Guardian  
Role: anti-armor frontline

### Promotion Unlocks

#### Crack Armor

Type: active attack/debuff

Die use:

- Consumes at least one die.
- Die value scales damage and/or defense reduction.

Effect:

- Deals melee damage.
- Applies Cracked Armor.
- Cracked Armor reduces target defense.

#### Find the Gap

Type: passive

Effect:

- Shieldbreaker attacks ignore a small amount of enemy defense.

### Level 10 Capstone Choice

#### Shatter Plate

Type: passive capstone

Effect:

- Crack Armor is stronger or lasts longer.

#### Break Open

Type: passive capstone

Effect:

- Allies deal bonus damage to targets affected by Cracked Armor.

## Bannerbearer

Tier: 1  
Role: support  
Promotion branches: Warcaller or Mascot

### Level 10 Capstone Choice

#### Rally Rhythm

Type: passive capstone

Effect:

- Bolster effects also grant a small attack bonus.

#### Patch Job

Type: passive capstone

Effect:

- Bolstering a wounded ally also heals a small amount or grants a small shield.

## Warcaller

Tier: 2  
Promotes from: Bannerbearer  
Role: combat support and offensive buffer

### Promotion Unlocks

#### Warcry

Type: active support

Die use:

- Consumes at least one die.
- Die value scales attack bonus strength and/or number of affected allies.

Effect:

- Grants attack bonus to an ally or row of allies.
- Recommended first implementation: single-target Warcry that targets the highest-attack ally or next-acting ally.

#### Battle Tempo

Type: passive

Effect:

- When an ally defeats an enemy while affected by a Warcaller buff, another random ally gains a small bolster or attack bonus.

### Level 10 Capstone Choice

#### Chant of Violence

Type: passive capstone

Effect:

- Warcry grants a larger attack bonus.

#### Mob Mentality

Type: passive capstone

Effect:

- Allies deal bonus damage to enemies already damaged this round.

## Mascot

Tier: 2  
Promotes from: Bannerbearer  
Role: morale, luck, and opportunistic support

### Design Intent

Mascot should be a goblin-flavored support branch that feels more playful and strange than Quartermaster. It should not become a conventional healer or inventory manager. Mascot should make the squad feel lucky, scrappy, and emotionally volatile.

### Promotion Unlocks

#### Lucky Chant

Type: active support

Die use:

- Consumes at least one die.
- Die value scales the strength of the granted luck buff.

Effect:

- Grants Lucky to one ally.
- Lucky increases the next damage roll, healing/shield roll, or defensive roll made by that ally.
- If possible, Lucky should prefer an ally that is about to act or has a strong active ability available.

Alternative implementation if roll modification is difficult:

- Lucky grants a flat bonus based on die value to the ally's next active ability result.

#### Attention Hog

Type: passive

Effect:

- When Mascot receives a bolster or support effect, one other random ally receives a smaller version of that benefit.
- This should not recursively trigger itself.

### Level 10 Capstone Choice

#### Dumb Luck

Type: passive capstone

Effect:

- Once per battle, when an ally would receive a low active ability result, improve that result by a small amount.
- This should not require player interaction.

#### Morale Goblin

Type: passive capstone

Effect:

- When an enemy is defeated, the lowest-health ally gains a small shield or heal.

Design note: Mascot should support combat momentum and survivability without replacing Warcaller as the offensive support branch.

## Saboteur

Tier: 1  
Role: control and debuff  
Promotion branches: Trickshot or Plaguehand

### Level 10 Capstone Choice

#### Toxic Tools

Type: passive capstone

Effect:

- Debuffs applied by this unit are stronger or last longer.
- Prefer stronger debuffs if debuff duration is not easy for players to read.

#### Spiteful Reflex

Type: passive capstone

Effect:

- Once per round, when a debuff is applied to this unit by an attacker, reflect a copy of that debuff back to the attacker.

Limits:

- Only reflects enemy-applied debuffs.
- Does not reflect reflected debuffs.
- Does not reflect boss-only special conditions unless explicitly allowed.

## Trickshot

Tier: 2  
Promotes from: Saboteur  
Role: precision debuffer

### Promotion Unlocks

#### Disarming Shot

Type: active attack/debuff

Die use:

- Consumes at least one die.
- Die value scales damage and/or attack reduction.

Effect:

- Deals ranged damage.
- Reduces target attack damage.

#### Opportunist

Type: passive

Effect:

- Trickshot deals bonus damage to enemies with any debuff.

### Level 10 Capstone Choice

#### Disabling Hit

Type: passive capstone

Effect:

- Disarming Shot's attack reduction is stronger.

#### Clean Shot

Type: passive capstone

Effect:

- Trickshot deals increased damage to enemies affected by Disarming Shot.

Potential later option:

- Follow the Weakness: single-target attacks prefer enemies with the most debuffs. This requires targeting weight support.

## Plaguehand

Tier: 2  
Promotes from: Saboteur  
Role: poison/control hybrid

### Promotion Unlocks

#### Poison Cloud

Type: active debuff

Die use:

- Consumes at least one die.
- Die value scales poison strength and/or number of affected enemies.

Effect:

- Applies poison to multiple enemies.
- Preferred targeting if adjacency exists: target plus adjacent enemies.
- If adjacency is not available, affect two random enemies.

#### Nerve Toxin

Type: passive

Effect:

- Poisoned enemies deal reduced damage.

### Level 10 Capstone Choice

#### Lingering Cloud

Type: passive capstone

Effect:

- Poison Cloud applies stronger or longer-lasting poison.

#### Sickly Weakness

Type: passive capstone

Effect:

- Poisoned enemies count as having one additional debuff type for effects that care about debuff count, such as Exposed Weaknesses.

Alternative if this is too abstract:

- Fever Break: enemies suffering poison take bonus damage from active abilities.

## Implementation Notes

### Data Model

The implementation likely needs to distinguish:

- unit type max level
- promotion eligibility level
- ability unlocks granted at class entry
- capstone unlocks available at level 10
- selected capstone per unit type history
- inherited passive abilities
- inherited active abilities, if current inheritance allows them

### Ability Registry

All new abilities should be registered in the backend ability registry and have handler coverage. Existing tests that verify registry coverage should be extended for the new abilities.

### Combat Logs

Combat events should be readable enough for playtesting:

- show when a passive triggers
- show when stacks are gained
- show when stacks are consumed
- show when a capstone modifies an action
- show when targeting preference selects a marked, wounded, or debuffed target

### Run Map Effects

Treasure Sense introduces an out-of-combat passive. It should be implemented carefully so combat-only assumptions do not break.

Recommended first behavior:

- At run generation or node reveal time, check whether the squad has Treasure Sense.
- Use only the highest Treasure Sense value among units.
- Reveal at most one hidden treasure node per run.

### Open Tuning Values

These values can start as conservative defaults and be tuned after playtesting:

- Brawl Hardened damage reduction per stack
- Skullcrack damage and attack reduction
- Menacing Follow-Through bonus
- Counterpunch damage
- Crowd Favorite bonus per stack
- Patient Aim target weights
- Vantage Point bonus per ally in front
- Mark Target damage bonus
- Treasure Sense reveal chance
- Exposed Weaknesses bonus per debuff type
- Taunting Guard stack cap and reduction per stack
- Shield Set stack value
- Warcry buff amount
- Mascot Lucky modifier
- poison damage and Nerve Toxin reduction

## Implementation Order

1. Add progression data support for max level 10, level 6 promotion eligibility, capstone options, and inherited passive capstones.
2. Add targeting weight support for wounded, marked, debuffed, backline, and preferred-target logic.
3. Add reusable combat effect primitives for temporary stacks, one-attack stack consumption, once-per-round reactions, debuff counting, and defense ignore/reduction.
4. Implement Tier 1 capstone choices.
5. Implement Tier 2 promotion unlock packages and capstone choices.
6. Add promotion and unit detail UX for capstone selection and promotion preview warnings.
7. Add tests for progression, inheritance, targeting, combat effects, and run-map passive behavior.
