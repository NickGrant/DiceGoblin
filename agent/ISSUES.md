# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish Phaser Farm map and abandon/resume UX

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-5 now provide the complete authoritative lifecycle needed to enter and resume a Farm run:
- normalized persisted run graph/participants;
- canonical private Farm generation;
- transactional idempotent start with Energy spend;
- current-run read and retry-safe abandon backend;
- active-run Warband locks;
- bootstrap startup summary;
- persistent Phaser `RunScene` lifecycle with Camp Start/Resume and reload/resume.

`RunScene` still renders only a lifecycle shell. The player cannot see the persisted Farm graph or intentionally abandon the run from gameplay.

This package renders the authoritative current-run graph and adds the client-side abandon interaction/reconciliation. It does **not** resolve nodes or begin combat.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/02-systems/run-node-generation.md`
- current Package 4 `GET /api/v1/runs/current` and abandon contracts
- current Package 5 `run-contracts.ts`, `RuntimeApiClient`, `GameStore`, `CampScreen`, `RunScene`
- `ClientContentRegistry` projected regions and `run_node_type.*` definitions
- `RuntimeViewport` and portrait-gate behavior
- deterministic capture/debug infrastructure

Do not use prototype Angular run pages, prototype map services, or private generation JSON in the browser.

#### Core outcome
A player with an active Farm run can:
1. enter/resume `RunScene`;
2. see the exact persisted Farm nodes and edges returned by `GET /api/v1/runs/current`;
3. understand each node's authored type/presentation and authoritative runtime status;
4. return to Camp without abandoning;
5. resume the same run/map;
6. explicitly abandon with confirmation;
7. return to Camp with the run cleared locally only after authoritative abandon success.

The five-node graph currently appears as the persisted Farm path:

`Combat -> Loot -> Rest -> Boss -> Exit`

but the client must render the returned graph rather than reconstructing this sequence from knowledge of canonical generation content.

#### Authoritative map source
The only map authority is `GameStore.currentRun.data`, populated through the strict Package 5 `/runs/current` contract.

Do not:
- regenerate the Farm graph;
- import/read private `run_generation.*` content;
- infer missing nodes/edges;
- synthesize IDs;
- assume the graph will always remain exactly five nodes in the rendering architecture.

Use the returned:
- node IDs;
- node indexes;
- node type IDs;
- status;
- completed timestamp;
- safe `{column,row}` positions;
- edge endpoint IDs.

Resolve presentation through projected authored `run_node_type.*` and region definitions.

#### Farm map presentation
Replace/extend the lifecycle shell with a functional map view.

At minimum show:
- authored Farm region name;
- visible graph edges;
- one visual node for every returned persisted node;
- authored node-type display name;
- authored icon/art presentation where practical from the safe `icon_key` contract;
- clear visual distinction between `locked`, `available`, and `completed`;
- run-level controls for Return to Camp and Abandon Run;
- useful run identity/status presentation without making raw database IDs the primary UX.

The visual implementation may remain deliberately simple. Final game-wide art direction is still deferred.

Do not hard-code primary labels such as COMBAT/LOOT/REST/BOSS/EXIT from node indexes. Resolve them from authored projected node types.

#### Layout
Use returned node positions to place nodes in a logical map coordinate system.

For the current Farm graph the authored columns produce a left-to-right path. Scale/translate that logical map into the current safe viewport.

Requirements:
- Compact `844x390` remains fully usable;
- Standard `1600x900` remains fully usable;
- Wide `2560x1080` remains fully usable;
- edges connect the visual node centers/endpoints coherently;
- controls do not overlap map nodes;
- all graph content remains reachable/visible for the current Farm graph;
- viewport reflow does not alter authoritative map state.

Do not create CSS/browser scrolling for the Phaser map.

A future larger graph may need pan/zoom, but do not build speculative map navigation unless the current returned graph cannot be presented safely without it.

#### Node interaction boundary
Milestone 3 does not resolve nodes.

Nodes may be selectable/focusable for presentation only if that improves clarity. If selection exists, it may show:
- authored node type name;
- authored description;
- authoritative status such as Locked / Ready / Completed.

Selection must remain local presentation state and survive ordinary reflow while `RunScene` remains active.

Do not:
- POST a node action;
- change node status locally;
- mark nodes completed;
- unlock downstream nodes;
- start BattleScene;
- award loot/rewards;
- heal at Rest;
- execute Boss/Exit behavior;
- pretend an available node has been entered.

An available node should look ready, but there is no resolution action in this package.

Do not surface internal milestone/developer terminology to players as primary UX.

#### Progress/current-position semantics
There is currently no canonical separate `current_node_id` persisted by Milestone 3.

Do not invent one.

Represent progression only from the authoritative node statuses actually returned:
- completed nodes are completed;
- available nodes are ready;
- locked nodes are locked.

Do not call a node "current" unless the server contract later introduces that concept.

#### Abandon RuntimeApiClient contract
Add:

`POST /api/v1/runs/:runId/abandon`

Requirements:
- credentials include;
- bootstrap CSRF token;
- no request body;
- no `Idempotency-Key`;
- canonical positive run ID;
- strict success parsing.

Parse the Package 4 authoritative response exactly:

```text
run:
  id
  region_id
  squad_id
  status: abandoned
  ended_at
active_run: null
player_revision
```

Validate:
- exact known fields;
- positive IDs;
- projected authored region;
- terminal abandoned status;
- valid UTC `ended_at`;
- `active_run` exactly null;
- non-negative revision.

Do not accept Energy in the abandon response; abandonment does not refund or mutate Energy.

#### Abandon retry behavior
The backend abandon transition is naturally idempotent for the same owned run ID.

Client behavior:
- one in-flight abandon at a time;
- network/malformed/5xx ambiguity may retry the same abandon endpoint/run ID;
- no new idempotency key is generated or required;
- do not optimistically clear active-run state while the result is ambiguous;
- a valid success may be either the real transition or the backend's already-abandoned no-op retry result.

A deliberate 4xx/unauthorized failure should display safe failure/recovery messaging and preserve the cached active run unless/until authoritative current-run state later proves otherwise.

Do not display raw server exception text.

#### Abandon confirmation
Abandon must require explicit confirmation.

The confirmation should communicate that:
- the active run will end;
- spent Energy is not refunded.

It must provide clear Confirm and Cancel actions.

While confirmation or submission owns interaction:
- prevent duplicate abandon submissions;
- block accidental node/Return-to-Camp actions behind the confirmation;
- preserve current-run/map state.

Touch-first interaction must be usable without relying on hover.

#### GameStore abandon reconciliation
Add a narrow authoritative reconciliation method for a valid abandon result.

Before mutation, committed state remains unchanged.

On valid success:
- reject revision regression;
- verify returned run ID/region/squad agrees with the cached active/current run when those states are at the same authoritative revision;
- adopt returned `player_revision`;
- set bootstrap `active_run` to null;
- set current-run cache to fresh `data: null`;
- preserve bootstrap Energy exactly as cached before abandon;
- preserve Warband units/dice/squads caches;
- preserve active squad;
- do not globally refetch bootstrap/profile/Warband.

After successful reconciliation, transition to `GameScene`/Camp.

If a strictly valid abandon response cannot reconcile with local authoritative state, enter a safe reload/recovery state. Do not clear the run optimistically and do not issue another unrelated mutation.

#### Stale other-tab/session behavior
Package 5 already supports `/runs/current -> run:null` at a newer revision clearing a stale bootstrap active-run summary.

Preserve that behavior.

If another tab abandons while this RunScene is open, an explicit retry/refresh/current-run reload that receives the newer `run:null` must recover to Camp.

Do not add polling merely for this package.

#### Return to Camp / Resume
Preserve the Package 5 distinction:
- Return to Camp does **not** abandon;
- Camp displays Resume while bootstrap has an active run;
- Resume issues no `POST /runs`;
- re-entering RunScene reuses fresh current-run cache when still authoritative;
- browser reload uses bootstrap active-run summary and then reloads the persisted current aggregate.

Adding map presentation must not regress this lifecycle.

#### Portrait gate
The existing touch-first portrait gate must suspend RunScene map interaction and confirmations without destroying:
- current-run cache;
- local node selection/presentation state;
- abandon confirmation state where practical and safe.

Return to landscape should reflow the same map/run rather than reload/regenerate it.

#### Error/loading states
Retain and refine clear RunScene states for:
- current-run loading;
- current-run request failure with Retry;
- current-run integrity/malformed failure;
- stale active-run recovery to Camp;
- abandon confirmation;
- abandon submitting;
- abandon retryable ambiguous failure;
- abandon authoritative rejection/recovery required.

Do not hide a failed run query behind an empty/generated map.

#### Debug/capture support
Extend deterministic capture support for the actual Farm map using contract-valid current-run fixture data.

Add captures for:
- Farm map Compact `844x390`;
- Farm map Standard `1600x900`;
- Farm map Wide `2560x1080`;
- touch-first portrait gate if the existing capture flow supports it cheaply;
- abandon confirmation at at least one representative landscape size.

Use the same frontend run contracts/store shape as production. Do not create a second map model just for screenshots.

Visually inspect captures for:
- clipped nodes/labels;
- edges missing node centers;
- status distinction;
- control overlap;
- unreadable node details;
- unsafe confirmation controls;
- raw IDs dominating UX;
- portrait interaction leaking through gate.

#### Tests
At minimum prove:
- map model/layout consumes returned node positions/edges rather than fixed five-node indexes;
- authored node presentation comes from ClientContentRegistry;
- locked/available/completed states are visually/model-distinct;
- legitimate completed/progressed persisted graph still renders;
- local selection does not mutate GameStore/current-run authority;
- map reflow preserves local selection and current-run cache;
- node interaction issues no mutation and does not start BattleScene;
- abandon API uses POST, credentials, CSRF, exact route, no body, no Idempotency-Key;
- strict abandon parser rejects malformed/additional/Energy-bearing responses;
- opening abandon confirmation does not mutate state;
- Cancel preserves the active run;
- Confirm prevents duplicate submissions;
- ambiguous abandon failure preserves active run and permits retry of the same run ID;
- successful abandon reconciliation adopts revision, clears active-run/current-run state, preserves Energy and Warband caches, and enters Camp;
- revision regression or response identity disagreement fails safely;
- Return to Camp remains non-abandoning;
- Camp Resume still issues no run-start mutation;
- fresh current-run cache is reused on Resume;
- newer `run:null` recovery still routes to Camp;
- Package 5 run-start ambiguous-idempotency tests remain green;
- GameScene/RunScene still share one mounted Phaser runtime/store/canvas;
- responsive map layouts fit Compact/Standard/Wide safe bounds;
- portrait gate preserves authoritative/local presentation state.

#### Real-stack verification
Where practical, use controlled fixture + real PHP/MySQL/browser runtime:

fresh/authenticated Camp
-> Start Farm
-> persisted Farm map appears
-> Return to Camp
-> Resume same map/run ID
-> reload -> same persisted map/run ID
-> open Abandon confirmation -> Cancel -> run remains active
-> open confirmation -> Confirm
-> authoritative abandon
-> Camp with Start Farm available again
-> verify Energy was not refunded
-> verify persisted run row is terminal/history retained.

Do not resolve any node.

#### Documentation
Update existing canonical docs only where this package changes accepted map/abandon client truth.

Do not create package-report/history/UAT documents.

#### Explicitly Out of Scope
Do not implement:
- node resolution API/client mutation;
- combat;
- `BattleScene` entry;
- enemy encounters;
- loot/reward claims;
- Rest mechanics;
- Boss mechanics;
- Exit/completion mechanics;
- unlocking/completing nodes;
- current HP calculation;
- run modifiers;
- battle playback;
- final visual overhaul;
- Milestone 4.

Do not modify backend run lifecycle contracts unless a concrete Package 6 blocker/defect is discovered.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum:
- focused abandon contract/API/store tests;
- Farm map model/layout/render tests;
- RunScene interaction tests;
- Package 5 lifecycle/start-idempotency regressions;
- Camp Resume regressions;
- runtime/orientation tests;
- full frontend suite;
- production frontend build;
- bundle check;
- deterministic Compact/Standard/Wide Farm captures and visual inspection;
- abandon-confirmation capture;
- relevant backend Package 4 abandon regression if backend files change;
- docs/context checks when applicable.

Real-stack verification is strongly useful when practical.

Do not claim a gate passed unless it actually ran.

#### Review State
When complete:
- leave Package 6 **In Progress**;
- do not mark it complete;
- do not promote Package 7;
- do not begin integrated closure or Milestone 4.

Architectural review decides completion.

#### Final Report
Report:
1. commit SHA;
2. map presentation/layout approach;
3. proof that authoritative persisted graph drives rendering;
4. node presentation/status behavior;
5. confirmation interaction;
6. abandon API/strict response contract;
7. ambiguous abandon retry behavior;
8. GameStore abandon reconciliation and Energy/cache preservation;
9. Return/Resume/reload behavior;
10. responsive/orientation behavior;
11. capture paths and visual inspection findings;
12. real-stack verification if run;
13. quality gates actually executed/results;
14. unresolved concern, if any.

Do not begin another package.
