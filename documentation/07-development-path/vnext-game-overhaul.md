---
Title: "Dice Goblins vNext Game Overhaul"
Status: Active Implementation Plan
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On:
  - documentation/00-overview/00-project-overview.md
  - documentation/00-overview/01-core-gameplay-loop.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-currency-economy-model.md
  - documentation/07-development-path/vnext-energy-model.md
  - documentation/07-development-path/vnext-progression-state-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-api-contract-model.md
  - documentation/07-development-path/vnext-endpoint-inventory.md
  - documentation/07-development-path/vnext-backend-internal-architecture.md
  - documentation/07-development-path/vnext-phaser-client-architecture.md
Category: 07-development-path
Tags:
  - vnext
  - architecture
  - implementation-plan
  - phaser
  - php
  - database
  - overhaul
---

# Dice Goblins vNext Game Overhaul

## Purpose

Dice Goblins vNext is a deliberate implementation reset built around the game Dice Goblins has become rather than the assumptions of the original browser prototype.

This document is the implementation roadmap and status tracker for the overhaul. Dedicated accepted vNext decision documents are authoritative for their individual domains and explain the detailed contracts behind this plan. This file should summarize those decisions rather than redefine competing versions of them.

The working branch is `vnext-game-overhaul`.

## Planning Status

Milestone 0 reconciliation completed on 2026-09-10. The earlier horizontal rewrite plan has been replaced by walking implementation slices that exercise Phaser, PHP, authored content, and MySQL together as early as possible.

The next implementation target is **Milestone 1 - Walking Skeleton**.

## Confirmed Product and Technical Boundaries

- Existing production/runtime player data does not need to migrate into vNext.
- Backward compatibility with the prototype database schema or API is not required.
- Existing migration history is design evidence, not an installation contract.
- Dice Goblins remains web-delivered for the vNext overhaul.
- PHP remains the authoritative gameplay backend.
- MySQL stores mutable player/runtime state.
- Authored gameplay definitions live in Git-tracked JSON and are referenced by stable IDs; MySQL does not maintain a required duplicate authored catalog.
- Angular owns the public website, authentication/account shell, and the `/game` hosting boundary.
- Phaser is the game client and directly consumes the authenticated PHP gameplay API.
- Mobile native packaging is a future concern, but responsive landscape/mobile readiness is part of the vNext client architecture now.
- Existing gameplay algorithms and content should be preserved when they still serve the current design; prototype architecture and compatibility surfaces are not preserved merely because they exist.

## Accepted Architecture Summary

### Player state, progression, and rewards

- Successful gameplay/transaction resolutions may emit semantic events which resolve authored reward definitions.
- Reward randomness is finalized exactly once and grants are applied transactionally/idempotently.
- Ordinary rewards are applied immediately during authoritative resolution; reward/claim UI is presentation and acknowledgment, not delayed ownership.
- Duplicate unique rewards resolve to nothing unless a future mechanic explicitly changes that rule.
- Unlocks represent permanent capabilities/access and Codex entries represent unique knowledge.
- Objectives, runs, units, dice, inventory, and other mutable concepts keep state in the domain that owns the behavior rather than a generic progression/history layer.

### Economy and Energy

- Teeth and Raw Chaos share currency/wallet semantics but serve different economic roles.
- Teeth buy ordinary repeatable goods and services.
- Raw Chaos primarily changes player capability, with the Wrong Machine as the intentional repeatable exception.
- Energy is a regenerating pacing resource rather than currency.
- Energy is spent exactly once when run creation successfully commits; active runs have no ongoing Energy cost.

### Storage and authored content

- vNext starts from a clean MySQL baseline optimized for the approved model.
- Git-tracked JSON is authoritative for static gameplay definitions and tuning.
- Runtime MySQL records reference authored content by durable stable string IDs.
- Content changes are ordinary reviewed JSON changes, not SQL migrations.
- The server consumes the complete authored registry.
- Phaser receives allowlisted client-safe projections plus player-conditioned/revealed information from PHP; server-only probabilities, hidden rules, and unrevealed information are not intentionally shipped to the browser.

### API and backend

- The gameplay API remains under `/api/v1` unless an explicit versioning decision later changes it.
- Reads are domain-oriented queries; mutations are explicit player-intent commands.
- Collection endpoints return compact summaries and detail endpoints return the mutable state required by individual-object screens.
- Commands involving spending, durable creation, randomness, or gameplay resolution have an idempotency boundary.
- Mutation responses return affected authoritative state plus `player_revision`, not a catch-all profile reload.
- Backend internals follow HTTP/controller -> application command/query -> domain rules/engines -> repositories/content registry.
- Application operations own transaction boundaries. Controllers remain thin. Repositories remain persistence-focused.
- Combat and run generation remain computational engines where practical and should not own HTTP/persistence orchestration.
- vNext remains one PHP application and one MySQL database; microservices, asynchronous messaging, and full event sourcing are not part of this overhaul.

### Phaser client

- Angular mounts/destroys the game but does not orchestrate normal gameplay once Phaser is running.
- A persistent Phaser runtime owns API access, cache/store, navigation, content registry, assets, audio, input, and responsive/orientation infrastructure.
- The three major gameplay scenes are `GameScene`, `RunScene`, and `BattleScene`; Boot/Loading are setup lifecycle scenes.
- Ordinary destinations such as Camp, Warband, Unit Detail, Academy, Shop, and Wrong Machine are screens/views rather than one scene per former Angular page.
- Phaser state is explicitly a cache of authoritative server state and lazy-loads large domains when needed.
- The reference design space is 1600x900. Rendering preserves proportions, effective logical width adapts to landscape aspect ratio, and screens use anchors/calculated layout regions rather than raw physical-pixel placement.
- Compact, Standard, and Wide landscape layout modes provide limited responsive composition changes.
- Mobile gameplay is landscape-only. Portrait blocks/obscures gameplay with a rotate-device presentation while preserving the active game state.

## Implementation Strategy

The overhaul uses walking slices rather than completing every backend or frontend layer in isolation.

> Every milestone should leave a working vertical capability that crosses Phaser -> API -> domain logic -> MySQL where applicable.

Shared infrastructure should be implemented only as far as the next vertical capability requires, then exercised immediately through the real client/server path. This reduces the risk of building large abstractions before the game demonstrates that they fit.

## Milestone Plan

### Milestone 0 - Reconcile the vNext Plan

**Status: Complete (2026-09-10).**

Scope:

- make this document the primary overhaul roadmap/status tracker;
- make dedicated accepted vNext decision documents normative for their scopes;
- remove stale assumptions about database-owned authored catalogs, Angular-owned gameplay, claim-based ordinary rewards, and horizontal layer-by-layer rewriting;
- index vNext documents so the architecture is discoverable;
- identify implementation details that may remain deferred until the milestone that needs them.

Exit criterion: planning describes one coherent target without requiring prototype archaeology or contradicting accepted vNext decisions.

### Milestone 1 - Walking Skeleton

**Status: Next.**

Scope:

- establish the working clean-schema baseline foundation for account/user state required by bootstrap;
- establish server authored-content registry and client-safe content projection pipeline;
- establish transaction, idempotency, and `player_revision` infrastructure to the extent required by the first slice;
- create the Angular `/game` host boundary;
- create persistent Phaser runtime plus Boot/Loading/GameScene foundation;
- implement the 1600x900 responsive layout model and landscape-only mobile orientation gate;
- implement the game bootstrap API and GameStore bootstrap state;
- render a minimal Phaser-owned Camp from real authoritative server state;
- enforce client/server content compatibility/version checking.

Exit criterion: an authenticated player enters `/game`, Phaser starts, verifies compatible content, retrieves real server state, and renders a minimal Camp. Angular does not own gameplay state or gameplay API orchestration.

### Milestone 2 - Warband

Scope:

- units, dice, and squads persistence/query contracts needed by the player-facing collection;
- lazy GameStore domain loading and invalidation;
- Camp navigation into Warband and Unit Detail;
- nine-position squad editing;
- unit ability/loadout ordering and exact dice bindings;
- persistence/reload behavior for configuration changes;
- establish responsive behavior using Camp, Warband, and Unit Detail as representative Compact/Standard/Wide screens.

Exit criterion: a player can inspect real owned units/dice, configure units and a squad through Phaser, reload/reconnect, and observe the same authoritative configuration.

### Milestone 3 - Enter the Farm

Scope:

- Energy run-start semantics;
- region/unlock availability required for Farm;
- Farm authored content required to generate the first run;
- run persistence and topology generation;
- `RunScene` and run-map presentation;
- start, resume, and abandon flows;
- active-run configuration locking needed by the slice.

Exit criterion: starting Farm consumes Energy exactly once, creates and renders a persisted run, and reconnecting resumes the same authoritative run.

### Milestone 4 - Combat

Scope:

- adapt the existing combat/targeting algorithms to vNext inputs without reintroducing persistence concerns into the engine;
- authoritative combat-node resolution;
- run-unit HP/state persistence;
- battle result/playback representation and persistence;
- `BattleScene` playback;
- `RunScene -> BattleScene -> RunScene` lifecycle;
- reconnect/recovery behavior for already-finalized combat.

Exit criterion: a Farm combat node resolves exactly once on PHP, Phaser plays that authoritative result, and post-battle run state survives reload/reconnect.

### Milestone 5 - Complete the Farm Slice

Scope:

- semantic events and authored reward resolution;
- transactional/idempotent grant application;
- XP and unit progression needed by the slice;
- Teeth/Raw Chaos grants where applicable;
- reward presentation without claim-based ownership;
- Farm boss/Mudking resolution;
- success/failure/abandon terminal run behavior;
- Mudking completion event granting the Mountains unlock.

Exit criterion: a player can complete the Camp -> Farm -> combat -> Mudking -> automatic rewards -> Mountains unlock -> Camp loop without double grants, rerolls, delayed claim ownership, or client-side gameplay authority.

**Architecture checkpoint:** pause broad migration here and evaluate whether the vNext architecture is working cleanly before moving the remaining systems. Fix structural problems here rather than carrying them through later milestones.

### Milestone 6 - Prove Region Generalization

Scope:

- Mountains authored region content required for play;
- kobold enemies and Mountain presentation/assets;
- Mountain run configuration through the same run-generation/runtime architecture;
- region progression into/out of Mountains as currently designed;
- remove Farm-specific assumptions discovered while introducing the second standard region.

Exit criterion: Mountains operates through the same architecture as Farm without duplicating region-specific controllers, persistence models, scenes, or orchestration. Adding a second region is primarily a content/configuration exercise rather than a new architecture project.

### Milestone 7 - Economy and Inventory

Scope:

- Shop query/purchase flow;
- Teeth earn/spend loop;
- stackable inventory and consumables;
- Energy recharge consumables;
- ordinary dice/unit purchases as applicable;
- die sale/salvage and relevant valuation rules;
- idempotent spending/asset-creation behavior.

Exit criterion: earnings from play feed a functional repeatable economy and inventory loop whose durable transactions obey the vNext command/reward boundaries.

### Milestone 8 - Permanent Progression

Scope:

- Academy query/action flow;
- Raw Chaos permanent-upgrade economy;
- unit-type and feature/capability unlocks;
- unit promotion and durable promotion history;
- ability acquisition/capstone-as-ability behavior;
- derived upgrades such as Energy-capacity increases.

Exit criterion: players can make meaningful persistent account and unit progression choices between runs without generic story/progression flags or prototype capstone-specific persistence.

### Milestone 9 - Kin and Wrong Machine

Scope:

- kin-restoration unlock state;
- Wrong Machine state/presentation;
- first-time reconstruction behavior;
- repeat deterministic reconstruction behavior;
- Pig Kin and Lizard Kin reconstruction integration;
- Raw Chaos costs and exactly-once unit creation;
- kin-specific authored presentation/gameplay integration required by the current design.

Exit criterion: the core kin restoration/reconstruction loop works end-to-end, including the distinction between first restoration and later exact reconstruction.

### Milestone 10 - Run Encounter Depth

Scope:

- Rest encounters;
- hazards and shrines retained by the current design;
- Chaos encounters;
- run modifiers and scalar runtime state;
- contextual in-run consumable use such as healing;
- specialized interactive node commands only when a mechanic requires persistent intermediate authoritative state;
- reconnect/recovery for multi-step node interactions.

Exit criterion: runs support reusable non-combat encounter patterns without pushing encounter-specific state into generic catch-all storage or creating unnecessary specialized APIs.

### Milestone 11 - Knowledge and Objectives

Scope:

- Codex ownership/presentation;
- important dialogue knowledge through unique Codex rewards;
- server-authorized dialogue choices where hidden conditions matter;
- replay behavior without generic dialogue-seen state;
- objective progress/lifecycle;
- bounties as an authored objective type;
- gameplay facts advancing objectives;
- automatic objective-completion reward application rather than sync/claim flows.

Exit criterion: knowledge, dialogue gating, objectives, and bounties operate through their approved domains without generic story flags, bounty synchronization, or ordinary reward-claim endpoints.

### Milestone 12 - Mystic Cave and Onboarding

Scope:

- new-player provisioning required by the current onboarding design;
- Mystic Cave special-biome presentation;
- The Whim introduction and dialogue/tutorial staging;
- tutorialization of the actual vNext game loop;
- Wrong Machine introduction and special-biome interactions;
- fresh-account path through the systems now implemented.

Exit criterion: a genuinely fresh account can enter the game and learn the intended vNext systems through the actual player experience rather than debug provisioning or developer knowledge.

### Milestone 13 - Swamp and Parity Audit

Scope:

- Swamp authored region content required for play;
- frogmen enemies and Frog Kin integration;
- third-region repeatability check after economy, progression, kin, encounter, knowledge, and objective systems exist;
- audit prototype features against the intentional vNext preserve/simplify/remove/defer decisions;
- implement any intentionally preserved functionality not already covered by prior milestones;
- explicitly retire features that are no longer part of the game rather than carrying accidental compatibility.

Exit criterion: vNext has intentional feature parity with the game being replaced and a third standard region demonstrates that the complete architecture remains reusable.

### Milestone 14 - Hardening and Cutover

Scope:

- remove obsolete Angular gameplay pages/services and compatibility routing;
- remove superseded API endpoints, old `team` terminology, claim/sync compatibility paths, and obsolete schema support;
- finalize the clean vNext baseline schema and future migration convention;
- implement scheduled retention/cleanup behavior for eligible battle playback, idempotency/event records, and retired assets;
- security and client-content-exposure review;
- responsive/device/landscape coverage;
- performance and asset-memory budgets;
- visual/game-feel polish and battle playback quality;
- reconcile transitional vNext decision documents into canonical `05-technical` and relevant `02-systems` documentation.

Exit criterion: a clean installation represents the production architecture, the prototype implementation is no longer needed to operate or explain the game, and future feature work can follow canonical documentation rather than overhaul notes.

## Overhaul Scope Boundary

The vNext overhaul does **not** require completion of the entire approved future base-game content roster.

Farm, Mountains, and Swamp are used during the overhaul because they progressively prove the architecture. Later standard biomes, The Library finale, and expansion content remain product/content milestones after the technical overhaul unless an explicit planning decision pulls them into scope.

The overhaul is therefore complete when the intentionally preserved current game is implemented cleanly on the vNext architecture—not when every approved future biome has shipped.

## Persistent Quality Gates

Quality gates should grow with the milestones rather than waiting for final hardening.

At minimum:

- a fresh local/test database must remain capable of booting the current vNext application;
- authored-content validation must reject invalid IDs/references and invalid client projections;
- client artifacts must not intentionally contain server-only authored fields;
- gameplay mutations that spend resources, create durable assets, invoke randomness, or resolve gameplay require retry/idempotency coverage;
- finalized rewards must not reroll on retry/reconnect/re-presentation;
- important Phaser screens should gain deterministic capture fixtures and Compact/Standard/Wide visual coverage as they are implemented;
- backend computational engines should remain testable without HTTP presentation or direct persistence orchestration where practical;
- milestone exit criteria should be exercised through the actual Phaser/API path rather than only through isolated unit tests.

## Deferred Decisions

Implementation details that do not block the current milestone should remain deferred until real behavior provides enough evidence to decide them. Current examples include:

- exact Compact/Standard/Wide breakpoint thresholds;
- exact response/error envelope details and error-code vocabulary;
- exact specialized Rest/Chaos interactive node routes and payloads;
- exact retention durations;
- optional ETag/cache refinements;
- richer future reward-choice mechanics;
- packaging strategy for native mobile/desktop distribution.

Deferral is intentional when the underlying architecture already provides a place for the future decision.

## Documentation Lifecycle

The vNext files under `07-development-path` are authoritative implementation decisions during the overhaul. They are not intended to become permanent parallel architecture documentation after cutover.

As systems stabilize:

- implementation details should be checked against the accepted decision documents;
- material architectural deviations should update the relevant decision document rather than silently drift;
- once vNext is stable, durable technical choices move into canonical `05-technical` documentation;
- durable gameplay/system contracts move into the relevant `02-systems` documentation;
- obsolete prototype-era canonical documents should be revised or marked as legacy so future feature work has one source of truth.

Milestone 14 is not documentation-complete until this reconciliation has occurred.

## Non-Goals

- migrating current player/runtime data;
- preserving obsolete schema/API compatibility for historical reasons;
- moving the authoritative backend away from PHP as part of this overhaul;
- moving persistent runtime storage away from MySQL as part of this overhaul;
- rewriting Dice Goblins in Godot during vNext;
- shipping native mobile clients before the web architecture is stable;
- supporting portrait mobile gameplay;
- shipping server-only authored data to Phaser for convenience;
- implementing the entire future base-game biome roster as a prerequisite for vNext cutover;
- porting every existing Angular gameplay page one-for-one into Phaser scenes.

## Definition of Success

The overhaul succeeds when Dice Goblins still contains the game players recognize, but the implementation behaves like a deliberately built game rather than a browser application that accumulated game systems over time:

- Phaser is the cohesive player-facing game runtime;
- Angular is a thin web/platform shell;
- PHP exposes a coherent authoritative game API;
- MySQL represents mutable player/runtime state without prototype-era compatibility baggage;
- Git-tracked JSON owns authored gameplay content;
- secret authored mechanics are not intentionally exposed to the browser;
- rewards, progression, unlocks, purchases, reconstruction, objectives, and ownership have explicit non-overlapping contracts;
- one clean baseline can create the complete vNext database;
- Farm, Mountains, and Swamp demonstrate repeatable region architecture;
- responsive landscape play is a first-class client behavior;
- future game features can be added by following canonical system/technical documentation rather than rediscovering architecture from source code.
