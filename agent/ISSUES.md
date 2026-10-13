# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 6 - Run-scoped modifier model + authoritative application

**Status:** In Progress
**Priority:** High

#### Problem

Packages 1-5 established the authoritative exploration frontier and generalized Rest, Hazard, Shrine, and Chaos encounter foundations, but vNext does not yet have an accepted persisted model for temporary effects that survive beyond the resolving encounter and influence later gameplay in the same run.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Packages 1-5 are approved through Chaos foundation `32caee03565d2c86c0f54e50f076975f13ea806b`.

The run is authoritative server/MySQL state. Node resolution, encounter effects, frontier changes, player revision, and finalized receipts share the existing transactional/idempotency boundary. Rest/Hazard use immediate HP effects; test-only Shrine proves authored choice; test-only Chaos proves finalized server-owned randomness. Farm remains fixed and Mountains remains the only current production expansion, with Hazards but no Shrine/Chaos.

Canonical reward boundaries remain in force: temporary run effects are effects/domain state, not rewards. Rewards add assets/progression/entitlements and continue through the accepted event/reward/finalization architecture.

Prototype code contains prior run-combat-modifier concepts. Treat it only as migration evidence: inventory it before designing the vNext model, reuse semantics where they still fit canonical vNext architecture, and do not preserve prototype shape merely for compatibility.

#### Purpose

Establish the smallest reusable persisted run-scoped modifier model needed for encounters to create temporary consequences that affect later gameplay and expire deterministically.

Package 6 should prove one narrow modifier lifecycle end-to-end: authoritative creation, projection where player-visible, later gameplay application, deterministic consumption/expiry, reload/reconnect, and idempotent receipts. It must not turn modifiers into a generic scripting engine or prematurely implement Package 7 multi-step/consumable mechanics.

#### Canonical design constraints

- Modifiers are owned by an active run and cannot leak to another run or permanent player/unit state.
- Persist only durable state needed to reproduce the modifier after reconnect and apply/expire it authoritatively.
- Authored modifier semantics/tuning belong in validated canonical content where appropriate; runtime instances reference stable authored identity plus minimal lifecycle state rather than copying arbitrary effect graphs into SQL.
- Backend owns modifier eligibility, creation, application, remaining duration/uses, consumption, expiry, and resulting gameplay state.
- Client may receive a narrow player-visible projection sufficient to explain active modifiers, but never hidden tuning, arbitrary effect configuration, or server-only predicates.
- Exact-key replay of the encounter/node that creates a modifier must not create a duplicate modifier or reset its duration.
- Exact-key replay of later gameplay that consumes/applies a modifier must not apply or consume it twice.
- Reload/reconnect must reproduce the same active modifier state and remaining lifecycle.
- Terminal run completion/failure must leave no modifier capable of affecting later runs. Follow existing run retention/cleanup policy rather than inventing a second cleanup architecture.
- Preserve the exploration frontier semantics from Package 1.
- Respect the reward/effect boundary; modifiers do not directly grant currency/items/units/dice/XP/Codex/unlocks.
- Do not introduce production Shrine or Chaos content to Farm/Mountains. If a source encounter is needed for proof, use validated test-only authored fixtures.
- Do not introduce Package 7 consumables or multi-step encounter persistence.

#### Required inventory before implementation

Inspect current canonical combat/run architecture and the retained prototype run-modifier implementation, including any `RunCombatModifierService`, combat participant metadata, resolver handling, persistence, and tests that still exist.

Document in implementation evidence which prototype concepts were reused, replaced, or intentionally dropped. Current vNext canonical architecture wins on conflict.

#### Minimum modifier proof

Use one intentionally narrow, player-comprehensible modifier family rather than a generalized expression language.

Preferred proof: a **next-combat participating-warband stat modifier** created by a test-only encounter and consumed by the next eligible Combat/Boss resolution in that run.

Choose the smallest stat/effect already cleanly supported by the deterministic combat engine. Do not add several modifier families merely because the prototype had them.

The authored definition should provide a stable modifier ID and bounded tuning. The persisted runtime instance should contain only the identity/source/lifecycle information necessary to reproduce authoritative behavior.

If the existing combat architecture makes a different equally narrow modifier materially safer, use it and explain why in implementation evidence.

#### Backend/content scope

1. Define and validate the minimal authored run-modifier definition required by the proof. Keep the schema bounded and family-specific.
2. Add the minimal persistence required for active run modifier instances. Prefer integration with existing run persistence/migrations rather than an encounter-family table.
3. Add an authoritative creation/application boundary reusable by later Shrine/Chaos production content without hard-coding those encounter families into combat/runtime infrastructure.
4. Create the modifier through a validated test-only encounter/action fixture or equivalent test-only source using the existing action-aware node-resolution transaction.
5. Prove creation is atomic with node completion/frontier/revision/receipt finalization.
6. Apply the active modifier to the next eligible combat through the canonical combat assembly/resolution path. Do not make the client calculate modified stats/damage.
7. Consume/decrement/expire the modifier atomically with the eligible combat resolution.
8. Finalized combat/node receipts must contain enough authoritative facts to prove what modifier was applied/consumed without exposing private authored tuning unnecessarily.
9. Reload/re-query before consumption reproduces the active modifier; reload/re-query after consumption shows it expired/updated.
10. A failed or rejected encounter/combat resolution must not create, consume, or partially apply a modifier.
11. Run ownership must be enforced. A modifier from run A cannot affect run B, another user, or later permanent unit state.
12. Define deterministic behavior for Combat versus Boss eligibility and document it. Prefer both as combat-like eligible nodes if that matches the canonical combat path, but do not include Chaos merely because prototype code treated it as combat-like; vNext Chaos is currently a non-combat encounter family.

#### API/read projection

Expose active player-visible run modifiers only if needed for understandable gameplay/reconnect state. If projected:
- use stable modifier identity plus player-facing label/description and lifecycle such as remaining eligible combats;
- source presentation may be included only when useful and safe;
- do not expose raw internal effect graphs, hidden predicates, server RNG state, or canonical catalogs;
- keep parsing strict and fail closed on malformed authoritative payloads.

Do not add a standalone modifier endpoint unless the existing current-run/combat contracts cannot cleanly carry the required state.

#### Phaser/runtime scope

Keep frontend work minimal and family-agnostic. RunScene may display active modifier state if required to make the persisted consequence understandable, but it must not calculate its effect or consumption.

BattleScene/combat presentation may consume authoritative applied-modifier metadata/result facts, but must not independently decide whether a modifier applies.

Do not perform Package 8's broader encounter/branching visual integration in this package.

#### Verification requirements

Prove at minimum:
- authored modifier definitions validate and malformed IDs/types/tuning/lifecycle values fail validation;
- runtime modifier instances are run-owned and reconnect-safe;
- the test-only source creates exactly one modifier atomically with its node resolution;
- exact-key replay of source resolution creates no duplicate and does not reset lifecycle;
- an unrelated non-combat node does not consume a next-combat modifier;
- the next eligible Combat/Boss applies the modifier authoritatively and exactly once;
- modifier application changes the intended authoritative combat calculation/result in a deterministic fixture;
- consumption/expiry is persisted atomically with combat resolution;
- exact-key replay of the combat does not apply or consume the modifier twice;
- failed/rejected combat does not consume the modifier;
- reload before and after consumption reproduces correct modifier state;
- modifier from one run/user cannot affect another;
- terminal run behavior cannot leak active modifiers into a later run;
- browser-facing projection, if any, excludes hidden tuning/configuration;
- existing Combat/Boss behavior without modifiers is unchanged;
- Farm and Mountains production content remain free of production Shrine/Chaos modifier sources unless canonical content already requires otherwise;
- Rest/Hazard/Shrine/Chaos/frontier/idempotency regressions remain green;
- reward architecture remains untouched by temporary modifier application;
- active-run Warband restrictions and broader run completion remain green.

Run applicable gates from `agent/QUALITY_GATES.md`.

#### Scope exclusions

Do not add:
- a generic arbitrary effect/expression language;
- multiple speculative modifier families;
- production Shrine or Chaos content to Farm/Mountains;
- Swamp production content/Frog Kin;
- contextual consumables;
- multi-step encounter persistence;
- permanent unit progression/equipment changes through modifiers;
- direct reward grants through modifier effects;
- Milestone 11+ functionality;
- final visual redesign.

#### Completion evidence

Report:
- implementation SHA;
- prototype modifier inventory and explicit reuse/replace/drop decisions;
- authored modifier schema and persisted runtime shape;
- source-creation transaction/idempotency evidence;
- exact authoritative combat application semantics;
- Combat/Boss eligibility decision;
- consumption/expiry transaction and replay evidence;
- reload/run-ownership/terminal isolation evidence;
- browser secrecy/projection evidence;
- no-modifier combat regression evidence;
- Rest/Hazard/Shrine/Chaos/frontier regression evidence;
- focused/full quality-gate results;
- confirmation no Package 7+, production Swamp, or reward-bypass scope was introduced.

Leave Package 6 **In Progress** for architectural review. Do not promote Package 7 yourself.

#### Package 6 implementation evidence (review candidate)

- Prototype inventory: `RunCombatModifierService` applied broad `run_status_effects` objects to individual unit combat metadata, while prototype `RunLifecycleService` stored JSON status effects per run unit and removed next-combat effects during resolution. Retained the useful next eligible combat and participating-warband semantics. Replaced arbitrary per-unit JSON effects and prototype orchestration with one authored, bounded modifier definition, a run-owned relational instance, and the vNext combat assembly/transaction. Dropped generic stat multipliers, damage affixes, family aliases, and compatibility with prototype storage.
- Authored/runtime shape: validated `run_modifier` has stable `modifier.*` ID, visible name/description, `next_combat_attack_bonus`, Attack bonus 1–10, and exactly one eligible combat. `run_modifiers` persists only run/source node/authored ID/remaining combats; composite source FK and run-scoped unique key guard ownership. The clean `goblin_test` baseline reset succeeded with this table. The source is a validated test-only Shrine action; Farm/Mountains production content and client catalog contain no modifier source or tuning.
- Creation/application: the existing action-aware node command creates the row in its node/frontier/revision/receipt transaction. Exact source replay returns the finalized receipt and leaves one row. Combat and Boss share the authoritative combat handler: it locks active rows by run, adds the authored bonus to every participating player Attack during snapshot assembly, runs deterministic combat, and consumes rows in the battle/node/revision/receipt transaction. The receipt lists applied IDs and zero remaining combats without authored tuning. Rejected combat keeps the row; an unrelated Loot resolution does not consume it. The focused deterministic fixture verifies the +2 snapshot and increased attack component/damage for the same ability, target, and roll. No-modifier paths retain their prior receipt shape.
- Reload/isolation: a new PDO/current-run query reproduces the active modifier before consumption and omits it afterward. A terminal run exposes no current-run state; its retained modifier is scoped to the original run and absent from a later run and another user. Existing run deletion cascades to the modifier row. Client current-run projection contains only instance ID, authored identity, visible label/description, and remaining combats; strict frontend parsers reject private attack tuning/effects in both projections and receipts. RunScene displays only the authoritative active label/lifecycle and re-queries after resolution.
- Focused verification: modifier content 2 tests/14 assertions; lifecycle 3 tests/31 assertions; schema baseline 8 tests/74 assertions; focused frontend contracts 21 tests passed. Final `npm run verify:package`: **PASS**; backend 430 tests/2408 assertions with 173 retained prototype tests skipped, frontend 553 tests, production build, bundle, content, context, docs, and diff checks all passed (`artifacts/verification/package-summary.json`). Deterministic `run-modifier` captures passed and were visually reviewed at Compact 960×540, reference 1600×900, and Wide 2200×900; the active label/lifetime remains readable. The initial full gate found one stale schema table expectation, corrected before the clean final pass. No Package 7+, production Swamp, reward bypass, or new production Shrine/Chaos content was introduced.
