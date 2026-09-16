# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 5 - Battle/result/playback query and reconnect contracts

**Status:** Open
**Priority:** High

#### Problem
Packages 1-4 now provide the complete authoritative combat write path:
- canonical resolved player stats and run HP;
- canonical Farm combat content and deterministic kernel;
- immutable finalized battle persistence;
- an authenticated/idempotent node-resolution transaction that persists battle + player HP + node/run lifecycle atomically.

The client still cannot retrieve the finalized persisted playback. The active-run aggregate also has no minimal battle reference, so after a victorious combat the browser cannot rediscover the already-resolved battle from authoritative run state.

This package establishes the **read/reconnect contract only**. It exposes presentation-safe facts derived exclusively from the immutable Package 3 battle record and adds the minimal current-run battle reference required to rediscover playback. It does not implement Phaser playback, result screens, client node-resolution mutation, rewards, or combat simulation.

#### Primary battle read endpoint
Implement:

`GET /api/v1/battles/:battleId/playback`

Requirements:
- authenticated session;
- canonical positive battle ID;
- no CSRF requirement because this is a read;
- thin controller delegating to one battle-playback application query;
- missing and foreign battle IDs return the same non-disclosing not-found response;
- corrupt persisted battle/run ownership/state returns one narrow non-disclosing battle-data integrity error.

Do not accept run ID, node ID, seed, participant IDs, or any other authority from query/body/header input.

#### Lifecycle/readability rule
An owned retained finalized battle remains readable **regardless of whether the owning run is currently active, failed, abandoned, or a future terminal-success state**, until ordinary scheduled cleanup eventually removes it.

This rule is required because Package 4 can terminate the run as `failed` before the player watches the defeat/stalemate playback. A battle read must not require `runs.status = active`.

Do not resurrect the run, mutate it, or create a special battle lifecycle state in order to read playback.

Update `documentation/07-development-path/vnext-endpoint-inventory.md`, whose older wording currently says playback is for an active/incomplete run, to match this concrete retained-battle ownership contract.

#### Authority source
The playback response must be assembled **only** from the persisted Package 3 battle row:
- `input_snapshot`;
- `participant_manifest`;
- `result_json`;
- ordinary battle identity columns.

Do not consult current:
- unit names/types/levels;
- squad formation;
- current loadouts or dice;
- current authored enemy definitions;
- current ability configuration;
- current `run_unit_state` HP;
- current ContentRegistry presentation as a substitute for historical battle facts.

The Package 3 codec/value boundary must revalidate the stored row during hydration before public projection. Do not bypass it by decoding JSON ad hoc in the controller/query.

Historical combatant names/art/type identity come from the stored participant manifest. Initial positions/HP come from the stored input. Terminal HP/status comes from the stored result. Playback events come from the stored result.

#### Presentation-safe playback response
Return one strict, versioned presentation payload approximately shaped as:

```text
battle:
  id
  run_id
  run_node_id
  engine_version
  playback_version
  outcome
  ending_round
  ending_tick
  participants:
    - combatant_key
      side
      unit_id                  # owned player unit ID, null for enemy
      unit_type_id             # player stable type, null for enemy
      enemy_unit_type_id       # enemy stable type, null for player
      display_name
      art_key
      position: { x, y }
      initial_hp
      max_hp
      terminal_hp
      is_defeated
      terminal_statuses
  events:
    - exact persisted semantic playback event objects
player_revision
```

Exact envelope naming should follow existing vNext conventions.

`player_revision` is a read-only stale-cache signal and must be the player's current revision at query time; reading playback does not increment it.

The response should contain enough historical facts for Package 6 to render the combat without another content/Warband query.

Do **not** expose:
- combat seed;
- full normalized input snapshot;
- hidden enemy pre-combat stats;
- hidden enemy complete loadout/die configuration merely because it exists in the input;
- player derived Attack/Defense/Precision/Resolve snapshots unless an actually visible playback fact requires them;
- authored handler configs/power ratios as a pre-combat catalog;
- reward/XP/currency data;
- SQL/internal metadata unrelated to durable battle identity.

The semantic playback events are finalized observable battle history and may expose their existing event facts such as ability ID, die sides/rolls, damage, target reasons, status events, and resulting HP. Return those events exactly as persisted; do not regenerate, reorder, summarize, or reinterpret them.

#### Participant projection integrity
Project participant presentation by joining the **persisted in-memory battle value**, not SQL/current catalogs.

For each manifest entry:
- require exactly one matching input combatant and terminal result combatant;
- side/type identity remains exactly the manifest identity already validated by Package 3;
- position comes from the matching input combatant;
- `initial_hp` is the input `current_hp` at battle start;
- `max_hp` is the input `max_hp`;
- terminal HP/defeated/statuses come from the matching terminal result;
- no combatant is missing/duplicated/added.

The Package 3 `FinalizedBattle` validation already proves the core correspondence. The public projector/query should keep the mapping explicit and fail as an integrity error rather than relying on array order.

Do not parse database IDs from combatant keys.

#### Ownership repository boundary
Add the narrow persistence query needed to retrieve a battle through its owning run/user relation.

Ownership is authoritative as:

`battle -> run -> user`

Do not duplicate `user_id` onto `battles`.

The application query decides not-found versus integrity semantics. A repository owner-scoped lookup/join is acceptable as a persistence primitive, but it must not mutate, open transactions, or build presentation from current Warband/content state.

The existing unrestricted Package 3 persistence lookup may remain for internal trusted application use/tests; the player-facing query must not fetch arbitrary battle ID then accidentally expose it before ownership is proven.

#### Current-run battle discovery
Extend `GET /api/v1/runs/current` minimally so a persisted combat node may expose:

`battle_id: <positive canonical ID> | null`

for that node.

Rules:
- do not embed outcome/playback/participants into current-run;
- do not put battle snapshots into bootstrap;
- the run aggregate remains a run/map query, not battle history;
- an unresolved/locked/available node has `battle_id: null`;
- a completed combat node with a finalized battle returns that battle ID;
- impossible observed states such as a finalized battle attached to an uncompleted node fail the existing current-run integrity boundary rather than being silently normalized;
- non-combat completed nodes may remain `battle_id: null`;
- a completed combat node in the currently implemented slice is expected to have its finalized battle; missing battle state should be treated deliberately as integrity corruption once this contract is active.

Use a compact run-level battle lookup rather than an N+1 query if practical.

This discovery applies only while the run is active, because `GET /runs/current` intentionally returns `run: null` for terminal runs. A terminal failed battle remains directly readable through `/battles/:battleId/playback` when the client already holds its battle ID from the Package 4 resolution response/reconnect presentation state.

Do not invent a server-side pending-playback/claim flag just to make terminal playback discoverable after every future login. Package 6 may use an ephemeral client presentation-resume marker for a just-resolved battle; server gameplay authority is already finalized.

#### Frontend contract groundwork
Because Package 6 will consume this endpoint, add framework-neutral strict client contracts only:
- strict parser/type for the playback response;
- strict `battle_id` parsing in current-run node state;
- RuntimeApiClient GET method for `/api/v1/battles/:battleId/playback` if that matches current client architecture.

Do not implement `BattleScene`, animations, timers, node-resolution client POSTs, result UI, or GameStore battle lifecycle in this package unless a very small read-cache type is already necessary for contract testing. Package 6 owns the playback lifecycle.

Frontend parsing must reject:
- extra/missing fields;
- malformed canonical IDs;
- unsupported versions;
- invalid outcome/side;
- duplicate participant keys;
- malformed coordinates/HP;
- participant initial/terminal HP outside `0..max_hp`;
- defeated state inconsistent with terminal HP;
- malformed or non-contiguous playback events;
- event combatant references not present in participants;
- battle-end event/outcome/ending facts disagreement.

Do not reimplement combat mechanics in the parser. It validates transport/coherence only.

#### Read-only behavior
Battle playback GET and current-run GET must not:
- increment `player_revision`;
- change Energy or its regeneration anchor;
- change run HP;
- change node/run state;
- change battle JSON;
- add idempotency receipts;
- materialize rewards/progression;
- update playback progress.

Prove this with integration state snapshots.

#### Package 4 hardening carried forward
While touching the backend combat read/HTTP boundary, correct the one minor Package 4 error-classification edge case identified in architectural review:
- if a persisted non-empty combat-node `encounter_id` does not even match the canonical `encounter.*` stable-ID syntax, node resolution must map it to the existing non-disclosing run/combat data-integrity error rather than generic `server_error`;
- validly-shaped but missing/corrupt authored encounter references must continue to fail as non-disclosing integrity errors;
- no state may mutate in either case.

Do not otherwise reopen Package 4 transaction/combat behavior.

#### Security / non-disclosure
Use at least two users in integration coverage.

Prove:
- owner can read their retained battle;
- owner can read it after run failure;
- owner can read it after later run abandonment when a battle was won before abandonment;
- another player cannot read it;
- missing and foreign IDs use the same status/error shape;
- public payload does not expose seed/full input/hidden pre-combat enemy catalog data;
- corrupt persisted payload fails safely through the persistence codec/integrity boundary.

#### Tests / verification
At minimum prove:
- unauthenticated playback read rejected;
- malformed/noncanonical battle ID is non-disclosing not-found;
- owner read returns the exact battle identity/versions/outcome/ending facts;
- participants are projected solely from persisted input + manifest + terminal result;
- historical display/art/type/position/HP continue to return correctly after current Warband/content-like mutable database facts are changed where possible;
- exact persisted event ordering/facts are returned;
- seed/full normalized input is absent from serialized public response;
- foreign/missing battle non-disclosure;
- active-run victory battle remains readable;
- failed-run defeat/stalemate battle remains readable;
- battle remains readable if the active run is later abandoned;
- current-run victory node includes its `battle_id` and no playback payload;
- unresolved nodes return `battle_id: null`;
- corrupt battle/node lifecycle correspondence is rejected by current-run integrity validation;
- battle GET/current-run GET mutate no state/revision/Energy/HP/node/battle/idempotency records;
- strict frontend parser accepts the real Package 2/3 version-1 playback payload;
- frontend parser rejects malformed versions/participants/events/references/end facts;
- Package 4 malformed persisted encounter-ID classification now returns the narrow integrity error and rolls back;
- Packages 1-4 and M1-M3 regressions remain green.

Run actual MySQL integration tests for ownership/lifecycle/current-run projection and the repository's supported backend/frontend gates affected by this package. Because frontend transport/parser contracts change, run the focused frontend suite, full frontend suite, production build, and bundle check.

Do not claim absent GitHub CI or an unavailable host-only aggregate passed.

#### Documentation
Update current canonical docs only:
- `documentation/07-development-path/vnext-endpoint-inventory.md` with the actual retained-battle playback read contract and terminal-run readability;
- current run/API docs if needed for nullable node `battle_id`;
- storage docs only if clarification is necessary; do not redesign persistence.

Do not create package-report/history/UAT docs.

#### Explicitly out of scope
Do not implement or scaffold:
- Phaser `BattleScene`;
- combat animation timing;
- client node-resolution POST flow;
- battle result screen/Continue behavior;
- authoritative return-to-run reconciliation UI;
- server-side playback progress, acknowledgement, claim, or pending-presentation lifecycle;
- battle mutation endpoints;
- rewards, XP, objectives, currency, loot;
- Mudking/boss combat;
- Rest/loot/exit resolution;
- run completion/Mountains unlock;
- additional combat rules;
- long-term battle history UI;
- Milestone 5.

#### Review state
When complete:
- leave Package 5 **In Progress**;
- do not mark it complete;
- do not promote Package 6;
- do not begin Phaser battle playback.

Architectural review decides completion.

#### Final report
Report:
1. exact implementation commit SHA;
2. exact playback GET response shape;
3. ownership/non-disclosure query behavior;
4. active/failed/abandoned run readability behavior;
5. participant projection source and integrity rules;
6. fields intentionally withheld from public playback;
7. current-run nullable `battle_id` behavior;
8. strict frontend transport/parser additions;
9. Package 4 malformed encounter-ID hardening;
10. read-only state proof;
11. backend/MySQL/frontend verification commands/results;
12. any environment-limited gates;
13. unresolved concern, if any.

Do not begin another package.
