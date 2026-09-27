# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 5 - Contextual consumables: Energy restore + active-run unit healing

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 4 - Unlock-aware base-unit purchase + shared unit creation is approved at `b323b170f200159c047e8f22195cc98e7bfbcf27`.

Package 4 closure evidence:
- DB provision/reset: PASS;
- authored content/unlock tests: 18 tests / 40 assertions;
- Shop availability tests: 7 tests / 26 assertions;
- MySQL purchase/run-safety tests: 15 tests / 116 assertions;
- focused frontend contracts: 21 tests;
- full frontend: **497 tests PASS**;
- production frontend build and bundle check: PASS;
- Docker content validation: PASS at revision `7c3fe94d7568a11955c0e0400a1d2316fad69082a622a0b1a044bda97f49e79f`;
- full Docker backend: **853 tests / 3,505 assertions / 268 skipped**;
- docs lint and `git diff --check`: PASS.

Package 4 established generic authored unit-type unlock entitlements, unlock-aware Shop availability, tier-1 Basic Goblin unit purchase through the existing idempotent Shop transaction, a reusable normal-unit creation boundary, and the valid owned-abilities/empty-loadout state for newly purchased units.

There are no GitHub Actions/status checks attached to the implementation commit; approval is based on the supplied verification results plus architectural review of the pushed diff.

#### Package 5 purpose

Implement the first real consumable-use mutations while preserving the accepted rule that consumables are **contextual commands**, not an arbitrary generic `use item` executor.

This package proves two explicit player intentions:

- `POST /api/v1/energy/restore` consumes an owned Energy-recharge item and applies the accepted Energy overcharge rules;
- `POST /api/v1/runs/:runId/units/:unitId/heal` consumes an owned healing item and restores HP only on the specified participating unit in that owned active run.

Both commands spend durable inventory and therefore require authentication, CSRF, and an `Idempotency-Key`.

Do not add a generic item-effect execution endpoint or scriptable effect dispatcher.

#### Authored consumable effects

Retain the existing strict item model:
- materials have no effect;
- consumables require one validated effect object;
- quantities and effect amounts stay client-safe positive integers.

Keep the existing:

```text
effect:
  type = energy_restore
  amount
```

Extend the consumable effect union with:

```text
effect:
  type = unit_heal
  amount
```

For Package 5, `unit_heal` is usable only through the active-run healing command below. It is not a permanent-unit HP field and does not imply out-of-run healing.

Safe client projection may expose the authored effect `type` and `amount` because they are static gameplay/presentation facts. Preserve strict allowlisting and reference validation.

Production consumable content may remain empty. Use fixture content rather than inventing final balance values merely to exercise this package.

#### Contextual endpoint contracts

Register exactly these accepted vNext commands:

- `POST /api/v1/energy/restore`
- `POST /api/v1/runs/:runId/units/:unitId/heal`

Each request body is exactly:

```text
{
  item_id: item.*
}
```

Reject missing, expanded, malformed, or wrong-namespace bodies.

Both commands require:
- authenticated user;
- valid CSRF token;
- valid `Idempotency-Key`;
- one caller-owned transaction;
- one finalized idempotency receipt for a newly committed use;
- exactly one `player_revision` increment for a newly committed use.

The idempotency request identity must include the complete semantic intention:
- Energy restore: operation + `item_id`;
- unit healing: operation + canonical `runId` + canonical `unitId` + `item_id`.

Same key + different semantic request conflicts.
Exact matching retry returns the finalized original result without consuming another item or applying the effect again.

Receipt lookup occurs before current authored item/effect, inventory quantity, Energy, run, or target-unit eligibility checks so a finalized exact retry remains valid after later state/content changes.

#### Energy restore semantics

Use the accepted Energy model rather than treating Energy as currency.

For a new Energy restore:
1. lock/read the caller's `user_state`;
2. resolve current authored `item_id`;
3. require a stackable owned consumable with `effect.type = energy_restore`;
4. lock the owned item stack;
5. calculate the caller's **effective current Energy at command time** using the existing authoritative Energy calculation and current authored normal maximum/regeneration rate;
6. if effective Energy is already at or above the normal maximum, reject without consuming inventory;
7. otherwise consume exactly one item;
8. add the authored restore amount, allowing the result to exceed the normal maximum;
9. persist coherent Energy state/regen timing;
10. increment revision once, finalize the receipt, and commit.

Accepted overcharge rule:
- below normal maximum -> use is allowed even if the item takes Energy above maximum;
- at normal maximum -> blocked;
- above normal maximum -> blocked.

Do not clamp a valid recharge to the normal maximum.

Regeneration timing must remain coherent:
- materialize whole elapsed regeneration ticks before applying the item;
- preserve fractional progress if the post-use Energy remains below normal maximum;
- if the post-use Energy reaches/exceeds normal maximum, reset the persisted regeneration anchor to command time so time spent capped cannot later become retroactive regeneration;
- ordinary regeneration remains paused while persisted/effective Energy is at or above normal maximum.

Reject any client-safe integer overflow atomically.

Use a stable business error such as:
- `energy_restore_unavailable` (409) when Energy is already at/above the normal maximum;
- `consumable_unavailable` (409) when the caller lacks the required owned item quantity.

Authored-content/type mismatch is not client-selectable polymorphism: an item that is not an `energy_restore` consumable is invalid for this endpoint and must not be consumed.

#### Energy restore result

Return and persist an exact result sufficient for authoritative client reconciliation:

```text
item_id
quantity_consumed = 1
owned_quantity_after
energy
player_revision
```

`energy` uses the existing canonical Energy view:
- current;
- normal_max;
- regeneration_per_hour;
- regeneration_interval_seconds;
- last_regeneration_at;
- next_regeneration_at;
- fully_regenerated_at.

The frontend parser must reject malformed Energy timing/state, unsafe numbers, wrong item identity, or incoherent inventory quantities.

#### Active-run unit healing semantics

Healing changes only `run_unit_state.current_hp`; it does not mutate permanent unit level/type/stats.

For a new heal request:
1. validate canonical positive `runId` and `unitId` path IDs;
2. lock/read the caller's `user_state`;
3. resolve the owned run by exact ID for update;
4. require that run to belong to the caller and still be `active`;
5. require the target unit to be an exact participant in that run and to belong to the caller;
6. lock the target run-unit HP state;
7. resolve the current authored unit type + persisted level and calculate current maximum HP through the existing canonical stat resolver;
8. require persisted HP to be within `0..max_hp`;
9. require an owned stackable consumable whose authored effect is `unit_heal`;
10. if the unit is already at maximum HP, reject without consuming inventory;
11. consume exactly one item;
12. set `hp_after = min(max_hp, hp_before + authored amount)`;
13. increment revision once, finalize the receipt, and commit.

A 0-HP participant may be healed if the run itself is still active. A terminal/failed/abandoned/completed run cannot be healed.

Do not:
- heal a unit that is not participating in the exact run;
- heal another user's unit or run;
- over-heal above canonical maximum HP;
- mutate permanent unit state;
- complete/advance a node;
- rewrite battle playback;
- create a new run HP model.

Use a stable business error such as:
- `run_heal_unavailable` (409) when the owned run is not active or the target is already full;
- `consumable_unavailable` (409) for insufficient owned item quantity;
- ownership-safe 404 behavior for a missing/foreign run or nonparticipating/foreign target, consistent with existing run security patterns.

#### Active-run healing result

Return and persist an exact result:

```text
item_id
quantity_consumed = 1
owned_quantity_after
run_id
unit:
  unit_id
  hp_before
  hp_after
  max_hp
player_revision
```

Required invariants:
- response run/unit identities equal the submitted path identities;
- `0 <= hp_before < hp_after <= max_hp`;
- `quantity_consumed = 1`;
- `owned_quantity_after` is client-safe and non-negative.

The frontend parser must bind the result to the exact submitted run/unit/item intention rather than accepting any coherent-looking heal receipt.

#### Persistence/application boundaries

Reuse existing vNext boundaries:
- `UserItemRepository::decrement` for owned stack consumption;
- `PlayerStateRepository` for Energy persistence/revision;
- existing run persistence/resolution repository patterns for locked participating-unit HP writes;
- `BaseLevelStatResolver` for canonical maximum HP;
- shared idempotency infrastructure.

Any new repository methods remain persistence-only and require a caller-owned transaction for mutation.

Prefer a small deterministic Energy restoration calculator/service beside the existing Energy calculators if needed; controllers must not implement Energy math.

The application commands own transaction, authorization-context checks, item-effect eligibility, mutation order, revision, and finalized receipt.

Do not add new inventory, consumable, Energy, or run-HP tables.

#### Frontend/runtime contracts

Extend the strict client item-effect union with `unit_heal`.

Add strict mutation contracts/API-client methods for both contextual endpoints, but do not build the Phaser Shop/Inventory UX yet; Package 7 owns those surfaces.

Preserve:
- exact envelope/field validation;
- client-safe integer checks;
- canonical positive ID strings;
- projected authored item resolution;
- request-bound response identity;
- existing current-run HP and Energy contracts.

Where practical, share canonical Energy parsing rather than allowing subtly different bootstrap/run/consumable Energy validators.

No client code decides eligibility. The server is authoritative.

#### Transaction and failure behavior

For both commands:
- no item decrement if preconditions fail;
- no partial Energy/HP mutation;
- no revision increment on rejected use;
- no finalized receipt on rejected use;
- failure after effect mutation but before receipt/commit rolls back item, effect, revision, and receipt;
- exact retry after success performs no second mutation.

The finalized result is the replay boundary, even if:
- item quantity later changes;
- authored item content later changes;
- Energy later changes;
- the run later terminates;
- target HP later changes.

#### Tests

Authored content/client projection:
- existing `energy_restore` item remains valid and projects strictly;
- valid `unit_heal` item loads/projects;
- unsupported effect type rejects;
- zero/negative/unsafe effect amount rejects;
- materials with effects still reject;
- consumables without effects still reject;
- strict frontend item effect union accepts only the two supported effects.

Energy restore:
- below-max use consumes one item and restores exact amount;
- restore may overcharge above normal maximum;
- exactly-at-max and already-over-max uses reject with no mutation;
- elapsed whole regen ticks are materialized before eligibility/effect;
- fractional regen progress is preserved when still below cap;
- reaching/overcharging cap resets the regen anchor correctly;
- overflow rejects atomically;
- wrong-effect item rejects without consumption;
- missing/insufficient item rejects without mutation;
- exact retry does not consume/restore twice and survives later content/state changes;
- same key with another item conflicts;
- revision increments exactly once on committed use.

Active-run healing:
- owned active-run participant below max heals by exact amount capped at max;
- 0-HP participant in an active run can be healed;
- full-HP target rejects without item consumption;
- target cannot exceed max HP;
- wrong-effect/missing item rejects without HP mutation;
- nonparticipant/foreign unit cannot be healed;
- foreign/missing run does not disclose or mutate state;
- completed/failed/abandoned run cannot be healed;
- exact retry does not consume/heal twice and survives later run/HP/content changes;
- same key with different run/unit/item conflicts;
- revision increments exactly once on committed use.

Rollback/security:
- injected failure before commit rolls back item + Energy/HP + revision + receipt;
- caller transaction requirements remain enforced;
- no new persistence tables;
- existing Shop, run-node Rest, combat HP, run-start Energy, inventory query, and purchase tests remain green.

Frontend:
- strict Energy-restore receipt parsing;
- strict heal receipt parsing bound to submitted run/unit/item;
- expanded/malformed/wrong-identity/unsafe results reject;
- API client sends auth credentials, CSRF, idempotency, exact route/body;
- existing runtime contracts remain green.

#### Verification

Run:
- `npm run verify:package`;
- focused authored item/effect validation tests;
- focused Energy calculator/restore command tests;
- focused active-run heal command tests;
- focused frontend consumable/runtime API contracts;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- focused MySQL inventory/Energy/run-HP tests;
- `npm run test:backend:docker`.

Report exact tests/assertions/skipped counts where available.

#### Out of scope

- generic `use item` endpoint or arbitrary effect dispatcher;
- final consumable balance/catalog population;
- Shop/Inventory Phaser UI;
- healing permanent unit state outside a run;
- healing enemies;
- run-node advancement as a side effect of item use;
- combat simulation changes;
- Rest-node behavior changes;
- Energy maximum progression/Academy upgrades;
- dice sale/salvage (Package 6);
- Milestone 8+ progression systems.

#### Completion

Implement only Milestone 7 Package 5. Leave it **In Progress** for architectural review. Do not promote Package 6 yourself.
