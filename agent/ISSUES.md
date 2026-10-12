# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 5 - Chaos encounter foundation

**Status:** In Progress
**Priority:** High

#### Problem

Rest, Hazard, and test-only Shrine encounters now prove the generalized non-combat interaction boundary, but Chaos has no validated family contract or authoritative resolution proof for later Swamp content.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Package 1 is approved at `86c88987aaf14391660cb3b868cd10e537dd19d8`. Package 2 is approved through `f782b368e3bc1e4672bcec886bf3a29263e5baf8`. Package 3 is approved through `31b53b106d84edf2db846ec971877080e08aac9f`. Package 4 is approved through correction `c121a67a4903637424c68fb9347f9f9192f4c2e5`.

The run model is an authoritative persisted exploration frontier. Package 2 established generalized authored `non_combat` interaction projection/action resolution. Package 3 proved production Hazards in Mountains. Package 4 proved a second multi-choice family, Shrine, using test-only fixtures without adding a family-specific read/client path or bypassing the canonical reward model.

Regional vocabulary remains intentional: Farm is Combat/Loot/Rest/Boss/Exit; Mountains adds Hazard and exploration branching/dead ends; Shrine and Chaos are reserved for Swamp and later regions. Swamp production content remains Milestone 13.

#### Purpose

Establish the smallest reusable Chaos encounter-family contract needed by later Swamp content without adding Chaos nodes to Farm or Mountains and without prematurely implementing Swamp.

Chaos encounters should represent volatile non-combat interactions whose authoritative outcome may vary, but Package 5 is an architectural foundation rather than a production content package. It should prove server-owned finalized randomness and/or authored Chaos choices through the existing encounter boundary without introducing Package 6 run modifiers or Package 7 multi-step/consumable state.

Because no current production region should contain Chaos, use validated test-only authored fixtures. Do not add speculative production Chaos content merely to manufacture production coverage.

#### Canonical design constraints

- Reuse the Package 2 generalized authored non-combat projection and action-resolution boundary.
- Do not add Chaos-specific branching to `CurrentRunQuery`, controllers, API endpoints, or Phaser runtime interaction dispatch.
- Add only the authored node-family metadata and server resolution semantics genuinely needed for Chaos.
- Player-visible title/body/prompt/action labels may be projected when authorized. Effect configuration, odds, random tables, tuning, and hidden eligibility remain server-private.
- Backend authority owns action validity, random outcome selection, effects/rewards, node completion, frontier changes, revision, and finalized receipt.
- Any random outcome must be finalized once. Exact-key replay must return the same result without rerolling, reapplying, or changing the frontier twice.
- Preserve Farm and Mountains production content exactly; neither region receives Chaos nodes or Chaos encounters.
- Do not implement Swamp production topology/content, Frog Kin, run-scoped modifiers, contextual consumables, or multi-step encounter persistence.
- Respect the canonical reward/effect boundary. Currency, items, units, dice, XP, Codex entries, and unlocks are rewards and must not be introduced as direct Chaos effects. Immediate HP/state mutations may remain effects where semantically appropriate.
- Keep the Chaos effect/outcome vocabulary no more expressive than required for this proof. Do not build a general scripting language or arbitrary effect graph.

#### Backend/content scope

1. Add the minimal canonical Chaos run-node/encounter-family association needed to flow through the existing generic non-combat contract.
2. Define a small test-only Chaos encounter that demonstrates the family's distinguishing property. Prefer a single authorized action with a server-owned finalized random choice between two already-supported immediate HP outcomes, or an equally small design that does not require later-package architecture.
3. Keep random outcome tables/weights server-private and validated. Use stable authored outcome IDs if the finalized result needs to identify the selected outcome.
4. Use validated test-only authored fixtures only. Do not place Chaos definitions in Farm or Mountains production region content.
5. Resolve Chaos actions through the existing action-aware run-node command boundary. Validate ownership, node availability, persisted encounter association, encounter/action identity, region compatibility, family compatibility, effect/outcome validity, and server-owned semantics.
6. Apply the selected immediate consequence, node completion, direct-successor unlocking, retained frontier, `player_revision`, and finalized receipt atomically.
7. Exact-key replay must not reroll or reapply the selected outcome.
8. Reload/re-query must reproduce the authoritative finalized run/unit/frontier state.
9. Keep browser projection narrow; do not expose outcome weights, effect types/amounts, or canonical encounter catalogs.
10. Do not introduce durable per-encounter progress in this package.

#### Randomness requirements

If the test Chaos fixture uses randomness:
- production selection is server-owned and uses the project's accepted secure randomness approach;
- tests use an injectable/deterministic selection boundary rather than statistical/flaky assertions;
- repository order, database IDs, insertion order, or client input must not determine the random result;
- the selected authored outcome and exact resulting state transition are recorded in the finalized receipt;
- exact-key replay returns that persisted selection and never consumes another random choice.

Do not expose server RNG state or hidden outcome weights to the browser.

#### Phaser/runtime scope

Use the existing generic non-combat interaction UI and action submission path. No new Scene or Angular route is allowed.

Only make frontend changes if the existing generic contract cannot correctly render/submit the test Chaos interaction or reconcile its finalized result. Any such change must remain encounter-family agnostic.

Do not add production Chaos navigation or production-region captures. Test-only runtime/component coverage is sufficient where no production region can legitimately expose Chaos yet.

#### Verification requirements

Prove at minimum:
- Chaos authored definitions/family metadata validate and malformed family/action/effect/outcome/reference combinations fail validation;
- Farm contains no Chaos node/encounter and remains its fixed five-node graph;
- Mountains contains no Chaos node/encounter and retains its accepted Hazard topology;
- an available test-only Chaos reaches the authorized client projection through the generic Package 2 path;
- `CurrentRunQuery` has no `run_node_type.chaos` branch;
- the Chaos interaction resolves successfully through the shared action-aware command boundary;
- if random outcomes are used, deterministic tests force each supported outcome and prove selection is server-owned;
- only the finalized selected outcome is applied;
- node completion/direct-successor unlocking/frontier retention are correct;
- invalid/mismatched/locked/completed/foreign/terminal requests do not mutate state;
- exact-key replay does not reroll/reapply, increment revision twice, or alter frontier twice;
- reload/re-query reproduces finalized state;
- server-private outcome/effect/tuning data is absent from browser-facing projections;
- Rest, Hazard, and Shrine regressions remain green, including Hazard random-target receipt stability and Shrine choice/idempotency behavior;
- Combat/Loot/Boss/Exit, Farm completion, Mountains completion, economy/material acquisition, reward boundaries, and active-run Warband restrictions remain green;
- frontend generic interaction tests prove the Chaos interaction can render/submit without family-specific client logic.

Run applicable gates from `agent/QUALITY_GATES.md`.

#### Scope exclusions

Do not add:
- production Chaos content to Farm or Mountains;
- production Shrine content;
- Swamp production content/topology or Frog Kin;
- run-scoped modifiers;
- contextual consumable mechanics;
- multi-step encounter persistence;
- direct currency/item/unit/die/XP/unlock grants outside the canonical reward system;
- Milestone 11+ functionality;
- final visual redesign.

#### Completion evidence

Report:
- implementation SHA;
- Chaos authored family/fixture representation;
- immediate Chaos outcome semantics and why they do not require Package 6/7 architecture;
- server-side random selection/finalization mechanism if used;
- proof the generic read/client interaction path remains family agnostic;
- authoritative lifecycle evidence for every supported test outcome;
- invalid/idempotent/reload/frontier evidence;
- secrecy evidence;
- Farm/Mountains no-Chaos regression evidence;
- Rest/Hazard/Shrine and broader run regressions;
- focused/full quality-gate results;
- confirmation no production Chaos/Shrine, Swamp, modifier, consumable, multi-step, reward bypass, or Milestone 11+ scope was introduced.

Leave Package 5 **In Progress** for architectural review. Do not promote Package 6 yourself.

#### Implementation evidence (review candidate)

- Added canonical `run_node_type.chaos` family metadata and a validated test-only `encounter.test_chaos` with one `accept` action. Its server-private `random_participating_hp` table has two weighted, stable-ID immediate outcomes: restore all participating units to maximum HP (`mend`) or damage each by up to 2 HP (`wound`). The branching test generation proves interaction projection and frontier behavior. Farm and Mountains production generation remain five and nine nodes with no Chaos encounter; no production Shrine encounter was added.
- `ChaosNodeResolutionHandler` validates the persisted encounter, region, family, action, outcome table, participation, ownership, and HP before resolving. Production selection uses `random_int(1, total_weight)`; integration tests inject a deterministic roll. The shared command atomically persists only the selected HP transition, completes the node, unlocks direct successors while retaining another available route, increments revision once, and records `outcome_id` plus exact HP transitions in the `chaos` receipt. Exact-key replay returns that receipt without invoking the selector again. No wallet or reward grant is applied.
- `CurrentRunQuery` has no Chaos node-type branch. The available test encounter projects only title/body/prompt and action ID/label; outcome IDs, weights, effects, and the encounter catalog remain private. RunScene now accepts authored action responses through their encounter/action identity rather than a family list, then re-queries authoritative run HP. The strict Chaos response parser accepts only the finalized outcome ID and HP transitions.
- Focused verification: Chaos content 2 tests/21 assertions; Chaos lifecycle 2/85; Shrine 2/99; Mountains/Hazard 5/158; Farm Rest 20/177; frontend contract/RunScene 42 passed. Full `npm run verify:package`: PASS, backend 424 tests/2364 assertions, frontend 551 tests, content validation, production build, bundle budget, context/docs, and diff checks. Production client content revision: `9139c5f2865d1b1cded64e98fbf41a7549580f623aa2b8662f60bb034fcabcf7`. Broader Combat/Loot/Boss/Exit, run completion, economy/material, reward, and active-run Warband regressions remain green in the full suite.
- No production Chaos/Shrine encounter, Swamp, modifier, consumable, multi-step, reward bypass, or Milestone 11+ functionality was introduced. Package 5 remains In Progress for review.
