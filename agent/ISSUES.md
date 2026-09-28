# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 2 - Idempotent Raw Chaos Academy upgrade transaction + first derived-capability/Shop consequences

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 8 Package 1 - Authored Academy upgrades + permanent capability foundation + read contract is approved at `0f3069f8aa0d81ff96d7450a006c51230be465a9`.

Package 1 closure evidence:
- `npm run verify:package`: PASS;
- DB provision/reset: PASS;
- production frontend build/content/docs/bundle/diff gates: PASS;
- full frontend: **522 tests PASS**;
- focused backend Academy/capability/Energy/Shop coverage: **117 tests / 716 assertions PASS**;
- focused frontend contracts: **34 tests PASS**;
- the complete supported backend suite was confirmed by the user as run successfully; an earlier reduced test-count report was a reporting mistake rather than reduced suite execution.

Package 1 established canonical Academy/capability content, derived Energy/max-die-size policy, unlock-aware >d8 Shop eligibility, and the strict read-only `GET /api/v1/academy` contract.

#### Problem

Authored Academy upgrades cannot yet be purchased with Raw Chaos, and higher die offers need their capability-gated Shop entries and safe base prices.

#### Purpose

Make authored Academy upgrades executable as one authoritative Raw Chaos transaction.

Package 2 owns:
- `POST /api/v1/academy/upgrade`;
- idempotent Raw Chaos debit;
- permanent upgrade unlock grant through the accepted finalized reward pipeline;
- Energy-capacity transition semantics at the instant a higher cap is unlocked;
- immediate authoritative consequences for Academy, Shop, bootstrap, Energy, and die eligibility;
- canonical d10/d12/d20 basic-die Shop offers after their capability gates exist;
- a narrow canonical die-price correction that prevents repeatable purchase -> sell Teeth arbitrage before Raw Chaos progression goes live;
- strict frontend mutation contracts/API support, without a Phaser Academy screen.

Do not implement promotion or Academy UI in this package.

#### Academy upgrade reward boundary

Academy permanent unlocks must use the accepted event -> finalized reward -> transactional grant pipeline rather than creating an Academy-only unlock insertion path.

Extend authored `academy_upgrade` with one server-only:

```text
event_id
```

For every Academy upgrade:
- `event_id` references one authored `event`;
- that event references one authored `reward_definition`;
- the reward definition contains exactly one entry;
- probability is exactly 10000 basis points;
- reward type is exactly `unlock`;
- the reward entry's `unlock_id` equals the upgrade's `grant_unlock_id`;
- the event/reward pair may not add currency, XP, another unlock, or any extra grant;
- one Academy event may not be shared across different upgrades.

Keep `grant_unlock_id` on the Academy definition. It remains the concise durable ownership/read-model identity. Content validation must prove that the linked reward pipeline grants that exact unlock.

Use `RewardApplicationService` with:
- source type `academy_upgrade`;
- source ID = stable Academy upgrade ID;
- authoritative post-spend wallet/unlock context.

The Academy command owns spending and revision. Reward application owns finalization/application of the upgrade's unlock and does not increment revision.

Exact idempotent retry must return the stored Academy command receipt without rerolling/reapplying the reward event or requiring current authored content to remain unchanged.

#### Request contract

Implement:

```text
POST /api/v1/academy/upgrade
```

Requirements:
- authenticated user;
- CSRF;
- valid `Idempotency-Key`;
- exact JSON body:

{
  "upgrade_id": "academy_upgrade....",
  "expected_price": {
    "currency_id": "raw_chaos",
    "amount": <positive client-safe integer>
  }
}
```

No additional fields.

Semantic idempotency identity is the complete canonical request: upgrade ID plus expected Raw Chaos price.

Same key + any different semantic request conflicts.

#### Transaction and lock ordering

A new upgrade attempt uses one caller-owned transaction.

Required sequence:

1. parse/normalize request and idempotency key;
2. begin transaction;
3. lock/read `user_state`;
4. look up the idempotency receipt;
5. if receipt exists, validate it against the canonical request, commit the read transaction, and return the stored result;
6. validate current wallet/revision client-safe state;
7. resolve the current authored Academy upgrade and linked event/reward definition;
8. lock/read the user's current unlock IDs;
9. reject if the upgrade's grant unlock is already owned;
10. reject if any prerequisite unlock is missing;
11. require current authored price to equal `expected_price`;
12. require sufficient Raw Chaos;
13. apply the Raw Chaos debit;
14. perform any required Energy-capacity transition described below using the **pre-upgrade** capability state;
15. finalize/apply the Academy upgrade reward event and require the declared unlock to be granted exactly once;
16. increment `player_revision` exactly once;
17. build/finalize the idempotency receipt;
18. commit.

Receipt replay deliberately precedes current authored-content/ownership/prerequisite checks so a successful exact retry remains valid after:
- the upgrade is now owned;
- prerequisite/content definitions change in a later release;
- the Academy price changes later.

No rejected attempt may debit Raw Chaos, grant an unlock, change Energy persistence, increment revision, create/finalize a reward event, or finalize an idempotency receipt.

#### Failure behavior

Use non-disclosing/stable application errors appropriate to the command:

- unknown current upgrade -> `academy_upgrade_not_found`;
- already-owned upgrade -> `academy_upgrade_owned`;
- unmet prerequisite -> `academy_upgrade_unavailable`;
- current price differs from expected -> `academy_upgrade_changed`;
- insufficient Raw Chaos -> `insufficient_raw_chaos`;
- same idempotency key used for another request -> existing idempotency conflict behavior;
- corrupted persisted/reward state -> integrity/server failure, not speculative repair.

A new idempotency key submitted after an already committed upgrade is **not** another purchase; it rejects as already owned.

#### Exact success result

Return exactly:

```text
upgrade_id
spend
  currency_id = raw_chaos
  amount
  balance_before
  balance_after
grant
  unlock_id
energy
  <authoritative Energy view or null>
player_revision
```

Rules:
- `upgrade_id` equals the submitted stable identity;
- spend arithmetic is exact and client-safe;
- `grant.unlock_id` equals the Academy upgrade's declared permanent unlock;
- `energy` is non-null only when the upgrade increases the player's Energy normal maximum;
- when non-null it uses the same exact Energy-view shape already returned by bootstrap/restore;
- `player_revision` is the single resulting revision.

Persisted receipt validation must be request-bound but must not require resolving current authored content on retry.

#### Energy-capacity upgrade transition

Increasing the normal Energy maximum must not retroactively regenerate into capacity that did not exist before the upgrade.

At the command timestamp, when the grant is an `energy_normal_max` capability whose value exceeds the player's pre-upgrade maximum:

1. calculate the player's effective current Energy under the **old** normal maximum;
2. preserve all legitimately earned pre-upgrade regeneration;
3. persist that effective current value;
4. rebase the regeneration anchor so newly created capacity starts from the upgrade instant when the player had reached/exceeded the old cap;
5. if the player was still below the old cap, preserve fractional progress toward the next existing regeneration tick rather than resetting it;
6. do not clamp overcharged Energy;
7. grant the capability unlock;
8. return an Energy view under the **new** derived maximum.

This transition must be persistence-only inside the Academy transaction; it must not increment revision separately. Add/extend a repository boundary if needed so the Academy command still increments `player_revision` exactly once for the whole committed upgrade.

Examples:
- player sat at 50/50 for hours, buys 75-cap upgrade -> remains 50 immediately, with new regeneration toward 75 beginning at purchase time;
- player is 43/50 with partial progress toward the next tick -> materialize earned whole ticks and retain fractional timing, then continue toward 75;
- player is overcharged above the old cap -> preserve current Energy; do not clamp it when the normal cap changes.

#### Immediate permanent consequences

After the same committed upgrade:

**Unit-type research**
- Academy read reports the upgrade owned;
- the matching canonical Goblin Shop offer becomes available immediately;
- the entitlement remains separate from acquiring an individual unit with Teeth.

**Energy capacity**
- bootstrap, run-start spend, and Energy restoration all resolve the new maximum through the shared Package 1 capability policy;
- no duplicate `energy_max` field is persisted.

**Die-size capability**
- Shop catalog availability changes immediately;
- purchase revalidation changes immediately;
- no duplicate `max_die_size` field is persisted.

#### Canonical higher-die offers and economy-integrity correction

Add canonical plain Cardboard Shop offers:

- `shop_offer.cardboard_d10`
- `shop_offer.cardboard_d12`
- `shop_offer.cardboard_d20`

They use the existing `dice_profile.cardboard_plain`.

Their availability is controlled exclusively by the Package 1 max-acquirable-die-size capability policy plus profile compatibility.

Normalize the complete canonical plain Cardboard Shop price curve to the existing deterministic base-value curve:

- d4 -> **12 Teeth**
- d6 -> **18 Teeth**
- d8 -> **28 Teeth**
- d10 -> **34 Teeth**
- d12 -> **42 Teeth**
- d20 -> **60 Teeth**

These are provisional integrity anchors, not final economy balancing.

Reason: the previous 4/6/8 Teeth prices were below the existing deterministic sell awards (6/9/14), permitting repeatable Shop purchase -> sell Teeth profit. Before Raw Chaos becomes a spendable progression currency, canonical basic-die acquisition must not contain an obvious positive-Teeth arbitrage loop.

Required invariant:
- no canonical Shop die may cost less than or equal to its deterministic sell award;
- canonical purchase -> sell must always lose Teeth;
- salvage remains a possible expensive Teeth -> Raw Chaos conversion under the already accepted lifecycle design; its final exchange balance remains deferred.

Do not change the Package 6 valuation formulas in this package.

#### Frontend/runtime mutation contract

Add strict framework-neutral support only; no Academy Phaser surface yet.

Add:
- Academy upgrade request/result types;
- exact success parser;
- Runtime API client mutation method with CSRF + idempotency headers;
- standard error-code propagation through `RuntimeApiError`.

Frontend parser must validate:
- exact envelope and exact result fields;
- request-bound `upgrade_id`;
- spend currency = `raw_chaos`;
- spend amount equals submitted expected amount;
- `balance_before - amount = balance_after`;
- all wallet/revision values client-safe and non-negative;
- grant unlock identity is stable/canonical;
- `energy` is either null or the exact established Energy-view shape.

Do not trust static projected Academy price/capability internals; authoritative mutation facts come from the API.

Do not add a GameStore Academy cache or mutation UI in Package 2 unless a narrow contract test requires a framework-neutral reconciliation helper.

#### Tests

Add focused coverage for at least:

**Authored content/reward linkage**
- every canonical Academy upgrade has one valid event/reward pair;
- event/reward grants exactly the declared unlock at 100%;
- missing/mismatched/shared/extra Academy reward definitions reject;
- category matches grant target:
  - `unit_type` -> unit-type unlock;
  - `energy` -> `energy_normal_max` capability unlock;
  - `dice` -> `max_acquirable_die_size` capability unlock.

**Academy command**
- unit-type research success debits Raw Chaos, grants unlock, increments revision once;
- Energy 75 success;
- Energy 100 unavailable before Energy 75;
- d10/d12/d20 prerequisite chain;
- exact retry returns identical result and does not double-spend/regrant/reroll;
- exact retry survives later current-content/ownership changes;
- same key different upgrade/price conflicts;
- new key after already-owned rejects with no mutation;
- insufficient Raw Chaos atomic reject;
- expected-price mismatch atomic reject;
- missing prerequisite atomic reject;
- injected pre-commit failure rolls back Raw Chaos, unlock, Energy persistence, revision, reward event, and idempotency receipt;
- caller-owned transaction requirements remain enforced by repositories/reward service.

**Energy transition**
- at-old-cap upgrade does not receive retroactive newly-created-cap regeneration;
- below-old-cap upgrade preserves already-earned ticks and fractional progress;
- overcharge is preserved;
- post-upgrade bootstrap/restore/run-start all use the same new maximum.

**Shop consequences**
- canonical d4/d6/d8/d10/d12/d20 prices are exactly 12/18/28/34/42/60;
- no canonical Shop die has purchase price <= deterministic sell value;
- d10/d12/d20 locked before capability;
- owning d10 exposes only d10 among higher sizes;
- owning d12 exposes d10+d12;
- owning d20 exposes all standard sizes;
- exact corresponding purchases succeed after entitlement;
- unit-type Academy unlock immediately exposes its matching T1 Goblin Shop offer.

**Frontend**
- exact request payload;
- CSRF/idempotency headers;
- strict response parser;
- nullable Energy consequence;
- malformed/mismatched spend/grant/revision rejects;
- all six standard die sizes remain valid in Shop contracts.

#### Verification

Run:
- `npm run verify:package`;
- focused Academy command/reward/idempotency tests;
- focused Energy capacity-transition/bootstrap/run-start/restore tests;
- focused Shop catalog/purchase/lifecycle-valuation integrity tests;
- focused frontend Academy mutation/Shop contracts;
- DB provision/reset;
- full supported Docker backend suite;
- full frontend suite;
- production build;
- bundle/content/docs/diff gates.

Report exact focused/full counts when available. Ensure the reported full backend count corresponds to the complete suite rather than a focused subset.

#### Out of scope

- Phaser Academy screen/Camp Academy navigation;
- Academy GameStore cache/reconciliation UI;
- unit promotion graph/options/mutation;
- promotion costs/requirements;
- ability/capstone progression;
- Wrong Machine/kin progression;
- changing dice sell/salvage valuation formulas;
- final Teeth/Raw Chaos exchange tuning;
- final Academy/economy balance;
- final visual overhaul.

#### Completion

Implement only Milestone 8 Package 2. Leave it **In Progress** for architectural review. Do not promote Package 3 yourself.
