# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 1 - Authored Academy upgrades + permanent capability foundation + read contract

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 7 - Economy and Inventory is complete. Technical closure was approved at `e59b58e709892cc0a72e609576800dc45115813e`, and focused manual UAT passed on 2026-09-28 after the following UAT corrections:

- `c40f771cc089b649e7f676be0764eba6703ed63e` - fixed Shop/Supplies return-navigation recursion;
- `35b2b0359b406df231ee459fb68eba8f6160878b` - fixed Warband return navigation and dice-confirmation layering;
- `ba21115247ee862a739f07906c5e1df0f304e2b7` - made shared player state the single runtime authority for Teeth/Shop affordability.

Package 1 begins Milestone 8. Do not carry the prototype Academy or promotion persistence/orchestration forward wholesale.

#### Purpose

Establish the permanent-progression vocabulary and authoritative read boundaries that later Milestone 8 mutation/UI packages will use.

This package owns:

- authored permanent capability definitions;
- authored Academy upgrade definitions;
- canonical Academy progression content;
- a shared policy for derived permanent capabilities, initially Energy normal maximum and maximum acquirable die size;
- the authoritative `GET /api/v1/academy` read contract;
- strict frontend Academy/content contracts without a Phaser Academy screen yet;
- integration of the capability policy into existing Energy and die-acquisition eligibility boundaries.

This package does **not** spend Raw Chaos, promote units, add Academy UI, or grant progression from the client.

#### Durable-state rule

Do not add an Academy ownership table or generic player-progression table.

An Academy upgrade produces one permanent authored unlock. The durable truth is the existing `user_unlocks` row for that unlock.

Academy upgrade ownership is therefore derived:

```text
academy upgrade
  -> authored grant_unlock_id
  -> user owns that unlock
  -> upgrade is owned
```

Derived capabilities are calculated from authored content plus owned unlock IDs. Do not persist `energy_max`, `max_die_size`, Academy levels, or duplicate capability flags on `user_state`.

#### Authored permanent capability model

Add a narrow authored definition type:

```text
capability
```

Supported Package 1 capability kinds are exactly:

- `energy_normal_max`
- `max_acquirable_die_size`

A capability definition contains only stable identity, supported kind, and the integer value required by that kind. Keep this declarative; do not create arbitrary effect scripts/rule trees.

Canonical capability definitions:

- `capability.energy_max_75` -> `energy_normal_max = 75`
- `capability.energy_max_100` -> `energy_normal_max = 100`
- `capability.die_size_d10` -> `max_acquirable_die_size = 10`
- `capability.die_size_d12` -> `max_acquirable_die_size = 12`
- `capability.die_size_d20` -> `max_acquirable_die_size = 20`

Extend authored `unlock` validation so an unlock may target a `capability` in addition to the already accepted target types. The target must resolve to the exact authored capability definition.

Canonical capability unlocks:

- `unlock.capability.energy_max_75`
- `unlock.capability.energy_max_100`
- `unlock.capability.die_size_d10`
- `unlock.capability.die_size_d12`
- `unlock.capability.die_size_d20`

No generic feature/effect namespace is required merely to implement these two concrete capability families.

#### Authored Academy upgrade model

Add authored definition type:

```text
academy_upgrade
```

Each upgrade must have:

- stable ID;
- display name;
- description;
- category: `unit_type`, `energy`, or `dice`;
- Raw Chaos price;
- exactly one `grant_unlock_id`;
- zero or more `prerequisite_unlock_ids`.

Rules:

- price currency is exactly `raw_chaos`;
- price amount is a positive client-safe integer;
- grant/prerequisite IDs must reference authored `unlock` definitions;
- prerequisite IDs are unique and cannot include the upgrade's own grant;
- one permanent unlock may be the grant target of at most one Academy upgrade;
- reject direct or indirect Academy prerequisite cycles;
- upgrade IDs/order are deterministic;
- Academy definitions contain no executable scripts.

The generated client projection may expose player-safe presentation fields such as ID, display name, description, and category. Do **not** project authoritative prices or capability numeric internals merely because the Academy screen will eventually need presentation.

#### Canonical Package 1 Academy catalog

Author these provisional upgrades. These values are implementation/UAT balance anchors, not final economy tuning.

**Base unit-type research — 5 Raw Chaos each**

- `academy_upgrade.guardian` -> `unlock.unit_type.guardian`
- `academy_upgrade.marksman` -> `unlock.unit_type.marksman`
- `academy_upgrade.bannerbearer` -> `unlock.unit_type.bannerbearer`
- `academy_upgrade.saboteur` -> `unlock.unit_type.saboteur`

Add the corresponding authored unit-type unlock definitions. Bruiser remains the existing starting/base entitlement and does not need an Academy upgrade.

Also add ordinary 8-Teeth Shop offers for Guardian, Marksman, Bannerbearer, and Saboteur. Existing Shop availability rules must keep those offers unavailable until the corresponding unit-type unlock is owned.

**Energy capacity**

- `academy_upgrade.energy_max_75` -> `unlock.capability.energy_max_75` — 5 Raw Chaos
- `academy_upgrade.energy_max_100` -> `unlock.capability.energy_max_100` — 10 Raw Chaos
  - prerequisite: `unlock.capability.energy_max_75`

**Die-size eligibility**

- `academy_upgrade.die_size_d10` -> `unlock.capability.die_size_d10` — 5 Raw Chaos
- `academy_upgrade.die_size_d12` -> `unlock.capability.die_size_d12` — 10 Raw Chaos
  - prerequisite: `unlock.capability.die_size_d10`
- `academy_upgrade.die_size_d20` -> `unlock.capability.die_size_d20` — 20 Raw Chaos
  - prerequisite: `unlock.capability.die_size_d12`

Do not add higher-die production Shop offers in Package 1. Package 2 will own the first player-visible >d8 acquisition path after the Raw Chaos upgrade mutation exists.

#### Permanent capability policy

Introduce one shared backend policy/service for derived permanent capabilities.

Given the canonical ContentRegistry plus owned unlock IDs it must resolve:

**Energy normal maximum**

- base = `config.gameplay.energy_normal_max`;
- an owned `energy_normal_max` capability raises the normal maximum to its authored value;
- multiple owned values resolve to the maximum;
- current Energy is not clamped merely because normal maximum changes.

Use this policy anywhere the backend currently needs the player's normal Energy maximum, including at minimum:

- game bootstrap;
- run-start Energy projection/spend;
- Energy-restoration consumable calculation.

All three boundaries must agree for the same player/unlock state.

**Die acquisition eligibility**

- base acquirable sizes are d4/d6/d8;
- an owned max-size capability permits standard sizes up to that authored threshold;
- supported standard sizes are d4/d6/d8/d10/d12/d20;
- d10 capability permits d10;
- d12 capability permits d10/d12;
- d20 capability permits d10/d12/d20;
- profile `allowed_sizes` remains an independent required constraint.

Unknown/stale persisted unlock IDs must not accidentally grant a capability.

#### Wire die eligibility into existing Shop authority

Replace the Milestone 7 hard-coded purchase assumption that all valid Shop dice are only d4/d6/d8 with the shared eligibility policy.

For authored/test Shop die offers:

- content validation may recognize d4/d6/d8/d10/d12/d20 when the referenced profile allows the size;
- `GET /api/v1/shop` must mark a >d8 die offer unavailable when the player lacks the required capability;
- purchase must revalidate capability authoritatively inside its transaction before spending;
- a locked-size purchase rejection changes no Teeth, die ownership, revision, or idempotency receipt;
- owning the appropriate capability makes the same authored offer eligible;
- frontend Shop/purchase contracts must accept all standard die sizes even though canonical production >d8 offers remain deferred to Package 2.

Do not derive die-size entitlement from currently owned dice. The capability unlock is the authority.

#### Academy read contract

Implement:

```text
GET /api/v1/academy
```

Requirements:

- authenticated query;
- no CSRF;
- no mutation;
- no read-side provisioning;
- no revision increment;
- no Raw Chaos spend;
- canonical deterministic ordering.

Response data:

```text
raw_chaos
player_revision
upgrades[]
  upgrade_id
  price
    currency_id = raw_chaos
    amount
  owned
  available
```

Semantics:

- `owned` is true when the upgrade's `grant_unlock_id` is already owned;
- `available` is true only when the upgrade is not owned and every prerequisite unlock is owned;
- insufficient Raw Chaos does **not** change `available`; affordability is a presentation calculation from authoritative wallet + price;
- query never grants missing prerequisites or repairs state;
- numeric values are client-safe non-negative integers and upgrade price is positive.

The endpoint should use the existing player-state and unlock repositories plus ContentRegistry. Do not introduce Academy SQL catalog tables.

#### Frontend/runtime contracts

Add strict framework-neutral runtime support only; no Phaser Academy screen in Package 1.

Add:

- client-projected Academy presentation definitions;
- `ClientContentRegistry` Academy accessors;
- exact `GET /api/v1/academy` parser;
- Runtime API client method;
- read-model types suitable for the later GameStore/Academy screen.

Frontend validation must require:

- exact response envelope/fields;
- stable request/result upgrade identity;
- `raw_chaos` and revision client-safe;
- Raw Chaos price positive/client-safe;
- exact `raw_chaos` currency ID;
- every server upgrade maps to a projected authored Academy definition;
- no duplicate/missing/extra upgrade IDs compared with projected public Academy content;
- deterministic ordering.

Do not create an Academy mutation attempt, store cache, or Phaser UI yet unless a narrow existing architecture requirement makes the read contract impossible to test otherwise.

#### Existing Shop/player-state ownership

Preserve the UAT correction at `ba21115247ee862a739f07906c5e1df0f304e2b7`:

- `bootstrap.player` remains the single runtime owner for Teeth;
- Shop does not regain a second mutable Teeth/affordability snapshot;
- server Shop `can_afford` is still validated for response coherence.

Package 1 Academy frontend contracts should follow the same authority lesson: Raw Chaos belongs to shared player state once a GameStore surface is introduced later, rather than becoming a second independently mutable Academy wallet.

#### Tests

Add focused coverage for at least:

**Content**
- valid capability definitions;
- unsupported capability kind/value rejects;
- valid Academy definitions;
- missing grant/prerequisite references reject;
- duplicate prerequisite/self prerequisite rejects;
- duplicate grant ownership across Academy upgrades rejects;
- prerequisite cycle rejects;
- client projection exposes only allowed Academy presentation data;
- capability numeric internals and Academy Raw Chaos prices are not leaked through static projection.

**Capability policy**
- no capability => Energy 50 and max acquired die d8;
- Energy 75 then 100 resolve correctly;
- d10/d12/d20 chains resolve correctly;
- mixed/unknown unlock IDs cannot grant unintended capability;
- profile size compatibility remains required.

**Energy integration**
- bootstrap, run start, and Energy restore all use the same derived normal maximum;
- persisted current Energy is not rewritten by a read;
- owning a higher max allows regeneration toward the higher cap;
- existing overcharge behavior remains intact.

**Shop eligibility**
- existing d4/d6/d8 remain eligible without a size capability;
- fixture d10/d12/d20 offers are unavailable/rejected without capability;
- appropriate capability makes the exact size eligible;
- rejection is atomic;
- existing unit/item Shop behavior remains green;
- newly authored T1 Goblin offers remain unavailable before their unit unlock and become available when that unlock exists.

**Academy query/API/frontend**
- wallet/revision/current ownership;
- prerequisite availability chain;
- owned upgrade state;
- deterministic order;
- auth failure;
- exact strict parser;
- static content/read response reconciliation;
- no mutation on read.

#### Verification

Run:

- `npm run verify:package`;
- focused authored-content/capability/Academy query tests;
- focused Energy bootstrap/run-start/restore tests;
- focused Shop purchase/catalog tests including >d8 fixture eligibility;
- focused frontend content/Academy/Shop contracts;
- DB provision/reset;
- full Docker backend;
- full frontend;
- production frontend build;
- bundle/content/docs/diff gates.

Report exact test/assertion/skipped counts where available.

#### Out of scope

- `POST /api/v1/academy/upgrade` or any Raw Chaos spend;
- canonical d10/d12/d20 Shop offers;
- Phaser Academy screen/Camp Academy navigation;
- unit promotion options or mutation;
- promotion costs/level thresholds;
- retiring/consuming secondary units during promotion;
- capstone-specific state/endpoints;
- Wrong Machine/kin progression;
- final Academy/economy balance;
- final visual overhaul.

#### Completion

Implement only Milestone 8 Package 1. Leave it **In Progress** for architectural review. Do not promote Package 2 yourself.
