# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 7 - Contextual consumables + multi-step encounter interaction

**Status:** In Progress
**Priority:** High

#### Problem

Packages 1-6 now provide an authoritative exploration frontier, generalized non-combat encounters, server-owned random outcomes, and persisted run-scoped modifiers. The remaining encounter-depth gap is interaction that cannot be finalized by one stateless action: an encounter may need to expose an inventory-dependent option, consume an eligible item authoritatively, and/or persist a small intermediate choice so reload can resume the same encounter safely.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Packages 1-6 are approved through the Package 6 correction `736f5211a6e2fff1838fa5c6ec779e229f7368d6`.

The backend/MySQL run state is authoritative. Package 2 established the generic authored non-combat interaction boundary. Packages 3-5 proved Hazard, Shrine, and Chaos families without family-specific read/client dispatch. Package 6 established run-owned temporary modifier persistence and authoritative later application. Existing inventory/economy/reward boundaries remain canonical and must be reused rather than bypassed.

Farm remains the fixed five-node tutorial. Mountains remains the first production exploration region and adds Hazards only. Shrine and Chaos production content remain reserved for Swamp/Milestone 13. Package 7 must not add speculative production Shrine/Chaos content to Farm or Mountains merely to prove this architecture.

#### Purpose

Establish the smallest reusable contract for contextual consumable use and resumable multi-step encounter interaction.

Prove one narrow encounter lifecycle in which the server determines whether a contextual inventory action is available, authoritatively consumes exactly one eligible owned item when chosen, persists only the minimal intermediate encounter state needed to resume after reload, and finalizes the node/frontier exactly once.

This is an interaction/persistence package, not a generalized dialogue engine, quest engine, item scripting language, or production Swamp content package.

#### Required inventory before implementation

Before designing new storage or contracts, inspect:
- the accepted vNext inventory/item ownership and consumption paths;
- current run-node persistence and resolution receipts;
- the generalized non-combat interaction projection/action submission boundary;
- any retained prototype contextual-item or multi-step encounter implementation.

Document which existing boundaries are reused and which prototype concepts are intentionally dropped. Do not preserve prototype shape for compatibility.

#### Canonical design constraints

- Backend owns item eligibility, inventory quantity/ownership, consumption, encounter-step state, action validity, node completion, frontier changes, revision, and finalized receipts.
- Client may display only actions currently authorized by the server. It must not infer that an item is usable from local inventory alone.
- Contextual item use consumes existing authoritative inventory; it is not a reward grant and must not create a second inventory mutation path if an accepted one already exists.
- Intermediate encounter state is run/node-owned and reconnect-safe. Persist only what is necessary to reconstruct the current authorized step; do not persist arbitrary client UI state.
- A node in an intermediate step remains unresolved. It must not unlock successors or alter unrelated available frontier entries until the encounter is finalized.
- Exact-key replay of a step mutation must not consume an item twice, advance two steps, duplicate effects, increment revision twice, or finalize the node twice.
- A stale action from an earlier step must fail closed without mutation.
- Locked/completed/foreign/terminal nodes cannot be interacted with through intermediate-step endpoints/contracts.
- Player-visible authored copy/action labels may be projected; hidden predicates, item-consumption rules, effects, tuning, and authored catalogs remain server-private unless intentionally player-facing.
- Respect the reward/effect boundary. Consuming an owned item is an inventory/domain mutation; any asset granted by the encounter remains a reward and must use the canonical reward path.
- Do not introduce production Shrine/Chaos content to Farm or Mountains, Swamp production content, Frog Kin, Knowledge/Objectives, onboarding, or final visual redesign.

#### Minimum proof

Use validated test-only authored content unless an already-canonical production encounter genuinely requires this behavior.

The preferred proof is one test-only non-combat encounter with two stages:

1. **Initial step** exposes a normal continue/decline-style action plus a contextual action only when the player owns an eligible existing consumable/item.
2. Choosing the contextual item action atomically consumes exactly one eligible item and moves the encounter into a persisted intermediate step without completing the node.
3. Reload/re-query reproduces the intermediate step and only its currently authorized actions/copy.
4. A second action finalizes the encounter, completes the node, unlocks direct successors, and preserves unrelated available frontier nodes.

If the existing inventory model has no semantically valid consumable suitable for this proof, do not invent production item content casually. Use the narrowest validated test fixture that exercises the accepted inventory ownership/quantity machinery and explain why. Do not add a generalized item-effect system.

The proof should demonstrate both contextual inventory gating and multi-step persistence in one coherent lifecycle rather than creating two unrelated abstractions.

#### Persistence scope

Prefer extending the existing run-node persistence model with minimal encounter-progress state if it can represent the accepted semantics cleanly. Avoid a new encounter-family-specific table.

Any persisted progress must:
- belong to exactly one run node;
- be validated against that node's persisted encounter identity;
- use stable authored step/state identity rather than arbitrary serialized client payloads;
- be absent/terminal once the node is completed, unless existing retention conventions require historical data;
- be deleted/cascaded with the run through existing ownership cleanup.

Do not build generic workflow JSON, arbitrary key/value state, or an expression engine.

#### API/read contract

Prefer extending the existing current-run interaction projection and run-node mutation boundary rather than adding family-specific endpoints.

The current authorized interaction may include a stable step identity if required for stale-action protection/reconnect. If exposed, it must be an opaque/stable authored identity, not arbitrary server state.

The read model must derive authorized actions from authoritative persisted step + current inventory eligibility. It must not trust a client-submitted list of items or actions.

If the existing resolution endpoint cannot safely distinguish intermediate progress from final node resolution, make the smallest generic contract extension needed and document it. Do not create Shrine/Chaos-specific APIs.

#### Inventory semantics

- Validate ownership/quantity under the same transaction/locking boundary as step advancement.
- Consume exactly the authored required quantity; for this package prefer quantity 1.
- If the item disappeared or quantity changed before submission, fail without advancing the step.
- Exact-key replay returns the finalized step receipt without consuming again.
- A different idempotency key attempting the stale prior-step action must fail without another consumption.
- Inventory belonging to another user or otherwise ineligible inventory cannot satisfy the action.
- Do not expose server-private eligibility predicates or effect configuration to the browser.

#### Multi-step semantics

- Initial -> intermediate advancement is a real authoritative mutation and should increment `player_revision` exactly once if that matches the accepted mutation contract.
- Intermediate state survives reload/reconnect.
- The node remains `available` while intermediate interaction is in progress unless the existing run model has a narrower accepted state representation; do not invent a globally new node status unless necessary.
- Unrelated already-available nodes remain available while this node is mid-interaction.
- Finalization completes the node and unlocks successors using Package 1 frontier semantics.
- Once finalized, no intermediate action can be replayed under a new key.
- Terminal run protections remain in force.

#### Phaser/runtime scope

Keep frontend changes focused on generic interaction behavior:
- render only server-authorized actions for the current step;
- clearly distinguish an inventory/contextual action using server-provided player-facing text if needed;
- block duplicate input while a mutation is pending;
- after any step mutation, reconcile from authoritative response/current-run state rather than predicting inventory or step changes;
- reload/re-entry renders the persisted current step;
- no family-specific Shrine/Chaos client dispatch;
- no Package 8 broad map/encounter visual redesign.

#### Verification requirements

Prove at minimum:
- authored step/action/contextual-item definitions validate; malformed step IDs, references, quantities, transitions, and unsupported effect/state shapes fail validation;
- production Farm/Mountains content is unchanged unless an already-canonical production requirement explicitly says otherwise;
- the initial test encounter projects the contextual action only when authoritative inventory eligibility is satisfied;
- an ineligible/no-item player does not receive or successfully invoke that action;
- choosing the contextual action consumes exactly one owned eligible item and advances exactly one step atomically;
- the node is not completed and successors are not unlocked at the intermediate step;
- unrelated available frontier nodes remain available;
- exact-key replay of the contextual step returns the same receipt with no second consumption/revision/step advance;
- a new-key stale initial-step request fails without mutation;
- reload/re-query reproduces the intermediate step and authorized actions;
- final-step action completes exactly once, unlocks direct successors, and preserves unrelated frontier;
- exact-key finalization replay is stable; new-key post-completion requests fail;
- locked/foreign/completed/terminal/corrupt-step requests do not mutate inventory or run state;
- another user's inventory cannot satisfy or be consumed by the action;
- browser projection excludes hidden eligibility/effect/configuration data;
- existing inventory/economy behavior remains green;
- Rest/Hazard/Shrine/Chaos, modifier lifecycle, Combat/Boss, Farm/Mountains completion, frontier, idempotency, and active-run Warband restrictions remain green;
- no reward bypass is introduced.

Run applicable gates from `agent/QUALITY_GATES.md`.

#### Scope exclusions

Do not add:
- a generic dialogue/workflow engine;
- arbitrary encounter key/value state;
- arbitrary item scripting/effect graphs;
- multiple speculative consumable families;
- production Shrine/Chaos content to Farm or Mountains;
- Swamp/Frog Kin;
- additional run-modifier families unless strictly required by an already-accepted contract;
- Knowledge/Codex/Objectives;
- onboarding/Mystic Cave;
- Milestone 11+ functionality;
- final visual redesign.

#### Completion evidence

Report:
- implementation SHA;
- inventory/prototype inventory performed before implementation and reuse/drop decisions;
- authored test encounter/item/step representation;
- persisted intermediate-state shape and why it is minimal;
- API/read projection changes, if any, and proof they remain family-agnostic;
- authoritative item eligibility/locking/consumption transaction semantics;
- initial -> intermediate -> final lifecycle evidence;
- exact-key replay and stale-new-key evidence at each mutation boundary;
- reload/re-entry evidence;
- frontier behavior while intermediate and after finalization;
- foreign/locked/completed/terminal/corrupt-state rejection evidence;
- browser secrecy evidence;
- inventory/economy and Package 1-6 regression evidence;
- focused/full quality-gate results;
- confirmation no production Shrine/Chaos, Swamp, Package 8 visual integration, or Milestone 11+ scope was introduced.

Leave Package 7 **In Progress** for architectural review. Do not promote Package 8 yourself.

#### Package 7 implementation evidence (review candidate)

- Inventory review: vNext `user_items` and `UserItemRepository::decrement` already provide owned-stack locking and transactional consumption. The node-resolution command already owns the run/node lock, revision, frontier, and finalized idempotency receipt. The current-run query and Phaser action submission remain the shared non-combat read/write boundary. The retained prototype `EncounterPrimitiveCatalog` item/choice pressure and `ChaosEncounterService` reel state do not model this accepted two-step interaction; no prototype persistence or scripting shape was copied.
- Test-only `encounter.test_contextual_shrine` supplies one initial contextual `advance_step` action and one authored `kindled` step. Its `item.test_ritual_wick` is a test-only stackable material: the existing production consumables have dedicated healing/Energy effects and are not semantically appropriate as a shrine reagent. Neither item nor encounter is in production content. Validation requires a stable single step, unique action IDs, an existing stackable material, quantity exactly one, a valid transition, and supported final family effects.
- `run_nodes.encounter_step_id` is the sole additional persisted state; NULL means initial or completed, and completion clears it. The generic `ContextualEncounterStepAdvancer` uses the current authored action, canonical locked decrement, and compare-and-set node-step update inside the existing resolution transaction. The same receipt and revision path handles intermediate and final mutations. `interaction_step` is the narrow response for a still-available node; it unlocks nothing. Existing final receipts are unchanged.
- The current-run interaction projects the current authored copy, opaque step ID where present, and actions filtered by current owned quantity. It never projects item requirements, quantities, effects, predicates, or the encounter catalog. The same `/runs/:runId/nodes/:nodeId/resolve` endpoint and generic RunScene action path are used; RunScene reloads current-run and inventory state after intermediate success.
- Focused proof covers hidden/ineligible action, another user's stack, disappearing inventory after read, one-item consumption, one-step/revision advancement, unrelated available frontier retention, reload/re-entry, stale prior-step rejection, exact-key replay at both steps, final unlock once, locked/foreign/terminal/corrupt/completed rejection, and forced post-mutation rollback of item/step/revision/receipt. Browser contract tests reject private effect and item fields. Production Farm/Mountains authored content is unchanged.
- Focused verification: content validation **2 tests / 14 assertions**; contextual integration **4 tests / 44 assertions**; clean vNext baseline **8 tests / 74 assertions**. `npm run verify:package` passed all eight configured gates: backend **442 tests / 2,494 assertions**, frontend **556 tests**, content validation, production build, bundle, agent/context, docs lint, and diff check. RunScene deterministic Rest-interaction captures passed at Compact 960x540, reference 1600x900, and Wide 1920x900. The Docker PHPUnit recursive directory iterator did not enumerate the newly added backend test files in its configured aggregate suite (it also omits other pre-existing files); both new files were therefore run by explicit path with the focused counts above. Package 7 tests are verified, but the aggregate count must not be read as including them.
