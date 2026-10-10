# Active Execution Issue

## Milestone 9 - Kin and Wrong Machine

### Milestone 9 Package 4 - Kin/Wrong Machine integrated verification/technical closure

**Status:** In Progress
**Priority:** High

#### Problem

The accepted Kin and Wrong Machine implementation has not yet been proven as one production-composed lifecycle from authored run rewards through persisted first and repeat reconstruction, Phaser reconciliation, and responsive presentation.

#### Accepted baseline

Milestone 9 Package 1 is approved through implementation `2126bb76f65b753bef227f68c048de1738808bf7` plus focused production-drop correction `79e38a5a41d25fd36a97ea630ee2379d0e2f6049`.

Milestone 9 Package 2 is approved at `722aacbb6cab4f9907cfeeacf86326ceda024eeb`.

Milestone 9 Package 3 is approved at `3ca7d2aa512d66b94fce37870de5bcc65f8fd189`.

Packages 1-3 established the complete implementation slice: canonical Pig/Lizard Kin and recipes, generic material acquisition, durable Kin ownership, authoritative Wrong Machine reads, atomic/idempotent first and repeat reconstruction, shared normal unit creation, and the persistent Phaser Wrong Machine screen with unlock-gated Camp navigation and authoritative runtime reconciliation.

#### Purpose

Close Milestone 9 technically by proving the complete production-composed Kin/Wrong Machine lifecycle across authored content, PHP/domain/repository boundaries, MySQL persistence, Phaser runtime/cache reconciliation, reload/resume behavior, responsive presentation, and regression gates.

This package is verification and narrow closure work. Do not add new Kin mechanics, redesign the Wrong Machine, add Frog Kin, or begin Milestone 10 encounter-depth work.

#### Integrated lifecycle proof

Using production composition and a clean MySQL database, prove at minimum the following player lifecycle without test-only bypasses:

1. a player with Wrong Machine access acquires the authored Pig and Lizard reconstruction materials through the accepted Farm/Mountains reward paths;
2. the authoritative Wrong Machine read reflects current wallet, inventory, prerequisites, eligible unit types, Kin restoration state, and reconstructability;
3. first restoration for Pig and Lizard spends exactly the authored resources, creates exactly one level-1 active unit of the recipe Kin through the shared unit-creation boundary, grants the Kin unlock once, and advances `player_revision` once;
4. reloading/re-querying from persisted MySQL state shows the Kin restored, resources consumed, created unit owned, and recipe in repeat mode;
5. repeat reconstruction requires an explicit eligible unit type, spends exactly once, creates exactly one selected-type unit of the recipe Kin, does not replay the Kin grant, and advances revision once;
6. Phaser reconciles successful first and repeat reconstruction without browser reload, while fresh reload produces the same authoritative state;
7. Pig and Lizard traverse the same generic backend and Phaser paths.

#### Persistence, transaction, and retry closure

Re-run and preserve the Package 2 invariants under production composition:
- same-key replay returns the exact finalized receipt with no duplicate spend, unit, unlock, or revision increment;
- same idempotency key with a different canonical request conflicts without mutation;
- stale expected mode/cost/ingredients reject without spending;
- insufficient Raw Chaos or any required ingredient rejects without mutation;
- missing Wrong Machine prerequisite and invalid/locked repeat unit type reject without mutation;
- forced failures at meaningful transaction points roll back wallet, inventory, unlock, unit creation, revision, and idempotency result;
- cross-player resources/unlocks/units cannot satisfy or observe another player's reconstruction;
- active-run unit-ownership safety remains consistent with the accepted shared unit-creation model.

Do not introduce a Wrong-Machine-specific retry/history table or mirrored wallet/inventory authority as part of closure.

#### Client/runtime closure

Verify Package 3 against the real Package 1/2 contracts and production content:
- Wrong Machine remains a `GameScene` screen inside the persistent Phaser runtime;
- Camp entry remains gated by authoritative permanent Wrong Machine access;
- first restoration exposes no client unit-type choice or random prediction;
- repeat reconstruction requires explicit eligible selection;
- unavailable actions are inert;
- pending input cannot double-submit;
- ambiguous retry retains the same mutation identity/key;
- a new intention receives a new key;
- `reconstruction_changed` refreshes and requires reconfirmation rather than silently changing semantics;
- authoritative success reconciles Raw Chaos, inventory, Kin unlocks, `player_revision`, Wrong Machine mode, and Warband visibility;
- loaded caches update only where the accepted cache contract can verify the receipt; otherwise the affected lazy domain becomes stale for authoritative reload;
- no optimistic durable mutation occurs before server success;
- re-entry and full browser reload agree with persisted backend state.

#### Authored-content and security audit

Confirm closure did not weaken the accepted content boundary:
- canonical reconstruction definitions remain server-owned authored JSON;
- browser static projection does not expose server-only reconstruction selection rules or hidden authored mechanics merely to power the screen;
- the Wrong Machine API returns only player-authorized state required for presentation/action;
- no Pig/Lizard-specific spending or creation branches exist in controller/application/runtime code;
- no cross-player state leakage exists through API or client cache reuse.

#### Responsive deterministic capture

Run deterministic visual/capture verification for the Wrong Machine and its Camp entry at:
- Compact landscape `844 x 390` with touch/mobile capabilities;
- Standard `1600 x 900`;
- Wide `2560 x 1080`;
- portrait mobile `390 x 844` using the existing rotate-device gate.

Verify costs, ingredient ownership, recipe status, first/repeat mode, repeat unit-type selection, reconstruction action, error/retry text, and Back navigation remain legible/reachable in supported landscape modes. Portrait must remain blocked by the shared orientation gate without losing runtime state.

Only make narrow presentation corrections required to satisfy these accepted responsive rules. Final visual polish remains deferred.

#### Regression and quality gates

Run the applicable gates from `agent/QUALITY_GATES.md`, including clean provision/reset and the full supported backend/frontend/content/docs suites.

Regression coverage must include accepted Milestone 7/8 behavior affected by shared wallet, inventory, unit ownership, unlocks, revision, Camp navigation, and progression UI. In particular, Shop, Supplies/Inventory, Warband, Academy, run reward/material acquisition, bootstrap, and active-run behavior must remain green.

If closure exposes a defect, fix it narrowly within Milestone 9 and rerun the affected focused and full gates. Do not defer a correctness defect into manual UAT.

#### Completion evidence

Report:
- closure implementation SHA;
- clean MySQL provision/reset result;
- production-composed Pig first-restoration and repeat-reconstruction evidence;
- production-composed Lizard first-restoration and repeat-reconstruction evidence;
- persisted reload proof for wallet, ingredients, Kin unlocks, created units, mode, and `player_revision`;
- idempotency/retry/rollback/cross-player verification evidence;
- proof both Kin families use one generic backend and Phaser path;
- focused and full backend/frontend/content/docs test counts;
- deterministic capture evidence for Compact, Standard, Wide, and portrait gate;
- any narrow closure corrections made;
- confirmation no Frog Kin, Milestone 10 work, new Phaser Scene, Angular gameplay route, mirrored Wrong Machine persistence, or client-authored reconstruction authority was introduced.

Leave Package 4 **In Progress** for architectural review. Do not promote Package 5 yourself.

#### Closure evidence for architectural review

- Clean MySQL: PDO-based `backend/scripts/provision-test-db.php` provisioned isolated `goblin_test`; `backend/scripts/reset-test-db.php` dropped only that database's tables and reapplied `migrations/vnext_baseline.sql`. Focused integration passed on the fresh baseline: `KinWrongMachineLifecycleClosureTest`, 1 test / 79 assertions. The reset command remains guarded to the exact `goblin_test` database.
- Production-composed lifecycle: one account with persisted Wrong Machine and Bruiser access salvaged five dice through `DiceLifecycleCommand` for 20 Raw Chaos, completed two Farm runs and two Mountains runs through `StartRunCommand`/`ResolveRunNodeCommand`, and earned 6 Pig Ears + 2 Mudking Crown Fragments and 6 Kobold Scales + 2 Chief Engineer Lenses through authored victory grants. Combat victory is deterministic at the resolver seam; run, reward, wallet, read, and reconstruction operations use production composition.
- Pig and Lizard each completed first restoration and explicit Bruiser repeat reconstruction from fresh `WrongMachineQuery` reads through the same `ReconstructKinCommand`. Each receipt created one level-1 active unit of the requested Kin, spent 5 Raw Chaos, consumed authored ingredients, and incremented `player_revision` once. First restoration granted the Kin unlock; repeat reported `already_owned`. Fresh MySQL queries after each command showed the wallet, item balances, Kin unlock, unit ownership/type, and repeat mode. Exact-key replay returned the identical receipt with unchanged persisted state. Final wallet and ingredient balances were zero and four new units were owned.
- Existing `ReconstructKinCommandTest` and Wrong Machine controller/read suites cover changed-key conflict, stale intent, resource/prerequisite/eligible-type rejection, rollback checkpoints, cross-player isolation, authentication/CSRF, and active-run unit safety. Package 3 browser contracts/screens cover loaded and unloaded cache reconciliation, no optimistic spend, first/repeat choice rules, retained ambiguous retries, new keys, stale-intent refresh, and re-entry. Canonical recipes remain server-owned JSON; the validated client projection has no reconstruction recipes or selection rules. No family-specific branch was added to command/query/screen code.
- Full gates: `npm run verify:package` PASS; backend 375 tests / 1,921 assertions; frontend 539 tests; content validation, docs lint, context/backlog validation, production frontend build, bundle budget, and diff whitespace all passed. Production content revision: `9b3b0a14d8018319d4f59e16f6b376d70c8430476a671b18c082eb60d5419bd2`.
- Deterministic captures under ignored `artifacts/screenshots/`: Wrong Machine first mode at 844x390 touch, 1600x900, and 2560x1080; compact repeat selection; standard and compact read-error/retryable states; Camp entry at Compact/Standard/Wide; and 390x844 portrait with the shared rotate gate active. Reviewed costs, material counts, mode/status, selection/action, retry text, Back, and entry placement; no layout correction was required.
- Narrow closure corrections: repaired documented test database provision/reset scripts to use the configured `pdo_mysql` extension; added deterministic capture states and browser status markers. No new Kin mechanics, Frog Kin, Milestone 10 work, Phaser Scene, Angular route, mirrored Wrong Machine persistence, or client-authored reconstruction authority was introduced.
