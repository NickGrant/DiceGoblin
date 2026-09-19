# Active Execution Issue

## Milestone 5 - Complete Farm

### Milestone 5 Package 6 - Exit-node resolution + successful run termination + authoritative Camp/RunScene/unlock reconciliation

**Status:** In Progress
**Priority:** High

#### Current Architectural Review Findings

1. **Committed Exit is not interaction-locked during Camp synchronization.** After a successful Exit response, `RunScene` retains the terminal result and correctly retries bootstrap with GET only, but while that bootstrap request is in flight or in `sync-error`, ordinary run interactions remain available. The player can still use Return to Camp, open Abandon Run, select another node, or replay a completed battle. That can navigate away with stale `bootstrap.active_run` or attempt mutations against an already-completed run. Once a definitive Exit result is accepted, enter a terminal-reconciliation lock: the only in-scene recovery action is retrying Exit bootstrap synchronization. Do not permit Camp navigation, abandon, node selection, battle replay, or another node-resolution attempt until authoritative bootstrap reconciliation succeeds (a full reload remains a valid recovery path). Add focused tests proving these actions are suppressed both while Exit bootstrap is pending and after a sync failure, and that retry performs bootstrap GET only.

2. **MySQL Exit transaction tests are not covered by current CI evidence.** The green `verify:package` run at `e11b52a15e286077518252ade13fa1c6d7137c0e` reports 761 backend tests but 418 skipped because CI does not provide `TEST_DB_DSN`; the new `FarmLootRestNodeResolutionControllerTest` integration cases therefore are not independently exercised there. Before review, run the focused Exit MySQL integration tests and the applicable Docker backend gate (normally `npm run test:backend:docker`, with the test DB provision/reset required by the repo) and report the actual results. Do not treat the non-DB CI backend pass as proof of the Exit transaction/rollback/idempotency behavior.


#### Problem

Complete the successful Farm path after Package 5:

`Combat -> Loot -> Rest -> Boss -> Exit -> Camp`

Exit is a normal authoritative run-node resolution. It ends the run successfully; it does not grant another reward, rerun Boss rewards, or introduce a claim lifecycle.

#### Authority

Use current source plus:
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-progression-state-model.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `agent/QUALITY_GATES.md`

Packages 1-5 are accepted behavior. Mountains gameplay remains Milestone 6.

#### Backend contract

Continue using only:

`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

Add authoritative `run_node_type.exit` support inside the existing `ResolveRunNodeCommand` transaction.

On a valid available Exit:
- require the persisted Exit identity to be coherent for the Farm graph and non-battle-bearing;
- complete Exit;
- transition the owned active run to successful terminal status `completed` with `ended_at`;
- unlock no child nodes;
- increment `player_revision` exactly once;
- persist the exact idempotency receipt;
- commit once through the parent command.

The player-safe response is the existing shared shape with:
- `resolution_type: "exit"`;
- completed node;
- `newly_available_node_ids: []`;
- run status `completed` and authoritative `ended_at`;
- player revision.

Exit grants no event/reward/currency/XP/unlock facts. The Mountains unlock and Boss XP already exist from Package 5.

Preserve existing ordering: exact same-key receipt replay occurs before completed/inactive lifecycle rejection. Same run/node/key returns the exact original result without another mutation or revision. A different key cannot resolve the completed Exit again.

Any failure after mutation begins must roll back node completion, run termination, revision, and receipt together.

#### Frontend contract

Extend the strict node-resolution union/parser with `resolution_type: "exit"` and the exact successful terminal-run shape.

An available Exit node in RunScene gets a clear Exit/Leave action using the existing bodyless `RunNodeResolutionAttempt` discipline:
- one run/node/idempotency-key identity;
- duplicate clicks suppressed;
- ambiguous network/5xx/malformed/semantic mismatch retries reuse the same key;
- do not create a second retry system.

After a definitive Exit result, do not calculate progression locally. Reconcile authoritative terminal state and the Package 5 progression into same-runtime Camp:
- fetch authoritative bootstrap;
- strictly parse it;
- require player revision to be at least the Exit result revision;
- require `active_run === null`;
- preserve the active squad while accepting its authoritative post-Boss level/XP summaries;
- accept authoritative `progression.unlock_ids`, including `unlock.region.mountains`;
- invalidate/stale any previously loaded Warband unit/detail caches whose XP/level summaries may predate the Boss reward;
- enter Camp only after this reconciliation succeeds.

If the Exit POST succeeded but bootstrap reconciliation is ambiguous or fails, retain the successful Exit result and retry **bootstrap synchronization only**. Do not POST Exit again merely to refresh Camp state.

Reload after successful Exit naturally boots to Camp with no active run.

Do not add Mountains run generation or a new region-selection UX in this package.

#### Required tests

Backend:
- valid Exit completes node and run atomically with `status=completed` and `ended_at`;
- no child availability and no new reward/event rows;
- revision increments once;
- same-key replay is exact and does not mutate again;
- different-key completed Exit conflicts through established semantics;
- malformed/incoherent Exit identity fails atomically;
- injected post-mutation failure rolls back Exit/run/revision/receipt;
- Combat/Boss/Loot/Rest behavior remains green;
- current-run query returns `run: null` after successful Exit.

Frontend:
- strict Exit response parser accepts only the successful terminal shape;
- Exit action sends one bodyless resolve POST and uses existing retry identity rules;
- semantic mismatch retains the same key;
- successful Exit performs authoritative bootstrap reconciliation before Camp;
- sync retry after successful Exit performs GET/bootstrap only, never another resolve POST;
- bootstrap proves no active run, Mountains owned, and active-squad level/XP values are authoritative;
- stale Warband XP/level caches cannot survive as fresh after reconciliation;
- reload with no active run routes to Camp;
- existing Combat/Boss/Loot/Rest flows remain green.

#### Verification

Use `npm run verify:package` and report its compact summary, plus focused Exit backend/frontend tests and any specialized real-stack verification required by this issue.

If practical, prove the live path through Boss completion -> Exit resolve -> authoritative bootstrap -> Camp. Package 7 owns the broader Complete-Farm integrated closure.

#### Out of scope

- new rewards or claim/acknowledgement flow;
- Mountains run generation/gameplay or region-selection redesign;
- broad Camp/Warband visual work;
- Milestone 6 implementation;
- Package 7 integrated closure/UAT.

#### Completion

Implement only Package 6. Leave it **In Progress** for architectural review and do not promote Package 7.
