# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Complete Enter Farm integrated verification and closure

**Status:** Open
**Priority:** High

#### Problem
Packages 1-6 now implement the complete Milestone 3 Enter Farm slice:
- normalized active-run persistence;
- canonical/private Farm generation content;
- pure deterministic graph generation;
- transactional idempotent run start and Energy spend;
- authoritative current-run and abandon lifecycle;
- active-run Warband configuration locks;
- bootstrap run summary;
- mounted Phaser Camp/RunScene start/resume/reload lifecycle;
- persisted Farm-map rendering;
- explicit abandon confirmation/reconciliation.

Before manual UAT, verify this as one integrated authoritative system, fix concrete Milestone 3 defects discovered by verification, and remove only conclusively superseded prototype run UI/lifecycle code that no longer serves later milestones.

This is a closure/verification package, not a new feature package.

#### Required Context
Read before implementation:
- `AGENTS.md`
- `agent/QUALITY_GATES.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-energy-model.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/02-systems/run-node-generation.md`
- `documentation/02-systems/warband-and-formation.md`
- Package 1-6 implementation/tests
- current deterministic capture and real-stack verification scripts

#### Primary closure proof
Exercise the real vNext slice against real PHP/MySQL and the browser runtime, preferably from a freshly reset/provisioned database.

Use controlled development/UAT fixture support for Warband setup; do not add production starter provisioning.

Prove this player flow:
1. authenticate with a fresh account;
2. `/game` loads Camp with no active run;
3. controlled fixture establishes a valid active Warband squad;
4. Camp shows canonical Farm Energy cost and current effective Energy;
5. Start Farm submits one idempotent authoritative start;
6. Energy decreases exactly once by canonical cost;
7. `player_revision` increments exactly once;
8. RunScene loads the persisted current run;
9. Farm map shows the exact persisted graph;
10. Return to Camp does not abandon;
11. Camp shows Resume and sends no second run-start mutation;
12. Resume returns to the same run/map;
13. browser reload bootstraps directly back to the same RunScene/run ID;
14. abandon confirmation Cancel leaves the run active;
15. abandon Confirm terminalizes the run authoritatively;
16. abandon increments revision exactly once and refunds no Energy;
17. Camp returns to Start Farm state;
18. current-run read returns null;
19. the terminal run row, persisted nodes/edges, and participating-unit history remain stored.

Do not mock PHP/MySQL for this primary closure proof.

#### Fresh database and storage
Reset/provision the fresh vNext baseline and verify the Milestone 3 schema/invariants together with Milestone 2 state.

At minimum verify:
- expected vNext table inventory;
- one active run per user;
- separate users may each have active runs;
- multiple terminal historical runs remain legal;
- active run references a participating saved squad without terminal history permanently preventing later squad deletion;
- run-local node identity/order;
- relational edge endpoint integrity;
- duplicate/self/cross-run edge protection as designed;
- run graph/unit-state cascade behavior when a run is deliberately removed in tests;
- one run-unit row per participating unit;
- authored region/node IDs remain strings rather than SQL catalogs;
- `current_hp` remains intentionally nullable/deferred;
- fresh registration creates no units/dice/squads/runs;
- no new SQL gameplay catalogs were introduced.

#### Authored content and generation
Re-run canonical generation/validation and exact client-projection comparison.

Prove:
- Farm generation definition is canonical server-owned JSON;
- region → generation references validate;
- malformed/disconnected graph definitions fail validation;
- browser projection contains safe `run_node_type.*` presentation and `gameplay.run_energy_cost` only as explicitly allowlisted run presentation;
- private generation topology/algorithm/reference and other private gameplay config remain absent from browser content;
- global content revision includes private canonical content;
- `FixedGraphRunGenerator` remains infrastructure-free;
- same definition generates the exact deterministic Farm graph;
- generator output contains no DB IDs;
- runtime generated-graph validation remains independent of persisted current-run validation.

#### Run-start authority, Energy, and idempotency
Re-run focused and integrated Package 3 coverage.

Verify:
- auth + CSRF + `Idempotency-Key` required;
- exact `{region_id}` request contract;
- server chooses active squad;
- missing/empty/foreign/corrupt squad/unit/loadout/dice state rejected safely;
- existing active run rejected before Energy spend;
- canonical Energy cost is server-authored;
- below-cap fractional regeneration anchor behavior remains correct;
- reaching cap/full/over-cap behavior prevents banked capped regeneration;
- insufficient Energy creates no run/receipt/revision mutation;
- successful start writes run/nodes/edges/unit state + Energy + revision + idempotency receipt in one transaction;
- exact replay creates/spends/increments only once;
- same key with different request conflicts;
- injected generation/persistence failure rolls back all state.

#### Current-run and abandon authority
Verify Package 4 lifecycle behavior:
- current run `run:null` with no active run;
- active read returns persisted graph rather than regenerating;
- read is non-mutating;
- mutable progressed node states are accepted without fresh-generation availability assumptions;
- private generated metadata/topology source is not exposed;
- corrupt authored/cross-owner relationships fail as integrity/non-disclosure errors;
- abandon auth/CSRF and owned-run behavior;
- foreign/missing IDs non-disclosing;
- active → abandoned terminal timestamp/revision exactly once;
- repeated owned already-abandoned attempt is successful no-op;
- no Energy refund or Energy-anchor mutation;
- graph/unit history retained.

#### Active-run Warband locks
With an active run, verify the authoritative backend lock matrix:
- switching to a different squad blocked;
- re-activating participating active squad remains no-op;
- participating formation/membership/position change blocked;
- participating squad deletion blocked, including only-squad case;
- participating squad name-only change allowed if formation identical;
- participating unit loadout/dice replacement blocked;
- participating unit rename allowed;
- non-participating squad/unit configuration follows normal rules;
- blocked commands do not increment revision;
- after abandon, previously blocked legitimate configuration succeeds.

Where practical characterize the shared `user_state`-first transaction order for start/abandon/configuration races.

#### Client start/resume lifecycle
Re-run the Package 5 client contracts and integrated browser behavior.

Verify:
- public run cost is read from generated client content, never hard-coded in Camp;
- start request uses credentials/CSRF/exact body/idempotency key;
- one logical start attempt retains one key across network, malformed-response, HTTP 5xx, and repeated mixed ambiguous failures;
- definitive auth/4xx failure may begin a later new attempt with a new key;
- successful server response that cannot reconcile enters reload-required recovery and cannot issue another POST;
- no optimistic Energy or active-run mutation;
- startup bootstrap active run routes directly to RunScene without another bootstrap/content load;
- `/runs/current` is lazy/deduplicated and fresh state is reused;
- Return to Camp is non-mutating;
- Resume issues no start POST;
- browser reload resumes the same persisted run;
- newer `run:null` clears stale cross-tab active-run state; equal-revision contradictions fail safely;
- one RuntimeStartup/GameStore/ClientContentRegistry/RuntimeViewport/Phaser game/canvas survives GameScene ↔ RunScene switching.

#### Farm map authority
Verify Package 6 map behavior as a projection of authoritative persisted state.

Required proof:
- map model receives only the strict current-run aggregate + ClientContentRegistry;
- returned node positions determine node placement;
- returned edges determine edge rendering;
- a non-linear/non-five-node valid test graph demonstrates no hidden fixed Farm sequence assumption;
- node names/descriptions/icons come from authored projected node types rather than index-based labels;
- locked/available/completed remain visually/model-distinct;
- legitimate progressed persisted state renders;
- local node selection is presentation-only and does not mutate GameStore, POST, unlock/complete nodes, reward, or enter BattleScene;
- no `current_node_id` is invented;
- no private run-generation JSON is imported into the browser.

#### Abandon client reconciliation
Verify:
- exact POST route, credentials and CSRF;
- no body and no Idempotency-Key;
- strict abandon response rejects Energy/additional/malformed state;
- explicit confirmation states no Energy refund;
- Cancel performs no mutation;
- duplicate submission prevented;
- network/malformed/5xx ambiguity preserves active cached run and retries the same run ID;
- definitive rejection preserves active state;
- valid success reconciles revision and clears active/current run only after success;
- Energy object/value remains unchanged;
- active squad and Warband caches remain unchanged;
- reconciliation disagreement requires reload and blocks further abandon mutation;
- successful abandon returns to Camp without global bootstrap/profile/Warband refresh.

#### Cross-player/security verification
Use at least two users where practical.

Verify player A cannot:
- query B's run through any exposed vNext route;
- abandon B's run;
- start using B's squad/unit/die state;
- mutate B's Warband while B's run exists;
- learn foreign persisted IDs/details through unsafe error differences.

Persisted corrupt cross-owner run-squad/run-unit relationships must fail as integrity errors without leaking foreign state.

#### Network/runtime assertions
During real browser verification capture/inspect requests and prove:
- no `/api/v1/profile`;
- no `/api/v1/teams`;
- no prototype run endpoint family;
- no Angular gameplay routes/services owning run flow;
- no bootstrap refresh used for normal start/abandon reconciliation;
- no `game-content.json` refetch per screen transition;
- Resume sends no `POST /api/v1/runs`;
- Return to Camp sends no abandon request;
- Start sends exactly one mutation for a successful attempt;
- same Phaser canvas persists across GameScene/RunScene transitions.

#### Responsive captures
Generate and visually inspect deterministic captures for the final Package 6 state:
- Farm Compact `844x390`;
- Farm Standard `1600x900`;
- Farm Wide `2560x1080`;
- abandon confirmation at a representative landscape size;
- touch-first portrait gate.

Also re-check Camp Start/Resume presentation where inexpensive.

Inspect for:
- clipped nodes/labels;
- edges that do not visually meet their nodes;
- status differentiation;
- unreachable/tiny touch targets;
- overlap between map, detail text, Return, and Abandon;
- unsafe confirmation controls;
- raw IDs dominating UX;
- portrait interaction leaking through the gate;
- broken reflow or state loss.

Final visual polish remains deferred; functional clarity is the bar.

#### Quality gates
Run all applicable commands in `agent/QUALITY_GATES.md`.

Closure should substantiate, not assume, Package 1-6 verification.

At minimum run/report:
- `npm run llm:check`;
- `npm run docs:lint`;
- canonical content generation/validation/exact projection check;
- fresh vNext DB reset/provision;
- focused run persistence/generator/start/current/abandon/lock backend integration suites;
- full backend Docker suite;
- focused run frontend contract/store/map/scene/Camp suites;
- full frontend suite;
- production frontend build;
- bundle check;
- deterministic captures listed above;
- real PHP/MySQL/browser run-lifecycle verifier;
- `git diff --check`;
- `npm run verify:full` when host prerequisites permit.

If aggregate `verify:full` cannot execute because host PHP or another host-only prerequisite is missing, run all required constituents through their supported paths and report the limitation exactly. Do not claim aggregate success when it did not run.

#### Prototype disposition/cleanup
Inspect retained prototype run code against `vnext-prototype-code-disposition.md` only after the vNext slice is proven.

Delete only code that is conclusively:
- unreachable from live composition;
- fully superseded by the now-proven vNext Enter Farm slice;
- not useful evidence for later unimplemented Mountains/Swamps, node-resolution, combat, rewards, Rest/Chaos, or progression work.

Likely cleanup candidates include unreachable prototype Angular run/map lifecycle pages/services whose vNext presentation and lifecycle are now proven.

Retain prototype generator/algorithm evidence still needed for later regions/mechanics. Retain combat/node-resolution/reward evidence still needed by later milestones even when its old orchestration wrapper is obsolete.

Do not create an archive. Git history is the archive.

Do not perform unrelated cleanup.

#### Documentation
Update existing active documentation only where integrated verification/cleanup changes current truth.

Do not create completion-report or UAT-history documents.

Do not mark Milestone 3 complete/UAT-passed in this package. Architectural review will mark technical completion and prepare manual UAT.

#### Explicitly Out of Scope
Do not implement:
- node resolution;
- node completion/unlocking;
- combat or BattleScene entry;
- enemies/encounters;
- loot/reward application or claims;
- Rest behavior;
- Boss behavior;
- Exit/run-completion behavior;
- resolved combat HP/stat formulas;
- run modifiers;
- battle playback;
- promotion/Academy;
- Shop;
- Wrong Machine;
- production starter onboarding;
- Milestone 4;
- final game-wide visual overhaul.

Fix concrete Milestone 3 defects discovered by verification, but do not expand product scope.

#### Review State
When complete:
- leave Package 7 **In Progress**;
- do not mark Milestone 3 complete;
- do not promote Milestone 4;
- do not begin combat work.

After architectural closure review passes, manual user UAT will run before Milestone 4 begins.

#### Final Report
Report:
1. resulting commit SHA(s);
2. fresh DB/schema/invariant verification;
3. authored content revision and projection/privacy verification;
4. generator verification;
5. exact real PHP/MySQL/browser flow exercised;
6. network/runtime request assertions;
7. Energy/idempotency results;
8. current-run/abandon results;
9. active-run Warband-lock results;
10. cross-player/security results;
11. client start/retry/recovery results;
12. map-authority/node-interaction results;
13. abandon client reconciliation results;
14. `player_revision` results;
15. responsive capture paths and visual findings;
16. every quality-gate command and actual result;
17. prototype code removed;
18. intentionally retained prototype evidence and why;
19. environment limitations/skipped optional checks;
20. unresolved Milestone 3 concerns;
21. whether the milestone is `READY FOR ARCHITECTURAL CLOSURE REVIEW`.

Do not begin another milestone.
