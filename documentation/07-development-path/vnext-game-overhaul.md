---
Title: "Dice Goblins vNext Game Overhaul"
Status: Active Implementation Plan
Last Updated: 2026-09-16
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

**Milestone 2 - Warband: Complete; manual UAT passed.**

All nine implementation/closure packages passed architectural review. Integrated closure at `c72c2611d7e14d1d3151f42c05f791e4f1405c2d` proved the fresh-database/authored-content/PHP/MySQL/Phaser Warband slice end to end, including lazy collections/detail, squad lifecycle, unit rename/loadout/exact-die configuration, authoritative client reconciliation, persistence after reload, cross-player security, responsive presentation, and narrow retirement of superseded Angular Warband surfaces. Manual user UAT passed on 2026-09-13 with no blocking findings.

**Milestone 3 - Enter Farm: Complete; manual UAT passed.**

The seven implementation/closure packages passed architectural review, with integrated closure at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f`. Manual UAT identified inconsistent clickable cursor affordances and reactive-only presentation of active-run Warband locks. Package 8 at `038a6ce081fa3b735f627095db372db24b39f79e` corrected both while retaining backend authority and legal squad/unit naming. The focused manual recheck passed and Milestone 3 was closed on 2026-09-15.

**Milestone 4 - Combat: Technical closure complete; manual UAT pending.**

All eight implementation/closure packages have passed architectural review. Integrated technical closure at `16288bff6223cdddee56b5cbf359e607c07dc81e` proved the accepted combat slice through fresh MySQL state, deterministic server combat, immutable battle history, atomic/idempotent combat resolution, retained Phaser playback/reload, explicit results, and authoritative Continue reconciliation. Manual user UAT is the remaining gate before Milestone 4 can close and Milestone 5 may be promoted.

## Milestones
| # | Milestone | Exit criterion |
| ---: | --- | --- |
| 0 | Reconcile and clean vNext context | One coherent implementation plan and current-only documentation/agent context. **Complete.** |
| 1 | Walking skeleton | Authenticated Angular `/game` -> Phaser boot -> safe content projection -> real PHP bootstrap/MySQL state -> minimal responsive Camp. **Complete; UAT passed.** |
| 2 | Warband | Real units/dice/squads, lazy cache/detail queries, persistent squad and unit-loadout configuration. **Complete; UAT passed.** |
| 3 | Enter Farm | Energy + region/run creation, persistent generated Farm run, `RunScene`, resume/abandon/map. **Complete; UAT passed.** |
| 4 | Combat | Authoritative combat adaptation, run HP/state, persisted playback, `BattleScene`, reconnect-safe result. **Technical closure complete; UAT pending.** |
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

## Milestone 4 Package Sequence
1. Canonical combatant stats + authoritative run HP initialization. **Approved.**
2. Farm combat authored content + deterministic engine adaptation. **Approved.**
3. Battle persistence + playback boundary. **Approved.**
4. Authoritative combat-node resolution + persisted run/battle state. **Approved.**
5. Battle/result/playback query and reconnect contracts. **Approved.**
6. Phaser `BattleScene` playback lifecycle. **Approved after focused correction.**
7. Battle result + authoritative return-to-run reconciliation. **Approved.**
8. Combat integrated verification/closure. **Approved at `16288bff6223cdddee56b5cbf359e607c07dc81e`.**

Manual Milestone 4 combat UAT is now the only remaining Milestone 4 gate.

The retained prototype combat implementation is behavioral evidence only. It currently couples PDO/catalog access, node effects, rewards/progression, combat simulation, and playback construction; vNext preserves useful deterministic mechanics behind the accepted application/domain/repository/content boundaries rather than wrapping that object as the new architecture.

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
Do not block the next gameplay milestone on final visual polish or on exact later-system details such as Rest/Chaos sub-route payloads, later economy tuning, later kin recipes, or onboarding dialogue. Resolve them in the milestone that needs them and update the relevant accepted decision if the architecture changes.

For Milestone 4, Packages 1-8 have established and technically closed canonical stats/run HP, deterministic combat rules/content, immutable battle persistence, atomic resolution, ownership-safe playback, reload-safe Phaser presentation, authoritative post-battle reconciliation, and integrated real-stack verification. Manual UAT now validates the player-visible slice. No rewards/progression/claim semantics are added merely to close Combat.

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
