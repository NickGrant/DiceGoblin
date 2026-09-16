---
Title: "Combat Resolution"
Status: Canonical
Last Updated: 2026-09-15
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/target-resolution.md
  - documentation/02-systems/ability-loadouts-and-dice-binding.md
  - documentation/02-systems/dice-profiles-and-aspects.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
Category: 02-systems
Tags: [systems, combat, vnext]
---

# Combat Resolution

## Authority
Combat is resolved by PHP. Phaser never independently determines authoritative rolls, targets, damage, statuses, XP, rewards, or outcomes.

The vNext kernel receives a complete normalized snapshot and produces a deterministic result/playback model. Content lookup and player-state assembly occur outside the kernel. The kernel has no PDO, repository, HTTP, clock, run mutation, or reward/progression responsibility.

## Scheduling
Combat uses 20 ticks per round and no Speed stat. Each living combatant first schedules its first ordered active ability at that ability's `action_delay`. After execution or a sleep-skipped action, the loadout advances cyclically and the next action is scheduled by adding the **next** ability's delay to the current tick. Dead combatants do not act. Same-tick actions resolve by ascending `resolution_priority`, then stable combatant key. Combat ends at the first terminal action; if both sides are still alive at tick 4000 (round 200), it ends in `stalemate`.

## Inputs
The normalized snapshot includes seed; stable combatant key, side, and side-relative 3x3 position; max/current HP and the five resolved stats; ordered active abilities with delay, priority, target rule, authored handler config, and exact slot dice; applicable passive configs and statuses. Every die includes sides, profile identity, and normalized aspect effects. Enemy content and player ownership/dice facts are resolved before creating the snapshot. Identical snapshot plus seed yields deep-equal result and events.

## Resolution
For a damaging hit, effective Attack and Defense apply equipped/passive flat modifiers first, then summed same-stat percentages and floor once. Active status modifiers apply afterward using the same flat-then-percentage convention. `attack_component = floor(effective Attack × authored power_ratio)`. After Precision, roll each bound die uniformly from `1..sides`, summing all slot values; an Explosive maximum gets exactly one extra roll. Compute `max(0, attack_component + roll_total - effective target Defense)` after explicit defense-ignore, then add each participating Striking die's flat damage. Conditional Executioner and ranged Sharpshooter factors multiply one another; apply them before the combined position multiplier and position floor. Apply a critical ×1.5 and floor. A successful damaging action deals at least 1; a miss deals 0 and no harmful on-hit status. There is no unrelated ±2 damage variance.

Position uses side-relative x: x=2 is front, x=0 is back for both sides. Front melee attackers deal ×1.10; any front target takes ×1.10; a back target of a melee attack takes ×0.90. Multipliers compose before the position floor.

Precision 5 is neutral and consumes no hit/critical RNG. Below 5, miss chance is `min(40, (5 - Precision) × 8)%`, with no crit chance. Above 5, crit chance is `min(30, (Precision - 5) × 5)%`, with no Precision miss chance. A harmful status compares target Resolve with source Precision. When Resolve is no greater, no resistance RNG is consumed. Otherwise resistance chance is `min(45, (Resolve - Precision) × 8)%`; buffs are never resisted.

One deterministic RNG stream is consumed in this order per eligible action: target tie selection if needed; Precision miss/critical check for damaging hits when non-neutral (harmful status-only actions use the below-5 miss check but never an unusable critical roll); each die slot's initial roll and immediate one-level Explosive extra roll if triggered, in slot order; harmful Resolve check only when target Resolve exceeds source Precision. Support buffs consume no Precision or Resolve RNG. Material currently has no combat effect beyond profile eligibility/identity. Guarding gives +1 flat Defense, Bulwark +10% Defense, Precise +10% Attack across all unique equipped/bound dice. Striking gives +1 damage when its die participates; Executioner gives +15% damage when target HP is strictly below half max HP. Thick Hide gives +2 flat Defense; Sharpshooter gives +15% ranged damage.

The first Farm slice supports `bolstered` (authored percentage Defense buff), `cracked_armor` (authored flat Defense reduction), `sleep` (action prevention until authored expiration or damage), and `wrestled` (next eligible damaging enemy-targeted action is forced toward the valid wrestler and consumes the status). A status applied in round R for D rounds expires at the start of round R+D. If sleep ends on tick T, the unit cannot act on T and may act from T+1. Application, resistance, replacement, expiration, damage removal, and consumption are playback facts. No healing formula is established here.

Target semantics are defined in `target-resolution.md`.

## Output
Engine and playback schema versions are both 1. The result contains `victory`, `defeat`, or `stalemate`; ending round/tick; terminal HP, defeat state, and statuses for every combatant; and ordered semantic events with sequence, type, round, tick, and exact facts. Events cover battle/round boundaries, actor/ability/target reason, dice and Explosive rolls, hit/miss/critical, damage/HP, status transitions, and death. It contains no timestamps, prose authority, or reward/XP grants.

`BattleScene` consumes playback; it does not simulate authority.

## Rewards and XP
Reward, XP, objective, and progression resolution remain later parent-command work. This kernel does not grant or calculate them. Finalized grants will belong to the authoritative command transaction; no separate battle-claim mutation is intended.

Retries using the same idempotency boundary return the original finalized result rather than rerolling combat or rewards.
