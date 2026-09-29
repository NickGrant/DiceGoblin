# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 6 - Permanent-progression integrated verification and technical closure

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 8 Package 5 - Phaser Academy + unit-promotion surfaces and Camp/Warband integration is approved at `f9d077b840376a9c80a7011cc1a4a5e2a7ae195c`.

Package 5 architectural review approved:
- lazy Academy and per-unit promotion-option caches;
- shared `bootstrap.player.raw_chaos` authority;
- retained idempotency attempts for Academy upgrades and promotion;
- Academy -> Shop invalidation without fabricated availability;
- same-unit promotion reconciliation into roster/detail/active-squad summaries;
- Camp -> Academy and Warband -> Unit Configuration -> Unit Promotion navigation;
- responsive/debug capture support;
- retirement of the superseded unrouted Angular Academy page/service.

The user confirmed Package 5 verification passed.

Package 6 closes Milestone 8 technically. Do not add new permanent-progression mechanics.

#### Purpose

Prove the complete permanent-progression slice works as one coherent production system across:

- authored content;
- MySQL persistence;
- Raw Chaos acquisition/spend;
- Academy reads/upgrades;
- unit-type research -> Shop acquisition;
- Energy-cap upgrades;
- die-size capability -> Shop acquisition;
- unit XP/level eligibility;
- unit promotion/history/permanent ability ownership;
- active-run participation locks;
- Phaser Academy/promotion flows;
- GameStore invalidation/reconciliation;
- reload/re-entry persistence.

Fix only narrow defects found by this integrated proof.

#### Production-content integration

Add/extend integration coverage using the real repository content registry rather than temporary replacement catalogs wherever possible.

Prove the canonical production catalog contains and coherently links:

- all Milestone 8 Academy upgrades;
- capability unlocks;
- unit-type research unlocks;
- Academy event/reward definitions;
- all 20 Goblin promotion edges;
- canonical d4/d6/d8/d10/d12/d20 Cardboard Shop offers;
- target unit types and target abilities.

Do not weaken validators or create test-only production content.

#### Integrated lifecycle proof

Create one or more integration scenarios that exercise the real boundaries in sequence.

At minimum prove:

1. **Raw Chaos source**
   - ordinary dice salvage produces Raw Chaos through the accepted Milestone 7 lifecycle;
   - resulting wallet/revision are authoritative and persist.

2. **Academy unit-type research**
   - before research, the corresponding T1 Goblin Shop offer is unavailable;
   - Academy read reports the authored upgrade;
   - idempotent Academy upgrade spends Raw Chaos once and grants the permanent unlock;
   - after research, Shop read exposes the same canonical unit offer;
   - ordinary Teeth purchase creates one persistent unit through the shared creation path;
   - reload preserves unlock, wallet, and acquired unit.

3. **Energy capacity**
   - buy the 75-cap upgrade through the real Academy command;
   - immediate result does not fabricate retroactive Energy;
   - bootstrap/restore/run-start all agree on the new normal maximum;
   - reload preserves the capability through unlock ownership;
   - then prove the prerequisite chain to 100.

4. **Die-size progression**
   - d10/d12/d20 canonical offers remain unavailable before their required capability;
   - buy capability upgrades through their real prerequisite chain;
   - each Shop boundary changes at the correct threshold;
   - purchase of the newly eligible canonical die succeeds;
   - reload preserves capability and die ownership;
   - no purchase -> sell positive-Teeth arbitrage exists for any canonical Cardboard die.

5. **Unit promotion**
   - use one real persistent Goblin unit at the exact authored required level;
   - read promotion options;
   - promote tier 1 -> one tier-2 branch;
   - prove ID/name/kin/level/XP/loadout/dice bindings persist;
   - prove one history row and permanent target ability delta;
   - read now exposes exactly the authored tier-2 -> tier-3 edge;
   - promote to tier 3;
   - prove branch abilities remain permanently owned;
   - prove terminal options are empty;
   - reload and Unit Detail reproduce the exact persisted type/history/abilities.

6. **Idempotency**
   - replay at least one Academy spend and one promotion spend with the original key/request;
   - no duplicate wallet debit, unlock, history, ability grant, revision, or receipt;
   - same key + changed semantic request still conflicts.

7. **Active-run safety**
   - participating unit promotion is rejected atomically;
   - non-participating reserve unit remains promotable during another active run;
   - starting a run and promotion continue to serialize through the accepted player-state lock order.

#### Run/progression cache regression

Explicitly exercise progression after real run activity.

A unit may have a previously loaded Unit Detail/promotion-options cache, then gain XP/level through run/battle progression before returning to GameScene.

Prove that after:
- normal run completion/exit; and
- run abandonment after any already-persisted progression,

the next Unit Configuration / Unit Promotion flow does not become permanently stuck behind a false local integrity contradiction.

Accepted outcomes:
- affected Unit Detail/options are proactively marked stale and re-read; or
- the first disagreement safely marks stale/error and the standard retry path deterministically recovers.

A normal supported player flow must not require deleting local state or issuing a second progression mutation.

If this test exposes stale-detail invalidation missing from an existing run reconciliation path, make the narrow cache-invalidation correction in Package 6.

#### GameStore cross-domain closure

Add focused tests proving:

**Academy**
- Academy remains lazy at normal startup;
- read wallet/revision equality with bootstrap;
- upgrade result updates shared Raw Chaos/revision/unlock/Energy;
- loaded Academy becomes stale then refreshes;
- loaded Shop becomes stale;
- later Academy/Shop reads use server state, not fabricated prerequisite/availability changes.

**Promotion**
- promotion-options remain lazy per unit;
- Raw Chaos is always rendered from shared bootstrap state;
- successful promotion updates roster, Unit Detail, active-squad summary, shared wallet/revision;
- only the promoted unit's options are invalidated by promotion;
- run lifecycle invalidates lock-sensitive promotion caches;
- local reconciliation failure preserves committed wallet/revision and leaves affected slices recoverable.

**Cross-surface**
- Academy spend is visible when Unit Promotion is opened later;
- promotion spend is visible when Academy is opened later;
- neither screen retains a second wallet snapshot;
- reload bootstrap can replace all lazy progression caches cleanly.

#### Phaser integrated flow proof

Add/extend focused runtime integration tests for:

```text
Camp
 -> Academy
 -> upgrade
 -> Camp
 -> Shop
 -> Warband
 -> Unit Configuration
 -> Unit Promotion
 -> Unit Configuration
 -> Warband
 -> Camp
```

Prove:
- navigation history/back/Escape remains coherent;
- settled mutations release navigation;
- ambiguous mutations retain exact attempts and block leaving only until resolved;
- definitive rejections release navigation;
- no duplicate screen-local wallet state;
- returning screens render reconciled state without global profile refresh.

Do not redesign screen visuals.

#### Deterministic capture closure

Run and retain deterministic proof for representative permanent-progression surfaces at the accepted viewport matrix:

- Compact landscape;
- 1600x900 reference;
- Wide landscape;
- safe-inset landscape;
- portrait mobile rotate gate.

At minimum inspect:
- Academy default catalog;
- Academy owned/locked/unaffordable states;
- Unit Promotion two-choice state;
- below-level state;
- active-run locked state;
- terminal no-options state.

Fail the package for clipped controls, inaccessible actions, safe-inset violations, or critical status/wallet text outside bounds.

Capture fixtures remain debug-only and may not alter normal runtime authority.

#### Prototype/runtime closure

Verify:
- Angular Academy page/service are absent and no imports/routes reference them;
- live gameplay remains routed only through `/game`;
- no new dependency on prototype `AcademyService` or old `PromotionService`;
- no SQL-authored Academy/promotion catalog was introduced;
- no duplicate `energy_max`, max-die-size, Academy ownership, tier, or capstone state exists;
- Wrong Machine/kin progression remains untouched for Milestone 9.

Do not perform broad prototype deletion beyond clearly dead Milestone 8 frontend/runtime artifacts found by this verification.

#### Regression requirements

Keep green:
- Milestone 7 Shop/Supplies/dice lifecycle;
- run start/abandon/current-run reconciliation;
- battle return/reward progression;
- Warband unit detail/rename/loadout;
- unit purchase;
- Energy restoration;
- all Academy Package 1-2 contracts;
- all promotion Package 3-4 contracts;
- startup/content revision handling.

No accepted architecture may be relaxed merely to make integrated tests pass.

#### Verification

Run:
- `npm run verify:package`;
- focused production-content permanent-progression integration tests;
- focused Academy + Shop lifecycle tests;
- focused Energy capability tests;
- focused die-size progression + valuation integrity tests;
- focused promotion lifecycle/idempotency/history/active-run tests;
- focused GameStore progression reconciliation/invalidation tests;
- focused Phaser progression navigation/screen tests;
- deterministic progression capture matrix;
- DB provision/reset;
- full supported Docker backend suite;
- full frontend suite;
- production frontend build;
- bundle/content/docs/diff gates.

Report exact:
- focused backend tests/assertions;
- full backend tests/assertions/skipped;
- focused frontend tests;
- full frontend tests;
- production content revision;
- deterministic capture results.

#### Closure evidence

At completion, add a concise Package 6 closure section to this issue recording:
- implementation SHA;
- exact verification counts;
- production content revision;
- integrated lifecycle scenarios covered;
- capture matrix result;
- any narrow corrections made.

Leave Package 6 **In Progress** for architectural review.

Do not promote Package 7 or Milestone 9 yourself.

#### Out of scope

- new Academy upgrades;
- new promotion paths;
- balance redesign;
- new currencies;
- automatic ability equipping;
- capstone-specific systems;
- Wrong Machine/kin reconstruction;
- final game-wide visual overhaul;
- Milestone 9 work.
