# Active Execution Issue

## Milestone 7 - Economy and Inventory

### Milestone 7 Package 6 - Dice sell/salvage lifecycle + Teeth/Raw Chaos outputs and active-run/equipment safety

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 Package 5 - Contextual consumables is approved at `a6e21912e686984c7d82ce3cb888bd803259b64c`.

Package 5 closure evidence:
- Energy calculator: 6 tests / 10 assertions;
- consumable integration: 14 tests / 68 assertions;
- full frontend: **502 tests PASS**;
- full Docker backend: **875 tests / 3,585 assertions / 268 skipped**;
- DB provision/reset: PASS;
- Docker content validation, production build, bundle check, docs lint, PHP syntax, and `git diff --check`: PASS.

Package 5 established idempotent contextual Energy restore and active-run unit healing, including UTC-correct Energy regeneration semantics.

#### Package 6 purpose

Implement the accepted authoritative die lifecycle commands:

- `POST /api/v1/dice/:diceId/sell`
- `POST /api/v1/dice/:diceId/salvage`

Selling converts one owned active die into Teeth. Salvaging converts one owned active die into Raw Chaos. Both are terminal lifecycle transitions and must preserve equipment/run safety.

Do not revive the prototype dice-definition/affix persistence model or physically delete dice as the normal transition.

#### vNext valuation rule

For this package, retain the existing deterministic prototype size/rarity valuation curves only as behavioral evidence, adapted to the canonical vNext die profile model:

- resolve the owned die's current `size`;
- resolve its authored `dice_profile` and explicit profile `rarity`;
- sell Teeth = the existing `DiceValuationService::calculateSellValue(size, rarity, [])` behavior;
- salvage Raw Chaos = the existing `DiceValuationService::calculateRawChaosSalvageValue(size, rarity, [])` behavior.

Prototype affix rows are not part of vNext and must not contribute. Current vNext aspects have no rarity/value field, so aspects do not change sell/salvage value in Package 6.

This is a deterministic balance policy, not a reason to recreate prototype DB catalogs.

Salvage is **not** gated by the Wrong Machine. Wrong Machine availability/reconstruction belongs to Milestone 9; Package 6 must function independently.

#### Command and idempotency contracts

Both commands require:
- authenticated user;
- valid CSRF token;
- canonical positive `diceId`;
- valid `Idempotency-Key`;
- one caller-owned transaction;
- one finalized idempotency receipt for a new committed transition;
- exactly one `player_revision` increment on a new committed transition.

There is no request body. Semantic idempotency identity is operation + canonical `diceId`.

Receipt lookup must occur before current die lifecycle/content/equipment eligibility so an exact retry remains valid after the die has transitioned and after later authored-content changes.

Same key + different operation or die conflicts.

#### Ownership, lifecycle, and locking

For a new transition:

1. lock/read `user_state`;
2. resolve the exact caller-owned die for update and require `lifecycle_status = active`;
3. resolve and validate its authored profile and size eligibility;
4. lock/check all bindings for the die;
5. reject any currently equipped die;
6. preserve the accepted active-run configuration lock: a die participating in an active run through a participating unit cannot be sold/salvaged;
7. calculate the deterministic output;
8. reject client-safe wallet overflow atomically;
9. transition the die to terminal status `sold` or `salvaged` rather than deleting it;
10. apply the currency credit, increment revision once, finalize receipt, and commit.

An unbound die that is not part of the active run's committed combat configuration may be sold/salvaged even while another run is active.

Missing, foreign, or already-terminal dice use ownership-safe not-found behavior and must not reveal another player's state.

Repository mutation methods remain persistence-only and require a caller-owned transaction. Reuse current player-state currency transitions and active-run/configuration boundaries where practical.

No new dice ownership/economy tables.

#### Results

Sell result is exact:

```text
dice_id
lifecycle_status = sold
teeth_awarded
teeth
player_revision
```

Salvage result is exact:

```text
dice_id
lifecycle_status = salvaged
raw_chaos_awarded
raw_chaos
player_revision
```

All numeric fields are client-safe non-negative integers; awards are positive. Result `dice_id` must equal the submitted path identity.

The active dice collection must naturally stop returning the terminal die because it queries active instances only.

#### Frontend/runtime contracts

Add strict mutation contracts and runtime API-client methods for sell and salvage, but do not build the Phaser inventory/shop UX yet; Package 7 owns those surfaces.

Require:
- canonical positive die IDs;
- exact response envelopes/fields;
- request-bound `dice_id`;
- exact expected lifecycle status per operation;
- positive client-safe awards;
- client-safe resulting balance/revision;
- CSRF + idempotency headers;
- exact accepted routes and no invented request payload.

The server remains authoritative for valuation and eligibility.

#### Failure behavior

For both commands:
- no currency on rejected transition;
- no lifecycle change on rejected transition;
- no revision increment on rejected transition;
- no finalized receipt on rejected transition;
- failure before commit rolls back lifecycle, currency, revision, and receipt;
- exact retry after success returns the original finalized result and performs no second credit/transition.

#### Required tests

Cover at minimum:
- deterministic sell values across representative size/rarity combinations;
- deterministic salvage values across representative size/rarity combinations;
- aspects do not accidentally use removed prototype affix valuation;
- successful sell -> Teeth + `sold` + one revision;
- successful salvage -> Raw Chaos + `salvaged` + one revision;
- terminal die disappears from active collection while record remains;
- equipped die rejects for both operations with no mutation;
- active-run participating/equipped die rejects with no mutation;
- eligible unbound die can transition while another run is active;
- missing/foreign/already-terminal die is ownership-safe;
- malformed/noncanonical IDs reject;
- currency overflow rejects atomically;
- exact retries do not double-credit and survive later die/content state changes;
- same idempotency key with another die or operation conflicts;
- injected pre-commit failure fully rolls back;
- caller transaction requirements remain enforced;
- frontend strict sell/salvage receipt parsing and exact route/header behavior;
- existing dice query/loadout/run-start/Shop/inventory/consumable regressions remain green.

#### Verification

Run the repository package/quality gates plus focused:
- dice valuation;
- dice lifecycle command/persistence;
- equipment/active-run safety;
- frontend dice lifecycle contracts/API;
- DB provision/reset;
- full Docker backend.

Report exact tests/assertions/skipped counts where available.

#### Out of scope

- Phaser Shop/Inventory screens (Package 7);
- Wrong Machine unlock/reconstruction logic;
- Academy/permanent progression;
- new dice acquisition or >d8 eligibility;
- dice cleanup/retention scheduling;
- changing loadout behavior;
- final economy rebalance;
- prototype affix persistence;
- generic asset-sale framework.

#### Completion

Implement only Milestone 7 Package 6. Leave it **In Progress** for architectural review. Do not promote Package 7 yourself.
