---
Title: "vNext Prototype Code Disposition"
Status: Active Migration Guide
Last Updated: 2026-09-13
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-backend-internal-architecture.md
  - documentation/07-development-path/vnext-phaser-client-architecture.md
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/07-development-path/vnext-authored-content-model.md
Category: 07-development-path
Tags: [vnext, migration, reuse, prototype, code-disposition]
---

# vNext Prototype Code Disposition

## Purpose

Prevent two equally costly rewrite failures:

1. carrying obsolete prototype architecture into vNext merely because code already exists;
2. discarding proven algorithms, tests, tooling, and infrastructure that can be safely preserved or adapted.

This document is a migration map, not a historical archive and not a promise that every listed class survives unchanged.

Before a milestone replaces a substantive subsystem, inspect the relevant current source and tests again. The accepted vNext decision documents determine architecture. Existing source is evidence for useful behavior, edge cases, and algorithms.

## Disposition Vocabulary

- **Keep** — compatible with vNext responsibilities and worth preserving substantially intact. Small relocation/naming cleanup does not change this classification.
- **Adapt** — valuable implementation or behavior should be preserved, but dependencies, ownership, interfaces, or authored-data boundaries must change.
- **Rebuild** — desired behavior remains, but the current implementation shape conflicts enough with vNext that copying the implementation would preserve the wrong architecture. Mine tests/rules/edge cases before replacing it.
- **Retire** — the concept or implementation path is superseded. Remove it once no still-live path depends on it.
- **Re-evaluate** — insufficient reason to preserve or delete now; the owning future milestone must make the decision against its concrete requirements.

Do not create a permanent source-code archive directory. Git history is the archive. Keep prototype code in place while the still-live application depends on it; delete obsolete code in the milestone that proves its replacement.

## Executive Disposition

vNext is not a clean-slate rewrite.

The strongest reuse candidates are computational and infrastructure-oriented: deterministic random/formation helpers, combat ability handlers and combat algorithms, run-generation/pattern algorithms and validators, authentication/security mechanics, visual/debug tooling, audio behavior, and existing assets.

The weakest reuse candidates are orchestration and compatibility surfaces: catch-all controllers/services, page-oriented Angular gameplay, SQL-authored catalogs, claim workflows, `teams` compatibility, profile refresh orchestration, DB-backed run-pattern catalogs, and services that own persistence + transactions + gameplay rules + response mapping simultaneously.

The preferred migration pattern is therefore:

> Preserve tested computational behavior; replace obsolete ownership and boundaries around it.

## Backend Infrastructure and HTTP

| Current area | Disposition | vNext direction | Owner / removal trigger |
| --- | --- | --- | --- |
| `Core/Db.php`, `Core/Env.php`, `Core/Autoloader.php` | Keep | Continue as small infrastructure helpers unless implementation exposes a concrete problem. | Milestone 1; retain while useful. |
| `Core/Router.php` | Keep / Adapt | The small custom router is sufficient for the monolith. Retain routing mechanics; route inventory changes to the accepted vNext API. Avoid exposing raw internal exception detail in production behavior. | Milestone 1 onward. |
| `Core/Response.php`, `Core/Http.php`, `Http/JsonRequestBody.php` | Keep / Adapt | Retain lightweight HTTP helpers; normalize them only as required by the new query/command controllers. | Milestone 1 onward. |
| `backend/public/index.php` | Adapt | Preserve session-cookie, security-header, CORS, environment and router bootstrap behavior. Replace prototype route registration/controller composition with accepted vNext endpoints progressively. | Milestones 1-14; old routes disappear when their replacement domain ships. |
| controller service/composition helpers | Adapt | Simple dependency composition is fine; dependencies should resolve application/query services rather than perpetuating controller-to-catch-all-service coupling. | Milestone 1 onward. |

## Authentication and Account

| Current area | Disposition | vNext direction | Owner / removal trigger |
| --- | --- | --- | --- |
| `AuthController.php` behavior | Adapt | Preserve validation, password hashing/verification, OAuth state handling, session-ID rotation, password-reset safety and error cases. Move toward thin HTTP adaptation and the accepted users/local-credential/external-identity storage model. | Milestone 1. |
| `UserRepository.php` | Rebuild from proven behavior | Current row shape puts Discord/local credentials directly on `users` and owns transactions that conflict with the vNext model. Preserve normalization, locking/reset-token semantics and account lookup behavior while implementing new auth repositories/schema. | Milestone 1 DB baseline. |
| `SessionService.php` | Adapt | Preserve minimal session identity, CSRF rotation and login/logout mechanics. Remove read-side player provisioning: `GET /session` and bootstrap queries must not create gameplay state. | Milestone 1. |
| `CsrfService.php` | Keep | Token generation, rotation, clearing and constant-time validation fit the accepted architecture. It may move to infrastructure without semantic redesign. | Milestone 1 onward. |
| frontend auth/session recovery | Adapt | Preserve login/logout/session-expiration recovery for Angular platform/account shell. Split game profile/cache responsibilities out of the Angular session service. | Milestone 1. |
| Angular auth/guest route guards | Adapt | Preserve authentication gating for `/game` and account/public routes. Retire gameplay feature-unlock guards because Phaser owns in-game navigation/availability. | Milestone 1-2. |

## Database and Repositories

The prototype migration chain is not reusable as the vNext schema baseline. It mixes mutable runtime state with SQL-authored catalogs and compatibility structures that vNext rejected.

| Current area | Disposition | vNext direction | Owner / removal trigger |
| --- | --- | --- | --- |
| prototype migration chain | Retire | Create a fresh vNext baseline. Do not replay old catalog/history migrations. | Milestone 1 DB baseline. |
| `PlayerStateRepository` | Adapt / Rebuild SQL | Preserve useful row-lock/update patterns but target the new strongly typed `user_state`, including `player_revision`. | Milestone 1. |
| `EnergyRepository` | Rebuild into vNext player-state persistence | Energy belongs on `user_state`; no separate persisted `energy_max`/catalog state. | Milestones 1 and 3. |
| `UnitRepository`, `DiceRepository` | Adapt / Rebuild SQL | Repository role is valid; schema and authored-ID relationships change substantially. Keep useful ownership/query patterns, not old row contracts. | Milestone 2. |
| `TeamRepository` | Rebuild as Squad persistence | Replace compatibility `team` terminology and old structure with `squads` + fixed-position `squad_units`. | Milestone 2. |
| `RunRepository`, `RunNodeRepository`, `RunEdgeRepository` | Adapt / Rebuild SQL | Relational run persistence remains valid. Rework around accepted run aggregate/state and authored stable IDs. | Milestone 3. |
| `BattleRepository`, battle-log persistence | Adapt | Preserve reconnect-safe persisted result/playback concepts but conform to `battles` + immutable playback contract. | Milestone 4. |
| `BattleRewardsRepository` / claim persistence | Retire | Rewards auto-apply during authoritative resolution; replace claim-oriented storage with resolved-event/idempotency safety where required. | Milestone 5. |
| `RegionRepository` and other static catalog repositories | Retire | Authored regions/catalogs live in JSON `ContentRegistry`, not MySQL. | Owning content milestone; region repository should not survive Milestone 3. |
| `RunPatternCatalogRepository` | Retire | Run-pattern authored definitions must not require SQL catalog synchronization. | Milestone 3. |

Repositories should remain deliberately boring. Useful SQL/locking fragments may move forward, but repository classes must not carry authored mechanics, API shape, or business transaction ownership.

## Combat and Abilities

### `DeterministicRunNodeResolver`

**Disposition: Adapt by extraction/decomposition.**

Do not keep this class intact and do not rewrite combat from memory.

The current resolver contains valuable deterministic combat behavior, but it also loads state directly through PDO, resolves non-combat nodes, applies run/chaos concerns, calculates currencies/rewards, invokes progression/unlock behavior, and assembles playback. That violates the accepted vNext engine boundary.

Milestone 4 should extract the tested combat computation into the vNext shape:

```text
CombatInput
  -> CombatEngine
  -> CombatResult
     - outcome
     - resulting combat/run-unit facts
     - XP/gameplay facts
     - playback events
```

Preserve through characterization/regression tests where still intended:

- deterministic scheduling/tick behavior;
- targeting behavior and formation geometry;
- damage/status/passive interaction rules that remain current;
- ability execution semantics;
- deterministic playback/event production;
- edge cases represented by current unit/integration tests.

Do not preserve in the engine:

- PDO/SQL loading;
- node lifecycle orchestration;
- reward grants/currency mutation;
- progression persistence;
- authored DB catalog lookups;
- Rest/Loot/Hazard/Shrine/Chaos orchestration simply because the current resolver contains them.

### Ability subsystem

| Current area | Disposition | vNext direction |
| --- | --- | --- |
| handler interface/registry pattern | Keep / Adapt | A handler-selected-by-authored-definition model fits vNext well. |
| active/passive handler implementations | Adapt | Preserve tested executable effects that remain current; make them operate on engine/domain inputs rather than persistence. |
| `AbilityTarget`, `AbilityType` concepts | Keep / Adapt | Retain useful domain vocabulary when it still matches accepted combat rules. |
| `AbilityDefinition` / config merge behavior | Adapt | Definitions should be sourced from the JSON `ContentRegistry`; preserve useful normalization/composition rules. |
| hardcoded `AbilityRegistry` authored catalog | Retire as authority | Authored identity/config moves to canonical JSON. Registry behavior may become a view over `ContentRegistry`. |

### Combat tests

**Keep / Adapt as characterization tests.** Existing handler coverage, handler-effect tests, deterministic resolver primitives, formation integration and battle-resolution tests are valuable evidence. Rewrite fixtures/interfaces as necessary, but do not discard coverage merely because class boundaries change. Tests that assert obsolete claim/catalog behavior should be retired or rewritten.

## Run Generation and Patterns

### `RunGraphGenerator`

**Disposition: Adapt / Decompose.**

The generator contains substantial proven graph-generation behavior, but currently depends on PDO/SQL pattern catalogs and embeds region/dialogue/config definitions in PHP. Preserve algorithms; move authored configuration out.

Milestone 3 target:

```text
Run generation config + seed + authored definitions
  -> RunGenerator
  -> GeneratedRunGraph
```

Keep or adapt:

- deterministic graph/path generation algorithms;
- pattern/grid/tile composition algorithms;
- graph validation;
- deterministic tracing/diagnostics;
- useful generator regression/simulation coverage.

Remove from the generator:

- PDO dependency;
- SQL-authored pattern catalogs;
- hardcoded region tuning that belongs in JSON;
- hardcoded dialogue/story placement definitions that belong in authored content;
- historical generator-version compatibility that lacks a concrete vNext requirement.

The Farm fixed-graph slice has now been mined into canonical JSON plus the pure vNext `FixedGraphRunGenerator`. Its useful five-node order, placement, availability, and connectivity are covered at the new boundary. The retained prototype generator is no longer authority for Farm generation, but remains in place as evidence for Mountains/Swamps pattern algorithms, dialogue placement, and other later mechanics until their owning packages reconcile them.

### Pattern toolchain

`RunGraphValidationService`, grid/pattern validators, compilers, tile composers and variant compilers are **Keep / Adapt** candidates when they are deterministic and storage-independent.

`RunPatternCatalogRepository`, `RunPatternCatalogSyncService`, and `backend/bin/sync-run-patterns.php` are **Retire** because vNext does not synchronize authored patterns into MySQL.

Generator comparison, inspection and simulation CLI tools are **Adapt** candidates. Repoint them at the canonical JSON registry/current generator rather than deleting mature diagnostics.

## Domain and Application Services

| Current service/concept | Disposition | Why / vNext destination | Owner |
| --- | --- | --- | --- |
| `EnergyService` | Adapt arithmetic; rebuild boundary | Regen timing/math is useful. Current transaction ownership, persisted max and read-side mutation conflict with vNext. Extract deterministic Energy calculation; application mutations persist effects. | Milestones 1/3/7. |
| `ShopService` | Rebuild orchestration; mine rules | Current service combines PDO, authored prices/catalogs, transactions and response construction. Use application command + ContentRegistry + repos. | Milestone 7. |
| `AcademyService` | Rebuild orchestration; mine rules | Same boundary issue; authored upgrades/costs move JSON. | Milestone 8. |
| `WrongMachineReconstructionService` | Rebuild orchestration; mine intended rules | Align with accepted kin/Raw Chaos semantics and generic reward/unlock infrastructure. | Milestone 9. |
| `BountyBoardService` | Retire | Bounty becomes an authored objective type, not its own persistence/service domain. | Milestone 11. |
| `ObjectiveService` | Adapt | Preserve useful progress/event rules, consolidate bounties into objective model. | Milestone 11. |
| `CodexOwnershipService` | Adapt | Unique ownership semantics remain useful; integrate generic reward/event flow. | Milestone 11. |
| `DiceAffixService` | Retire | Superseded by authored materials + aspects + profiles. | Milestone 2/7. |
| `DiceValuationService` | Re-evaluate / Adapt | Pure valuation behavior may be useful after profile/aspect model is concrete. | Milestone 7. |
| `DiceSalvageService` | Adapt behavior; rebuild command | Preserve accepted salvage economics if still desired, but application command owns transaction/idempotency. | Milestone 7. |
| `LineageUnlockService` | Retire / replace | Kin restoration uses generic unlock semantics rather than a parallel lineage progression system. | Milestone 9. |
| `UserUnlockService` | Adapt | Generic unique unlock ownership remains valid; revise schema/namespaces around stable authored IDs. | Milestones 3/5/8/9. |
| `PromotionService` | Adapt rules; rebuild command | Preserve promotion eligibility/stat/path rules that remain intended, but move orchestration to vNext command/repositories/content. | Milestone 8. |
| `UnitProgressionService` | Adapt | Deterministic advancement rules are good reuse candidates after authored unit content is established. | Milestones 2/5/8. |
| `UnitNameGenerator` | Keep / Adapt | Independent generation logic can survive if naming design remains desired. | Milestone 2. |
| `UnitCapstoneService` | Retire | Capstone outcome is normal ability ownership; no separate capstone state/domain. | Milestone 8. |
| `UnitLoadoutService` | Adapt | Preserve useful validation rules; target atomic complete loadout + die-binding model and active-run locks. | Milestone 2. |
| `UnitMutationGuard` | Adapt | Central active-run configuration protection remains useful, likely as domain/application validation. | Milestones 2-3. |
| grant/owned-unit/owned-dice/user-asset services | Rebuild under shared grant pipeline | Mine creation defaults/ownership invariants; avoid many services independently granting durable rewards. | Milestone 5, with earlier direct creation only when a milestone concretely requires it. |
| `PlayerBootstrapper`, `ProfileService`, `ProfileDtoMapper` | Retire / replace | vNext provisioning occurs on authoritative creation mutations; reads do not bootstrap. Catch-all profile is removed. | Milestone 1. |
| `RunLifecycleService` | Rebuild orchestration; mine transitions | Preserve useful run terminal/cleanup edge cases, remove claims and oversized workflow ownership. | Milestones 3-5. |
| `RunCombatModifierService` | Adapt | Temporary modifier behavior can feed engine input from accepted `run_modifiers`. | Milestones 4/10. |
| `EncounterPrimitiveCatalog` | Adapt executable primitives; retire hardcoded catalog authority | Authored encounter definitions move JSON; backend handlers/rules remain code. | Milestone 10. |
| `ChaosEncounterService` | Re-evaluate / Adapt | Preserve only mechanics still desired when Chaos nodes are implemented; new multi-step API follows current node-state rule. | Milestone 10. |
| `StarterPackProvisioningService` | Rebuild/defer | Do not use starter-pack provisioning to force unit/dice/squad persistence into Milestone 1. Fresh-player gameplay provisioning belongs to onboarding. | Milestone 12. |
| balance/simulation/dev-tools services | Adapt | Diagnostics are valuable, but update them to call vNext domain engines/content rather than old schema/catalogs. | Owning gameplay milestone. |
| `UserDataSyncService` / profile synchronization concepts | Retire unless a concrete remaining shell need exists | Phaser uses affected-domain responses + cache invalidation, not profile sync. | As replacement APIs ship. |
| `SpliceVariantService` | Re-evaluate | Inspect against final kin model when Milestone 9 begins; do not treat prototype lineage behavior as authoritative. | Milestone 9. |
| `SquadCapacityService` | Re-evaluate / Adapt | Formation has nine positions; retain only capacity rules that are still deliberate gameplay. | Milestone 2. |

## Frontend Angular Shell

### Keep / Adapt

Angular itself remains part of vNext and is not being replaced wholesale.

Preserve/adapt:

- public/landing/login surfaces;
- authentication and guest-route gating;
- account/platform shell behavior;
- API base/runtime configuration useful to both shell and new game client;
- session-expiration/login recovery behavior where it belongs to the platform shell;
- public-site styling/assets that are still current.

### Retire progressively

Existing Angular gameplay routes/pages are not the vNext game client. Academy, Codex, Dice, Regions, Warband, Unit Detail, Squad Detail, Shop, Wrong Machine, run pages, battle-oriented page flows, and similar gameplay components should be removed after the corresponding Phaser capability replaces them.

Milestone 2 verification retired the unrouted Angular Warband, Dice, Unit Detail, and Squad Detail pages, together with the page-only `DiceService` and `SquadService`. Prototype Unit/Profile services and later gameplay pages remain only where they still provide evidence for unimplemented milestones; they are not part of the live `/game` composition.

Do not mechanically port component/service structure into Phaser. Existing pages may be inspected for useful copy, art references, interaction lessons, accessibility considerations, or edge cases.

The old Angular home page should be evaluated during GameScene/Camp work: preserve public/platform responsibilities if any, but the playable Camp belongs in Phaser.

## Frontend Services and Utilities

| Current area | Disposition | vNext direction | Owner |
| --- | --- | --- | --- |
| Angular `SessionService` | Adapt / Split | Keep shell authentication/session recovery. Retire its catch-all profile, feature unlock, units, squads and dice cache responsibilities. | Milestone 1. |
| Angular `ApiHttpService` | Adapt behavior, not dependency | Preserve `credentials: include`, API-base handling, auth-recovery and CSRF semantics. Phaser must have a framework-neutral runtime API client rather than depending on Angular DI/service ownership. | Milestone 1. |
| `ViewportOrientationService` + tests | Adapt | Current portrait-phone detection is useful prior art. Move final gameplay gate to GameHost/GameRuntime, preserve test cases and refine around accepted mobile-landscape rule. | Milestone 1. |
| audio manifest/models | Adapt | Presentation/audio identities and metadata are reusable where still current. | Milestone 1+ / game-feel. |
| Angular `AudioDirectorService` | Adapt behavior | Preserve Howler/browser unlock, mute/preferences, visibility handling, loop continuity and intent ideas. Rehome gameplay audio as persistent Phaser-runtime infrastructure; route-driven Angular audio should not remain gameplay authority. | Milestone 1 foundation, expanded later. |
| route-audio mapping | Retire / replace | Phaser scene/screen context replaces Angular gameplay route context. | As Phaser destinations ship. |
| battle-playback models/adapter/tests | Adapt | Valuable presentation normalization prior art; update to the new immutable playback contract and Phaser BattleScene. Remove claim coupling. | Milestone 4. |
| debug-capture tooling | Adapt | Preserve deterministic-state idea and migrate toward Phaser fixture/screen launch states. | Milestones 1-4. |
| hardcoded client region/ability/dialogue catalogs | Retire as authorities | Replace with allowlisted projection from canonical JSON. Mine approved presentation content/assets when authoring new JSON. | Milestones 1-3/11. |
| giant shared API model file | Retire / split | Introduce focused vNext DTOs by API/domain rather than extending the prototype catch-all type surface. | Incrementally. |
| Angular gameplay API services | Retire progressively | Phaser calls the accepted gameplay API directly. Tests may be mined for edge cases, not used to preserve page-oriented contracts. | Owning gameplay milestone. |
| global/component gameplay CSS | Retire progressively | Keep public-shell styling; gameplay layout moves to Phaser logical layout/components. | As Phaser replaces each page. |

## Assets

**Keep by default.** Existing visual/audio assets are not architectural debt merely because their current presentation code is being replaced.

- Reuse current game assets whenever they fit the retained visual direction.
- `raw-assets/` remains valuable source material.
- Do not mass-delete assets during architectural migration.
- Remove genuinely unused production assets only when the replacing milestone proves they are obsolete.
- Built deployment bundles are outputs, not design authority; rebuild them from vNext rather than treating old bundle contents as canonical.

## Tests and Characterization Strategy

Tests are an important source of reusable knowledge, but their disposition follows what they assert.

### Keep / Adapt

- deterministic RNG and formation tests;
- combat handler/effect/targeting tests;
- run graph/pattern/compiler/validator tests;
- generator simulation/characterization tests;
- authentication security and failure-path tests;
- frontend session-recovery, orientation and other infrastructure tests whose behavior remains accepted;
- battle-playback adapter tests where the playback fact model remains useful.

When class boundaries change, port the assertions to the new boundary instead of deleting coverage.

### Rewrite / Retire

Tests whose main assertion is a superseded architecture should not block vNext. Examples include:

- battle/reward claim flows;
- `/profile` catch-all contracts;
- `teams` compatibility routes;
- SQL-authored catalog synchronization;
- dialogue-seen state;
- bounty sync/claim as a separate domain;
- capstone-specific persistence;
- per-slot die assignment endpoints;
- Angular gameplay-page routing as the game architecture.

Before deleting such a test, inspect it for negative/security/idempotency cases that should be represented in the replacement test suite.

## Development, Simulation, and Visual Tooling

| Current tool | Disposition | Direction |
| --- | --- | --- |
| `skills/scene-screenshot` | Keep / Adapt | Expand for vNext Phaser Camp/Run/Battle deterministic fixtures and responsive captures. |
| `skills/ux-scene-review` | Keep / Adapt | Continue using it for game-screen review rather than Angular page preservation. |
| `scripts/capture-scene.mjs` | Adapt | Point fixture launch/capture at the persistent `/game` Phaser runtime. |
| run generator compare/inspect/simulate CLI tools | Adapt | Repoint to JSON ContentRegistry + current generator. |
| `sync-run-patterns.php` | Retire | SQL pattern synchronization is rejected. |
| balance simulation tooling | Adapt | Keep simulation capability; reconnect it to extracted engines/current authored content. |
| backlog/startup/doc validation tooling | Keep | Continue where consistent with cleaned vNext agent workflow. |
| bundle/release-readiness tooling | Re-evaluate / Adapt | Update after build/deploy shape stabilizes; do not preserve obsolete assumptions for compatibility. |

## Docker, CI, and Deployment

### Docker/dev environment — Keep / Adapt

The existing PHP/MySQL/frontend containerized development shape remains useful. Update database initialization and commands for the fresh vNext baseline rather than replacing Docker merely because application architecture changes.

### GitHub workflows — Adapt

Keep CI structure where practical. Update jobs as the vNext baseline introduces:

- fresh-database verification;
- canonical JSON validation;
- client-projection secrecy/allowlist checks;
- Phaser tests/build;
- responsive screenshot checks where automated;
- removal of prototype migration/catalog expectations.

### Deployment artifacts — Rebuild

Generated `artifacts/bundles` should not influence vNext architecture or authored content. They are regenerated outputs. Remove stale checked-in bundles later if they create operational confusion, but do not mistake them for reusable source.

## Milestone Ownership Summary

- **Milestone 1:** auth/session/security infrastructure; fresh DB baseline; ContentRegistry/projection; bootstrap; Angular host; new Phaser runtime; adapt orientation/debug/audio foundations as needed.
- **Milestone 2:** units/dice/squads repositories and rules; naming/loadout/configuration; retire Angular Warband/Dice/Squad gameplay services/pages as replacements ship.
- **Milestone 3:** extract/adapt run-generation algorithms/tooling; retire SQL region/pattern catalogs and associated sync paths.
- **Milestone 4:** extract combat core and adapt battle playback; preserve characterization tests; retire old battle orchestration/claim assumptions.
- **Milestone 5:** replace grant/claim/reward orchestration with event/reward/grant pipeline; adapt XP/progression facts.
- **Milestones 7-11:** individually re-evaluate economy, progression, kin, encounter, Codex/objective prototype services before replacement using this map as the initial classification.
- **Milestone 12:** deliberately rebuild fresh-player provisioning/onboarding rather than carrying starter-pack assumptions into Milestone 1.
- **Milestone 14:** final removal sweep for prototype routes/pages/services, stale generated artifacts and compatibility code that survived transitional dependencies.

## Removal Rule

A `Retire` classification does not mean "delete immediately."

Delete prototype code when all of the following are true:

1. the owning vNext replacement is implemented and verified;
2. no live route/page/service/tool still depends on the prototype implementation;
3. useful behavioral tests/edge cases have been transferred or intentionally rejected;
4. accepted documentation points only at the replacement;
5. Git history is sufficient for any later archaeological need.

This keeps the branch runnable during migration without allowing legacy implementation to become a second design authority.
