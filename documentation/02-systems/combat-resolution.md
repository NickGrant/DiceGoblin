---
Title: "Combat Resolution"
Status: Canonical
Last Updated: 2026-09-10
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

The combat engine should remain a computational subsystem: it receives an authoritative combat input snapshot and produces a deterministic result/playback model without writing HTTP responses or directly orchestrating database transactions.

## Scheduling
Combat retains the tick-based ordered-ability model. Ability order and authored timing determine when combatants act. Speed is not introduced as a general persistent unit stat merely to drive scheduling.

## Inputs
Combat may consume:
- participating units and their resolved stats;
- ordered ability loadouts;
- exact dice bindings and authored dice-profile definitions;
- formation/position information;
- enemies and authored abilities;
- current run HP and applicable run modifiers;
- deterministic random/seed context.

## Resolution
The engine resolves scheduled actions, automatic targets, dice/profile effects, damage/healing/statuses, deaths, and terminal outcome. Random-looking decisions must be reproducible from authoritative deterministic state so retries/reloads cannot create alternate results.

Target semantics are defined in `target-resolution.md`.

## Output
A combat result contains enough information to persist authoritative post-combat state and present the fight, including outcome, resulting HP/state, gameplay facts/events, and ordered playback events.

`BattleScene` consumes playback; it does not simulate authority.

## Rewards and XP
Combat/node resolution may emit semantic events that feed XP, objectives, rewards, and progression. Finalized grants are applied within the authoritative parent command transaction. There is no separate battle-claim mutation required to obtain already-finalized rewards.

Retries using the same idempotency boundary return the original finalized result rather than rerolling combat or rewards.
