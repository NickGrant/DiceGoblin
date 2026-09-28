# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 3 - Authored unit-promotion graph + progression read contracts

**Status:** In Progress
**Priority:** High

#### Problem

Establish the authored Goblin promotion graph and safe progression read contracts before the promotion mutation is implemented.

#### Accepted baseline

Milestone 8 Package 2 - Idempotent Raw Chaos Academy upgrade transaction + first derived-capability/Shop consequences is approved at `a22617e4e63898932c913e6c1290ed346fca9b55`.

Package 2 architectural review approved:
- receipt-first Academy idempotency;
- one Raw Chaos transaction owner;
- finalized reward-pipeline unlock grants;
- Energy-cap transition without retroactive regeneration;
- immediate unit-type/Energy/>d8 Shop consequences;
- canonical d10/d12/d20 Shop acquisition;
- canonical Cardboard die prices 12/18/28/34/42/60, removing purchase -> sell Teeth arbitrage.

The user confirmed the required Package 2 verification suite and gates ran successfully.

Package 3 defines the vNext promotion model and read boundaries only. Do not implement promotion mutation yet.

#### Re-approved promotion rules

The old prototype three-unit sacrifice model is **not** part of vNext.

Promotion now has these durable rules:

- exactly one owned active unit is the promotion subject;
- no secondary units are consumed or retired;
- the surviving unit keeps the same instance ID, display name, kin, level, and normalized XP;
- promotion changes only the unit's current authored unit type plus durable promotion/ability state in Package 4;
- all previously owned abilities remain permanently owned;
- promotion grants any abilities present on the target unit type that the unit does not already own;
- an ability already owned is not duplicated;
- a promotion is allowed to grant zero new abilities;
- loadout and dice bindings are not implicitly rewritten by merely reading promotion options;
- Package 4 must preserve existing valid loadout/bindings across promotion because old abilities remain owned;
- no capstone-specific persistence or endpoint exists;
- a future capstone is represented as ordinary authored ability ownership;
- only a unit participating in an active run is promotion-locked; a non-participating unit may progress while another squad/run is active.

Level/XP belong to the persistent unit rather than the current class. Promotion does **not** reset either value.

#### Canonical promotion cadence and cost

Author tier-1 -> tier-2 promotions with:

- required unit level: **3**
- Raw Chaos price: **5**

Author tier-2 -> tier-3 promotions with:

- required unit level: **6**
- Raw Chaos price: **10**

Price values are provisional progression anchors, not final balance.

A player who intentionally delays a tier-1 promotion until level 6 may immediately satisfy the level requirement for the next promotion after reaching tier 2. Raw Chaos cost and current authored path still apply.

#### Authored promotion definition

Add canonical definition type:

```text
unit_promotion
```

Each definition contains exactly:

- stable ID;
- `from_unit_type_id`;
- `to_unit_type_id`;
- `required_level`;
- `price`
  - `currency_id = raw_chaos`
  - positive client-safe `amount`.

Rules:

- IDs use `unit_promotion.` namespace;
- from/to reference authored `unit_type` definitions;
- from and to are distinct;
- target tier is exactly source tier + 1;
- only tier 1 -> 2 and tier 2 -> 3 are valid in the Package 3 graph;
- one exact from/to pair may appear only once;
- direct and indirect cycles reject;
- duplicate stable IDs already reject through the global registry;
- no executable scripts/handlers;
- required level is a positive client-safe integer;
- Raw Chaos amount is a positive client-safe integer.

Do not duplicate target ability grants into the promotion definition. The target `unit_type.ability_ids` remains authored ability authority.

#### Canonical Goblin promotion graph

Author exactly these paths.

**Bruiser family**
- `unit_promotion.bruiser.enforcer`: Bruiser -> Enforcer
- `unit_promotion.bruiser.pit_fighter`: Bruiser -> Pit Fighter
- `unit_promotion.enforcer.juggernaut`: Enforcer -> Juggernaut
- `unit_promotion.pit_fighter.juggernaut`: Pit Fighter -> Juggernaut

**Guardian family**
- `unit_promotion.guardian.bulwark`: Guardian -> Bulwark
- `unit_promotion.guardian.shieldbreaker`: Guardian -> Shieldbreaker
- `unit_promotion.bulwark.ironwall`: Bulwark -> Ironwall
- `unit_promotion.shieldbreaker.ironwall`: Shieldbreaker -> Ironwall

**Marksman family**
- `unit_promotion.marksman.deadeye`: Marksman -> Deadeye
- `unit_promotion.marksman.trapper`: Marksman -> Trapper
- `unit_promotion.deadeye.sharpshot`: Deadeye -> Sharpshot
- `unit_promotion.trapper.sharpshot`: Trapper -> Sharpshot

**Bannerbearer family**
- `unit_promotion.bannerbearer.warcaller`: Bannerbearer -> Warcaller
- `unit_promotion.bannerbearer.mascot`: Bannerbearer -> Mascot
- `unit_promotion.warcaller.warchanter`: Warcaller -> Warchanter
- `unit_promotion.mascot.warchanter`: Mascot -> Warchanter

**Saboteur family**
- `unit_promotion.saboteur.trickshot`: Saboteur -> Trickshot
- `unit_promotion.saboteur.plaguehand`: Saboteur -> Plaguehand
- `unit_promotion.trickshot.venomwright`: Trickshot -> Venomwright
- `unit_promotion.plaguehand.venomwright`: Plaguehand -> Venomwright

There are exactly **20** canonical promotion edges.

Tier-3 unit types have no outgoing Package 3 promotion definitions.

The shared tier-3 convergence is intentional. A unit's branch history remains meaningful because previously earned tier-2 abilities remain owned after promotion.

#### Promotion graph policy

Introduce one shared backend promotion/progression policy over ContentRegistry.

At minimum it must provide deterministic behavior for:

- outgoing promotion definitions for a current unit type;
- exact promotion lookup by stable promotion ID;
- target tier validation;
- level requirement evaluation;
- target ability delta:
  - target unit type `ability_ids`
  - minus already owned unit ability IDs
  - canonical deterministic order;
- current XP threshold using the existing `UnitXpResolver::threshold(level)`.

Do not derive promotion paths from naming conventions, slug stems, existing promotion history, unit-type tier alone, or prototype SQL catalogs. The authored `unit_promotion` graph is the path authority.

#### Active-run lock reuse

Do not create a second definition of run participation locking.

Extend/reuse the existing `ActiveRunConfigurationPolicy` so both commands and reads can answer whether one exact owned unit is configuration/progression locked by active-run participation.

Existing assert-style callers must retain their behavior.

Package 3 read queries use the same participation authority to expose lock state. Package 4 promotion mutation will revalidate the same lock transactionally.

Malformed active-run participation remains an integrity failure, not an unlocked fallback.

#### Unit progression read model

Extend the authoritative unit detail response with:

```text
xp_to_next_level
```

Meaning:
- exact `UnitXpResolver::threshold(current level)`;
- current persisted `xp` remains progress within the current level;
- response must satisfy `0 <= xp < xp_to_next_level`;
- client-safe positive integer;
- no read-side progression/materialization.

Do not project the XP formula into static client content merely to calculate this field in the browser.

Update strict frontend Unit Detail contracts accordingly.

#### Promotion-options endpoint

Implement:

```text
GET /api/v1/units/:unitId/promotion-options
```

This is authenticated, ownership-safe, read-only, and requires no CSRF.

Missing, foreign, inactive, and otherwise unavailable unit IDs use the existing non-disclosing unit-not-found behavior.

No read-side provisioning, progression, unlock grant, wallet mutation, or revision increment.

Return exactly:

```text
unit_id
unit_type_id
level
xp
xp_to_next_level
raw_chaos
player_revision
configuration_locked
options[]
  promotion_id
  target_unit_type_id
  required_level
  price
    currency_id = raw_chaos
    amount
  level_met
  can_afford
  available
  new_ability_ids[]
```

Semantics:

- options are exactly the authored outgoing edges for the unit's current `unit_type_id`;
- options are ordered by `promotion_id` ascending;
- tier-3/current terminal types return an empty list;
- `level_met = level >= required_level`;
- `can_afford = current raw_chaos >= authoritative price`;
- `configuration_locked` is true only when this unit participates in an active run;
- `available = level_met && !configuration_locked`;
- affordability does **not** change `available`;
- `new_ability_ids` is the target-type ability delta relative to this exact unit's permanent owned abilities;
- every returned ability ID resolves through authored content;
- `raw_chaos` and `player_revision` come from authoritative shared player state;
- client-safe numeric validation applies.

Package 3 does not require an Academy/unit-type unlock to promote an already-owned unit. Academy unit-type research controls ordinary Shop acquisition of base unit types, not a second promotion entitlement.

#### Promotion-history integrity

Add a reusable validation boundary for persisted promotion history without mutating it.

For a unit with history:

- rows remain ordered by persisted promotion order/time;
- each from/to pair must correspond to an authored `unit_promotion`;
- each row's `from_unit_type_id` must equal the preceding state;
- the final `to_unit_type_id` must equal the unit's current `unit_type_id`;
- a row cannot jump tiers or describe a path absent from canonical content.

A unit with no promotion history is valid at a tier-1 type.

A tier-2/tier-3 vNext unit with no valid authored history is an integrity failure for promotion/progression reads. Do not silently synthesize history.

Integrate this validation into the promotion-options query. If safely practical without broad regressions, also use the same validator when Unit Detail presents `promotion_history`; do not duplicate graph logic.

#### Client projection

Project browser-safe unit-promotion identity/relationship content:

```text
unit_promotions
  <promotion_id>
    id
    from_unit_type_id
    to_unit_type_id
```

Do **not** project:
- Raw Chaos price;
- required level;
- any future mutation-only rule;
- server-only progression calculations.

Extend `ClientContentRegistry` with strict promotion definitions/accessors and reference validation against projected unit types.

The promotion-options parser must reconcile the server's option identities/targets with projected promotion definitions.

#### Frontend runtime contracts

Add framework-neutral support only; no Phaser promotion UI in Package 3.

Add:
- strict promotion-options result types/parser;
- Runtime API method for `GET /api/v1/units/:unitId/promotion-options`;
- updated Unit Detail parser for `xp_to_next_level`.

Parser requirements:

- exact response envelope/fields;
- request-bound canonical positive unit ID;
- returned `unit_id` equals requested ID;
- current unit type resolves through projected content;
- level >= 1;
- XP normalized below `xp_to_next_level`;
- Raw Chaos/revision client-safe;
- options strictly sorted and unique;
- every promotion ID exists in projected content;
- projected promotion `from_unit_type_id` equals current unit type;
- projected promotion `to_unit_type_id` equals returned target;
- exact `raw_chaos` currency;
- positive/client-safe price and required level;
- booleans coherent:
  - `level_met` equals server level comparison;
  - `can_afford` equals wallet/price comparison;
  - `available` equals `level_met && !configuration_locked`;
- `new_ability_ids` unique, deterministic, projected, and present on the target unit type;
- returned option set exactly matches projected outgoing promotions for the current unit type.

Do not add GameStore promotion cache or Phaser surfaces yet.

#### Tests

Add focused coverage for at least:

**Content/graph**
- valid 20-edge canonical graph;
- missing/wrong type references reject;
- same-type edge rejects;
- wrong tier jump rejects;
- duplicate from/to rejects;
- cycle rejects;
- non-Raw-Chaos/zero/unsafe prices reject;
- invalid required levels reject;
- tier-3 canonical types have no outgoing edges;
- projection exposes only ID/from/to;
- prices and required levels are absent from static projection.

**Progression policy**
- each tier-1 type resolves exactly two tier-2 options;
- each tier-2 branch resolves exactly one shared tier-3 option;
- tier-3 resolves none;
- level 3 and level 6 thresholds are applied exactly;
- target ability delta excludes abilities already owned;
- branch abilities remain owned/relevant when converging on tier 3;
- zero-ability delta is valid;
- XP threshold delegates to current `UnitXpResolver`.

**Promotion history**
- valid Bruiser -> Enforcer -> Juggernaut history;
- valid Bruiser -> Pit Fighter -> Juggernaut history;
- analogous shared-target path validation in at least one other family;
- broken chain, nonexistent edge, tier jump, and final-type mismatch fail integrity;
- higher-tier unit without required history fails progression read;
- tier-1 no-history unit remains valid.

**Read/API**
- level below requirement: `level_met=false`, `available=false`;
- level exactly at requirement: level met;
- insufficient Raw Chaos affects `can_afford` only;
- participating active-run unit: locked + unavailable;
- non-participating unit while another active run exists: not locked;
- tier-3 empty options;
- missing/foreign/inactive unit non-disclosing;
- read changes no wallet, unit, history, unlock, revision, run, or receipt state;
- deterministic ordering;
- auth required, no CSRF required.

**Frontend**
- projected graph strictness/reference validation;
- Unit Detail `xp_to_next_level`;
- exact promotion-options parser;
- malformed identities/order/booleans/price/ability delta reject;
- API uses bodyless authenticated GET;
- no Angular gameplay dependency.

#### Verification

Run:
- `npm run verify:package`;
- focused promotion-content/graph/policy/history/query tests;
- focused Unit Detail + active-run-lock regressions;
- focused frontend content/Unit Detail/promotion contracts;
- DB provision/reset;
- full supported Docker backend suite;
- full frontend suite;
- production frontend build;
- bundle/content/docs/diff gates.

Report exact focused/full counts where available.

#### Out of scope

- `POST /api/v1/units/:unitId/promote`;
- Raw Chaos promotion spend;
- mutation/idempotency receipts for promotion;
- changing unit type/level/XP/history/abilities;
- automatic loadout edits after promotion;
- consuming/retiring secondary units;
- capstone-specific state/endpoints;
- Phaser Academy/promotion surfaces;
- final promotion balance;
- Wrong Machine/kin progression;
- final visual overhaul.

#### Completion

Implement only Milestone 8 Package 3. Leave it **In Progress** for architectural review. Do not promote Package 4 yourself.
