---
Title: "Dice Goblins vNext Game Overhaul"
Status: Active Implementation Plan
Last Updated: 2026-09-12
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/README.md
Category: 07-development-path
Tags: [vnext, implementation-plan, overhaul]
---

# Dice Goblins vNext Game Overhaul

## Purpose
vNext is a deliberate implementation reset around the game Dice Goblins has become. This document owns implementation order/status. Dedicated accepted `vnext-*.md` documents own the detailed decisions behind it.

Working branch: `vnext-game-overhaul`.

## Strategy
Use walking slices rather than completing frontend/backend/database layers in isolation.

> Every milestone should leave a working vertical capability crossing Phaser -> API -> domain logic -> MySQL where applicable.

Prototype source may be mined for useful algorithms and game behavior, but compatibility with prototype APIs, schema, Angular gameplay pages, migration history, or outdated docs is not a requirement.

Build gameplay functionality and interaction structure before spending heavily on final visual fidelity. New Phaser surfaces should remain clear, usable, and reasonably consistent with the visual guide, but the current UI is not the final presentation target. A larger cross-cutting visual/UI overhaul is intentionally deferred until enough gameplay surfaces exist to establish and apply the final shared visual language coherently.

## Status
**Milestone 0 - Reconcile and Clean vNext Context: Complete.**

Milestone 0 reconciled the architecture decisions, replaced the horizontal rewrite roadmap, removed conflicting/historical documentation from the active branch, and redirected coding-agent context to current vNext sources. Git history is the archive for removed material.

**Milestone 1 - Walking Skeleton: Complete; manual UAT passed.**

Milestone 1 proved the real authenticated Angular -> persistent Phaser -> authored content -> PHP bootstrap -> MySQL player state -> responsive Camp path. Closure verification passed through the supported Docker/frontend environment, including a real browser/PHP/MySQL registration-to-Camp check. Manual UAT identified no functional defects; final visual quality remains intentionally deferred to the later game-wide visual/UI overhaul.

**Next: Milestone 2 - Warband.** Decompose its first concrete execution package before implementation begins.

## Milestones
| # | Milestone | Exit criterion |
| ---: | --- | --- |
| 0 | Reconcile and clean vNext context | One coherent implementation plan and current-only documentation/agent context. **Complete.** |
| 1 | Walking skeleton | Authenticated Angular `/game` -> Phaser boot -> safe content projection -> real PHP bootstrap/MySQL state -> minimal responsive Camp. **Complete; UAT passed.** |
| 2 | Warband | Real units/dice/squads, lazy cache/detail queries, persistent squad and unit-loadout configuration. |
| 3 | Enter Farm | Energy + region/run creation, persistent generated Farm run, `RunScene`, resume/abandon/map. |
| 4 | Combat | Authoritative combat adaptation, run HP/state, persisted playback, `BattleScene`, reconnect-safe result. |
| 5 | Complete Farm | Event/reward pipeline, XP/progression, Mudking, terminal run flow, Mountains unlock; no claim/reroll/double-grant path. |
| 6 | Prove region generalization | Mountains/kobolds operate through the same region/run architecture without Farm-specific duplication. |
| 7 | Economy and inventory | Shop, Teeth, inventory/consumables, dice sale/salvage, recharge items and repeatable economy. |
| 8 | Permanent progression | Academy, Raw Chaos capability upgrades, promotion/ability progression, derived upgrades such as Energy max. |
| 9 | Kin and Wrong Machine | Kin unlock/restoration, Pig/Lizard reconstruction, first-unlock vs deterministic repeat behavior. |
| 10 | Run encounter depth | Rest, hazards, shrines, Chaos, run modifiers, contextual consumables, multi-step node interactions where needed. |
| 11 | Knowledge and objectives | Codex/knowledge dialogue, objectives/bounties, gameplay-fact progress and automatic completion rewards. |
| 12 | Mystic Cave and onboarding | Fresh-player Whim/tutorial/provisioning flow and Wrong Machine introduction. |
| 13 | Swamp and parity audit | Swamp/frogmen/Frog Kin plus audit of intentionally preserved prototype gameplay; third-region repeatability proof. |
| 14 | Hardening and cutover | Remove obsolete implementation paths, cleanup/retention, security/content-exposure audit, device/performance/game-feel pass, canonical-doc migration. |

Completing the overhaul does not require building Island through Savanna or The Library.

## Persistent Quality Gates
Every applicable milestone maintains:
- clean database bootstrap/reset;
- authoritative backend tests and negative paths;
- idempotency/retry coverage for spend/random/durable/gameplay commands;
- authored JSON structural/semantic validation;
- client-projection secrecy/allowlist validation;
- deterministic combat/run-generation regression where relevant;
- Phaser visual checks at Compact landscape, 1600x900 reference, and Wide landscape;
- portrait mobile rotate-device behavior;
- documentation/reference consistency.

## Deferred Until Needed
Do not block the next gameplay milestone on final visual polish or on exact later-system details such as Rest/Chaos sub-route payloads, all battle-playback fields, later economy tuning, later kin recipes, or onboarding dialogue. Resolve them in the milestone that needs them and update the relevant accepted decision if the architecture changes.

The cross-cutting visual/UI overhaul is intentionally deferred until core gameplay surfaces and interaction patterns are established. Until then, visual work should support clarity, usability, responsive correctness, and basic cohesion rather than attempt final production fidelity screen by screen.

## Scope Boundaries
- No current player/runtime-data migration.
- No prototype API/schema backward-compatibility requirement.
- No Godot/native rewrite during this initiative.
- No microservices/event bus/full event sourcing introduced without a new decision.
- No one-for-one port of Angular gameplay pages.
- No whole-catalog exposure to the browser.
- No future base-game biome production required merely to declare the vNext technical overhaul complete.

## Accepted Decision Set
See `documentation/07-development-path/README.md`. Those documents are authoritative for reward/unlock, economy, Energy, progression, authored content, storage, API/endpoints, backend internals, and Phaser client architecture.
