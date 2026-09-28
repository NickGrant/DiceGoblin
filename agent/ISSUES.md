# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 5 - Phaser Academy + unit-promotion surfaces and Camp/Warband integration

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 8 Package 4 - Promotion transaction + durable ability/history updates + active-run safety is approved at `a1067fbdf1fc3fe101198f17dbbd21e5ebd0a93d`.

Package 4 established and verified:
- `POST /api/v1/units/:unitId/promote`;
- receipt-first idempotent Raw Chaos spending;
- same-unit type transition;
- durable authored promotion history;
- permanent branch ability ownership;
- zero/nonzero target-ability grants;
- preserved ID/name/kin/level/XP/loadout/dice bindings;
- exact active-run participant locking;
- full authoritative mutation response and strict frontend mutation contract.

The user confirmed Package 4 verification passed.

Package 5 makes the permanent-progression slice player-usable inside the existing persistent Phaser runtime. Do not redesign the whole game UI in this package.

#### Purpose

Add:
- a GameScene-owned Academy destination reachable from Camp;
- lazy Academy read/cache/retry state;
- retained-idempotency Academy upgrade interaction;
- shared-state reconciliation after Academy upgrades;
- a GameScene-owned unit-promotion destination reachable from Unit Configuration;
- lazy per-unit promotion-options read/cache/retry state;
- retained-idempotency promotion confirmation/interaction;
- exact local reconciliation after a committed promotion;
- navigation/reflow/reload-safe behavior;
- deterministic responsive capture states;
- narrow retirement of the superseded unrouted Angular Academy page/service.

No new backend progression mechanics are introduced here.

#### Runtime authority rules

Preserve the existing architecture:

- backend/MySQL remains authoritative;
- `GameStore` is a cache, never a second progression store;
- `bootstrap.player.raw_chaos` is the **single runtime owner** of Raw Chaos;
- Academy and promotion caches must not retain an independently mutable wallet;
- static projected content remains presentation/identity only;
- Academy prices, promotion prices, prerequisites, level requirements, ownership, and availability come from the authoritative APIs;
- affected-domain mutation results update only the state they authoritatively describe;
- when dependent state cannot be derived exactly, mark it stale and re-read rather than fabricating it.

Do not introduce a global profile refresh.

#### Academy GameStore state

Add a lazy Academy cache analogous to Shop/items:

```text
AcademyState
  status = not-loaded | loading | fresh | stale | error
  data
    upgrades[]
  error
```

Do not retain the Academy read's `raw_chaos` or `player_revision` as a second wallet/revision snapshot.

Add:
- `academy` getter;
- `subscribeAcademy`;
- `loadAcademy(api, content, reload = false)`;
- `retryAcademy`;
- reset/clear behavior.

On a successful Academy read:
- require cached bootstrap to exist;
- require read `rawChaos` to equal `bootstrap.player.raw_chaos`;
- require read `playerRevision` to equal `bootstrap.player.player_revision`;
- only then retain the upgrade list as fresh.

If those shared facts disagree, do not partially advance bootstrap from a read that lacks the other player domains. Treat the Academy slice as integrity/error and require authoritative reload/recovery.

Academy must not be fetched during startup merely because the cache exists. It loads when the Academy screen is entered.

#### Academy upgrade reconciliation

Add one GameStore reconciliation path for `AcademyUpgradeResult`.

On a successful committed result:

1. require non-regressing revision;
2. update shared `bootstrap.player.raw_chaos` to `spend.balanceAfter`;
3. update shared `player_revision`;
4. add the granted unlock ID to `bootstrap.progression.unlock_ids`;
5. if the result contains Energy, replace the shared bootstrap Energy view with the returned authoritative view;
6. preserve Teeth, account/session, active squad/run, regions and unrelated state;
7. mark Academy stale when loaded, because dependent prerequisite/owned states are server-authored;
8. mark Shop stale when loaded, because unit-type or die-size unlocks may change offer availability;
9. do not fabricate a dependent Academy chain or Shop availability locally.

The grant unlock must not already exist in cached bootstrap before a new successful upgrade. If it does, treat reconciliation as an integrity contradiction while still preserving the authoritative affected wallet/revision result.

The Academy screen should re-read Academy after successful reconciliation. If that read fails:
- the committed upgrade remains committed;
- shared Raw Chaos/revision/unlock/Energy remain updated;
- Academy shows stale/error recovery UI;
- navigation is no longer blocked by the settled mutation.

#### AcademyScreen

Add a `GameSceneScreen` with key:

```text
academy
```

Use the established Camp/economy visual language; final visual overhaul remains deferred.

Minimum presentation:
- RETURN;
- ACADEMY heading;
- shared Raw Chaos wallet display;
- authored upgrade name;
- description;
- category;
- Raw Chaos price;
- state:
  - OWNED;
  - LOCKED / PREREQUISITE REQUIRED;
  - AVAILABLE;
  - INSUFFICIENT RAW CHAOS.

Affordability displayed by the screen is always recomputed from:
`store.bootstrap.player.raw_chaos >= upgrade.price.amount`.

Do not retain a screen-local wallet.

Support more entries than fit vertically through deterministic paging/scrolling appropriate to existing Phaser conventions. All canonical upgrades must remain reachable at Compact, Standard and Wide layouts.

Read states:
- not-loaded/loading;
- fresh;
- error with retry;
- empty catalog as an explicit safe state.

#### Academy mutation UX

Use `RetainedMutationAttempt<AcademyUpgradePayload, AcademyUpgradeResult>`.

Required behavior:
- selecting another upgrade is blocked while state is submitting or retryable;
- mutation identity includes exact upgrade ID + expected Raw Chaos amount;
- first action presents/uses an explicit upgrade confirmation;
- submitting disables duplicate submission;
- ambiguous network/server outcome retains the exact request + idempotency key;
- RETRY uses the exact retained attempt;
- Back/Escape is blocked while submitting or retryable;
- definitive rejection releases the attempt and navigation;
- success reconciles GameStore, then refreshes Academy;
- committed-but-local-reconciliation failure displays reload/recovery guidance without resubmitting under a new key.

The action is enabled only when:
- upgrade not owned;
- server says available;
- shared Raw Chaos can afford the authoritative returned price;
- no mutation is in flight/uncertain.

#### Camp integration

Add Academy as a normal `GameScene` destination, not a Phaser scene.

Update:
- `GameScreenKey`;
- navigator/back handling;
- GameScene screen factory/activation;
- Escape behavior;
- debug screen identity;
- Camp button layout.

Camp now has:
- WARBAND;
- SHOP;
- SUPPLIES;
- ACADEMY.

The four destinations must fit cleanly at Compact/Standard/Wide and safe-inset layouts. A two-row layout at constrained widths is acceptable; do not shrink controls below practical interaction size merely to preserve one row.

Returning from Academy recreates Camp from current shared player state so Raw Chaos/Energy changes are visible.

#### Promotion-options GameStore state

Add lazy per-unit promotion-options state:

```text
Map<unitId, UnitPromotionOptionsState>
  status = not-loaded | loading | fresh | stale | error
  data = UnitPromotionOptionsResult | null
  error
```

Add:
- getter by unit ID;
- subscription suitable for the active unit-promotion screen;
- load/retry methods;
- clear behavior.

On successful read:
- require response unit ID/current unit type to agree with the current cached Unit Detail when that detail is fresh;
- require response Raw Chaos/revision to equal shared bootstrap Raw Chaos/revision;
- require server `configurationLocked` to agree with current runtime active-run participation when that relationship is known;
- then retain the result.

Do not use the cached response's `canAfford` as a long-lived wallet authority. Rendering recomputes affordability from shared Raw Chaos + authoritative option price. The parser still validates that `can_afford` was coherent at response time.

Invalidate/mark stale:
- the promoted unit's options after a successful promotion;
- all loaded promotion-options states when run participation begins/ends in ways that can change `configuration_locked`;
- any loaded unit option whose current unit detail/type/level becomes stale after authoritative progression/run reconciliation.

Do not eagerly reload every unit.

#### Promotion reconciliation

Add `GameStore.reconcileUnitPromotion(result)` or equivalent.

A successful committed promotion authoritatively changes:
- Raw Chaos;
- player revision;
- the promoted unit summary;
- promoted Unit Detail.

Require a fresh reconciliation context when possible:
- bootstrap;
- roster unit summary;
- Unit Detail;
- dice summary needed to validate retained bindings.

Validate against prior cached state:
- same unit ID;
- same display name;
- same kin;
- same level and XP;
- same lifecycle;
- loadout unchanged;
- dice bindings unchanged;
- target type equals result promotion target;
- prior permanent abilities are retained;
- granted abilities are the only newly added ability IDs;
- history equals prior history plus exactly the returned from->to row.

Then:
- update shared Raw Chaos to balanceAfter;
- update player revision;
- replace roster unit type with target type;
- update matching `bootstrap.active_squad.units` summary if that unit is represented there;
- replace Unit Detail with returned detail;
- mark that unit's promotion-options stale;
- leave dice/squad topology unchanged.

If local cache prerequisites are missing, the mutation result is still committed. Preserve the authoritative wallet/revision and mark affected unit/roster/promotion slices stale/error for re-read rather than inventing missing prior state.

Other units' promotion affordability should render from shared Raw Chaos, so they do not require fabricated `canAfford` rewrites.

#### UnitPromotionScreen

Add a `GameSceneScreen` with key:

```text
unit-promotion
```

It is opened for one exact unit ID from Unit Configuration.

On create:
- ensure Unit Detail and required Warband/dice caches are available;
- lazy-load promotion options for the unit;
- show loading/error/retry states safely.

Minimum presentation:
- RETURN TO UNIT;
- goblin display name;
- current authored unit type;
- level and XP / XP-to-next-level;
- shared Raw Chaos;
- active-run lock state when applicable;
- each promotion option:
  - target type name;
  - required level;
  - Raw Chaos cost;
  - newly granted abilities by display name;
  - level requirement state;
  - affordability state;
  - locked state.

Terminal tier-3 unit with no outgoing options:
- show a clear "No further promotions" state;
- do not treat the unit as missing.

#### Promotion mutation UX

Use `RetainedMutationAttempt<UnitPromotionPayload, UnitPromotionResult>`.

Required:
- select one authored option;
- explicit confirmation before spending;
- confirmation names current -> target type and Raw Chaos cost;
- action enabled only when server says available, shared Raw Chaos affords it, and the unit is not configuration-locked;
- attempt identity includes unit ID + promotion ID + expected price;
- ambiguous outcome retains exact request/key;
- Back/Escape and option changes blocked while submitting/retryable;
- exact RETRY reuses the retained attempt;
- definitive rejection releases the attempt;
- success reconciles GameStore and refreshes promotion options for the same unit.

After a tier-1 -> tier-2 success:
- remain on promotion screen;
- display the new current type;
- refresh to the exact tier-2 -> tier-3 option;
- if preserved level already satisfies level 6, it may immediately show eligible.

After a tier-2 -> tier-3 success:
- refresh to the terminal no-options state.

Do not auto-equip newly granted abilities.

#### Unit Configuration / Warband integration

Keep Warband unit rows opening Unit Configuration as today.

Add a **PROMOTION** / **PROGRESSION** action to Unit Configuration that opens `unit-promotion` for the same unit.

Rules:
- opening promotion must be blocked while a Unit Configuration mutation is submitting or in an ambiguous retry state;
- participating active-run units may open promotion to inspect the locked state, but cannot submit;
- returning from promotion must show the reconciled target type/abilities without requiring a whole-page reload;
- existing rename/loadout behavior remains unchanged.

Navigation history:

```text
Camp -> Warband -> Unit Configuration -> Unit Promotion
```

Back/Escape must return one level at a time without losing the Warband tab/history.

#### Runtime navigation/debug support

Update GameScene activation/factories/history for:
- `academy`;
- `unit-promotion`.

Debug capture may enter these screens deterministically without altering normal startup routing.

If debug capture requests unit-promotion, use a deterministic fixture unit identity after Warband fixture state is loaded rather than hardcoding production database IDs.

#### Responsive/layout requirements

Academy and Unit Promotion must support the existing runtime classes:
- Compact 844x390;
- Standard 1600x900;
- Wide 2560x1080;
- safe-inset variants;
- portrait 390x844 remains behind the rotate-device gate.

No critical text/action may render outside safe bounds or underneath navigation/action controls.

Use existing reusable layout helpers where sensible; do not fork a second viewport system.

#### Deterministic capture proof

Extend `scripts/capture-scene.mjs` and debug fixture support for deterministic:
- Academy catalog;
- Academy owned/locked/available/unaffordable states;
- Academy retained/retryable mutation presentation if capture tooling supports mutation-state injection cleanly;
- Unit Promotion with two tier-2 choices;
- Unit Promotion active-run locked state;
- Unit Promotion terminal no-options state.

Capture at least the representative progression screens at:
- Compact;
- Standard;
- Wide;
- safe inset;
- portrait gate.

Review for clipping, overlap, unreadable text, inaccessible actions, and incorrect wallet/status presentation.

Do not use deterministic fixtures as substitutes for the authoritative API contracts in normal runtime.

#### Narrow Angular Academy retirement

Once the Phaser Academy route is live and tests pass, remove the isolated prototype Angular Academy gameplay UI:
- `frontend/src/app/pages/academy-page/**`;
- `frontend/src/app/core/services/academy/**`;
- their isolated tests/imports if no live platform dependency remains.

The current Angular router already routes gameplay through `/game`; do not disturb public/auth/account shell behavior.

Do **not** broadly delete backend prototype `AcademyService` or unrelated prototype progression code in this package. Backend prototype retirement remains evidence-driven/final-hardening work.

Update the prototype disposition document if required to record the frontend Academy retirement.

#### Tests

Add focused coverage for at least:

**Academy store**
- remains not-loaded before screen entry;
- read wallet/revision must match shared bootstrap;
- load/retry/error;
- upgrade reconciliation updates shared Raw Chaos/revision/unlock;
- Energy upgrade updates shared Energy;
- Academy and Shop invalidation;
- no duplicate wallet snapshot;
- reconciliation contradiction preserves committed affected state and marks recovery slices stale/error.

**Academy screen**
- all canonical upgrades reachable;
- owned/locked/available/unaffordable labels;
- shared wallet changes re-render affordability;
- exact retained attempt identity/key;
- ambiguous retry;
- definitive rejection releases navigation;
- success refreshes Academy;
- Back/Escape blocked only while uncertain;
- responsive layouts.

**Promotion store**
- lazy per-unit load;
- shared wallet/revision agreement;
- active-run lock agreement;
- promotion reconciliation preserves ID/name/kin/level/XP/loadout/bindings;
- roster + active-squad summary update;
- permanent ability delta/history update;
- options stale after success;
- run lifecycle invalidates lock-sensitive cached options.

**Promotion screen**
- level/XP/current/target presentation;
- two branch choices;
- insufficient Raw Chaos;
- below-level requirement;
- active-run locked;
- zero/new ability lists;
- retained exact retry;
- success refresh to next tier;
- tier-3 no-options state;
- back/navigation semantics.

**Integration**
- Camp -> Academy -> upgrade -> Camp;
- Academy unit-type unlock -> Shop stale/reload -> matching offer available;
- Academy die capability -> Shop reload -> correct higher die available;
- Academy Energy cap -> Camp/Supplies shared Energy maximum;
- Warband -> Unit Configuration -> Promotion -> promote -> Unit Configuration;
- promotion Raw Chaos spend reflected in Academy wallet when opened later;
- normal reload reproduces server progression state.

**Regression**
- Shop/Supplies retained mutation navigation behavior remains green;
- Warband dice lifecycle retained mutation behavior remains green;
- rename/loadout remains green;
- active run routes still start in RunScene;
- no eager Academy/promotion fetch during normal startup.

#### Verification

Run:
- `npm run verify:package`;
- focused GameStore Academy/promotion reconciliation tests;
- focused AcademyScreen/UnitPromotionScreen/navigation tests;
- focused Camp/Warband/Unit Configuration regressions;
- focused economy/progression runtime integration tests;
- deterministic progression captures;
- DB provision/reset;
- full supported Docker backend suite;
- full frontend suite;
- production frontend build;
- bundle/content/docs/diff gates.

Report exact focused/full counts where available.

#### Out of scope

- new backend progression mechanics;
- changing Academy or promotion prices/graph;
- final economy/progression balancing;
- automatic ability equipping;
- capstone-specific UI/state;
- Wrong Machine/kin progression;
- final game-wide visual overhaul;
- broad backend prototype deletion.

#### Completion

Implement only Milestone 8 Package 5. Leave it **In Progress** for architectural review. Do not promote Package 6 yourself.
