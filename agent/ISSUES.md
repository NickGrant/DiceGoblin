# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish Phaser RunScene lifecycle and Camp start/resume navigation

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-4 now provide the complete server-side Milestone 3 run lifecycle needed by the client: transactional Farm start, Energy/idempotency, persisted current-run aggregate, compact bootstrap active-run summary, abandonment, and active-run Warband configuration locks.

The mounted Phaser runtime still always enters `GameScene`, Camp has no run start/resume affordance, `RunScene` remains only a placeholder, and the framework-neutral runtime client/store do not yet consume the run endpoints.

This package wires authoritative run lifecycle into the existing persistent Phaser runtime without implementing the Farm map itself.

It owns:
- safe public presentation of canonical ordinary-run Energy cost;
- strict frontend contracts for run start/current responses;
- RuntimeApiClient run start/current methods;
- GameStore active-run/current-run cache/reconciliation;
- Camp Start Farm / Resume Farm controls;
- startup routing to `RunScene` when bootstrap reports an active run;
- functional persistent `RunScene` lifecycle/loading/error/leave-to-Camp behavior;
- deterministic lifecycle verification.

Package 6 owns actual Farm graph/map rendering and abandon UI.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-energy-model.md`
- `documentation/02-systems/run-node-generation.md`
- Package 2 client-safe `run_node_type` content projection
- Package 3 `POST /api/v1/runs` response contract
- Package 4 bootstrap active-run summary and `GET /api/v1/runs/current` contract
- current `RuntimeStartup`, `RuntimeApiClient`, `GameStore`, `RuntimeViewport`
- current `BootScene`, `GameScene`, placeholder `RunScene`, and runtime scene tests
- current `CampScreen`
- existing deterministic capture/debug infrastructure

Do not use Angular gameplay routing/services or prototype Angular run pages.

#### Public run-cost presentation
Camp needs to communicate the ordinary Farm start cost before submitting the command.

Expose the canonical `config.gameplay.run_energy_cost` through the existing client content projection using an explicit allowlist.

Prefer one small safe presentation domain/shape, for example:

```text
gameplay:
  run_energy_cost: 10
```

Requirements:
- it is generated from the same canonical Git JSON used by the backend;
- `ClientContentProjector` explicitly allowlists the field;
- `ClientContentRegistry` strictly validates it as a positive integer;
- no other server-private gameplay config becomes public accidentally;
- private generation topology remains private;
- server run-start validation remains authoritative and does not trust the client value.

Do not hard-code `10` in Camp or RunScene.

#### Frontend run contracts
Add a narrow framework-neutral run contract module rather than mixing large parsing logic into Phaser scenes.

Strictly parse the Package 3 start response:

```text
run:
  id
  region_id
  squad_id
  status: active
energy:
  current
  normal_max
  regeneration_per_hour
  regeneration_interval_seconds
  last_regeneration_at
  next_regeneration_at
  fully_regenerated_at
player_revision
```

Strictly parse Package 4 current-run response:

```text
run: null | {
  id
  region_id
  squad_id
  status: active
  created_at
  nodes[]
  edges[]
  units[]
}
player_revision
```

Validate exact known shapes/fields and canonical positive ID strings.

For current run validate at minimum:
- stable region/node-type IDs;
- contiguous unique `node_index` starting at zero;
- unique positive node IDs;
- allowed runtime statuses currently returned by the backend;
- completion timestamp/status coherence;
- safe integer position column/row;
- edge endpoints exist in returned nodes;
- no self/duplicate edge;
- every returned node is reachable from node index 0;
- unit IDs unique/positive;
- `current_hp` null or non-negative integer.

Resolve `region_id` and every `node_type_id` through `ClientContentRegistry`. Unknown projected authored references are integrity/contract failure.

Do not apply Package 2 fresh-generation availability rules to current runtime state.

#### Runtime API
Extend `RuntimeApiClient` with:

- start Farm run through `POST /api/v1/runs`;
- current run through `GET /api/v1/runs/current`.

Start requirements:
- credentials include;
- bootstrap CSRF token;
- `Idempotency-Key`;
- exact `{ region_id }` body;
- strict success parsing.

Current query:
- credentials include;
- no mutation/CSRF;
- strict success parsing.

Do not add frontend abandon yet; Package 6 owns the abandon interaction.

#### Run-start idempotency in the client
Use the same deliberate ambiguous-retry discipline established by squad creation.

For one Start Farm attempt:
- generate one valid opaque idempotency key;
- submit the exact Farm region request;
- while a request is in flight, prevent duplicate starts;
- if the request fails ambiguously at the network/transport level, retry of that unchanged attempt must reuse the same key;
- after definitive success, discard the key;
- after a definitive server rejection, a later deliberate new attempt may use a new key.

Do not repeatedly create new keys while retrying an ambiguous request because that could convert a lost success response into an `active_run_exists` conflict instead of an idempotent replay.

#### GameStore run state
Add a narrow active/current-run state model to the existing GameStore.

Do not introduce another state-management framework.

The full current-run aggregate should support states equivalent to:
- not loaded;
- loading;
- fresh;
- stale;
- error.

Requirements:
- bootstrap active-run summary remains the startup routing signal;
- current-run detail loads lazily when entering/resuming `RunScene`;
- concurrent current-run loads deduplicate;
- fresh current-run state is reused while authoritative revision has not invalidated it;
- `GameStore.clear()` clears current-run state/in-flight requests;
- stale/error recovery is deliberate;
- ordinary scene switching does not clear it.

#### Reconcile successful start
After a strictly valid successful run-start response:
- adopt the authoritative `player_revision`;
- replace bootstrap/player Energy with the returned authoritative Energy view;
- set bootstrap `active_run` from the returned run summary;
- ensure returned run `squad_id` agrees with the current authoritative bootstrap active squad;
- do not change Warband units/dice/squads caches merely because the run started;
- do not fabricate the full run graph from the known private/static Farm definition.

Then enter `RunScene`, which loads `GET /api/v1/runs/current` to obtain the persisted aggregate.

Never regenerate topology in the browser.

Reject revision regression or impossible summary disagreement as integrity failure rather than silently accepting it.

#### Reconcile current-run query
A successful current-run response is authoritative at its returned revision.

If it returns an active run:
- it must agree with the bootstrap active-run summary on ID/region/squad when both refer to the same current authoritative revision;
- adopt the full aggregate as fresh;
- adopt a newer/equal valid `player_revision` according to existing stale-cache rules.

If it returns `run: null`:
- this may legitimately mean another tab/session abandoned the run after bootstrap;
- if its revision is newer than the cached bootstrap state, clear `active_run`, adopt the newer revision, and route/recover to Camp;
- do not pretend an active run still exists.

If the response revision regresses or an equal-revision response contradicts bootstrap active-run summary, treat it as integrity/stale state and do not invent a winner.

#### Startup routing
Once RuntimeStartup is `ready`:
- bootstrap `active_run === null` routes to `GameScene`/Camp as today;
- bootstrap `active_run !== null` routes directly to the existing `RunScene`.

Do not fetch the full current-run aggregate before the one-shot bootstrap/content startup completes.

Do not refetch bootstrap just to choose the scene.

Do not create a new Phaser runtime/canvas.

#### Camp controls
Extend the Phaser-owned Camp screen.

When there is no active run:
- present a clear **Start Farm** / **Enter Farm** control;
- present the canonical Energy cost from client content;
- show enough Energy context for the player to understand the spend;
- do not expose private generation information.

When there is an active run:
- present **Resume Farm** instead of another start control;
- do not issue another run-start request.

Starting requires an active squad server-side. Client presentation may disable the start control when bootstrap clearly has no active squad or clearly lacks effective displayed Energy, but those client checks are usability only. The backend remains authoritative and server errors must still be handled.

The Camp screen must not fetch Warband collections simply to start a run.

#### Start success and failure UX
Start Farm needs intentional states:
- ready;
- submitting;
- ambiguous/network failure with retry;
- authoritative rejection such as insufficient Energy or invalid configuration;
- malformed/integrity response.

Prevent double submission.

On successful start, transition to `RunScene` only after authoritative store reconciliation succeeds.

Do not optimistically subtract Energy or set an active run before the response succeeds.

Use concise player-facing error text; do not display raw server exception content.

#### RunScene lifecycle
Replace the current placeholder with a functional lifecycle shell only.

On entry:
- verify bootstrap/store believes there is an active run;
- load/deduplicate `GET /api/v1/runs/current` when the full aggregate is not already fresh;
- show loading/error/retry state as appropriate;
- once loaded, present a minimal run shell identifying the authored region and that the run is active;
- do not render the five-node Farm graph yet.

Resolve region presentation through `ClientContentRegistry`.

Provide a clear **Return to Camp** / leave-run-screen control that switches back to `GameScene` without abandoning the run.

The active run remains authoritative and resumable.

Package 6 will replace/extend this shell with the actual map and abandon interaction.

#### RunScene navigation
`GameScene` and `RunScene` are real Phaser scenes inside the same already-mounted Phaser game.

Required behavior:
- Camp Start success -> `RunScene`;
- Camp Resume -> `RunScene` without starting another run;
- RunScene Return to Camp -> `GameScene` Camp;
- Camp then shows Resume because active-run state persists;
- a browser reload with bootstrap active run enters `RunScene` directly;
- runtime-lifetime `RuntimeStartup`, GameStore, ClientContentRegistry, viewport, Phaser game, and canvas remain the same objects across scene changes.

Do not use the GameScene screen navigator to fake RunScene as another Camp/Warband screen.

Do not use Angular routing.

#### Orientation/responsive behavior
Reuse existing RuntimeViewport and touch-first portrait gate behavior.

RunScene lifecycle UI must be functional at:
- Compact `844x390`;
- Standard `1600x900`;
- Wide `2560x1080`.

Portrait gate should suspend RunScene interaction just as it does other gameplay without clearing run cache/state.

Returning to landscape resumes the same RunScene/run state.

Keep presentation simple; Package 6 owns the actual map visual composition.

#### Deterministic debug/capture support
Extend the existing debug/capture infrastructure only enough to deterministically enter the RunScene lifecycle shell with contract-valid bootstrap/current-run data.

Generate and visually inspect lifecycle-shell captures at Compact, Standard, and Wide if the existing capture workflow makes that inexpensive. These captures are useful but should not become Farm-map design work.

Do not create a second fake run data model for screenshots; use the real frontend run contracts/store shape.

#### Existing Warband behavior
Starting an active run will make certain Warband commands server-locked by Package 4.

Package 5 does not need to redesign the Warband editor UI to proactively disable all locked operations; server rejection remains authoritative. If a small existing error-message mapping is needed so a 409 lock response is understandable when the player returns to Warband during an active run, that narrow adjustment is allowed.

Do not expand this into a new Warband package.

#### Tests
At minimum prove:
- public content contains only explicit `run_energy_cost` gameplay presentation and does not leak other private gameplay/generation fields;
- client content parser rejects malformed/non-positive run cost;
- run-start API uses credentials, CSRF, exact body, and Idempotency-Key;
- current-run API uses authenticated GET;
- strict start parser rejects malformed/private/unexpected shapes;
- strict current parser validates IDs, authored references, graph connectivity, position/status/completion/unit state;
- current parser accepts legitimate progressed node statuses rather than requiring fresh-generation statuses;
- start mutation does not alter committed GameStore before success;
- start reconciliation updates Energy, revision, and active-run summary only;
- start rejects revision regression/squad disagreement;
- ambiguous retry reuses idempotency key;
- duplicate input while starting cannot issue duplicate requests;
- bootstrap without active run starts in GameScene;
- bootstrap with active run starts in RunScene;
- RunScene lazily loads current run once;
- concurrent/current repeated loads deduplicate/use fresh cache;
- current-run `null` at newer revision clears stale active-run summary and returns/recover to Camp;
- equal-revision contradictory current state fails safely;
- Camp Resume issues no start request;
- RunScene Return to Camp preserves active run and full current-run cache;
- Camp after return presents Resume;
- navigation does not refetch bootstrap or game-content;
- same Phaser runtime/canvas/startup/store survives GameScene <-> RunScene transitions;
- resize/orientation changes preserve run lifecycle state;
- existing Camp/Warband navigation remains green.

#### Real-stack verification
Where practical use the controlled Warband fixture against real PHP/MySQL and browser runtime:

fresh/authenticated `/game`
-> Camp
-> Start Farm
-> authoritative Energy drop
-> RunScene current-run load
-> Return to Camp
-> Resume Run
-> browser reload
-> bootstrap routes directly back into the same run.

Verify only one run was created and only one Energy spend/revision occurred for the start.

Do not abandon or resolve nodes in this package.

#### Documentation
Update existing canonical docs only when the client lifecycle boundary changes accepted current truth.

Do not create package report/UAT-history documents.

#### Explicitly Out of Scope
Do not implement:
- Farm graph/node rendering;
- clickable/selectable nodes;
- abandon UI/API client call;
- node resolution;
- combat;
- rewards;
- Rest/Loot/Boss/Exit mechanics;
- current HP calculation;
- battle playback;
- final visual overhaul;
- Milestone 4.

Do not migrate prototype Angular run pages.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum:
- content validation/generation because public projection changes;
- focused run contract/API/store tests;
- runtime scene/startup navigation tests;
- Camp tests;
- orientation/responsive lifecycle tests;
- existing Warband regressions;
- full frontend suite;
- production frontend build;
- bundle check;
- backend content/projector tests if projection code changes;
- Package 3/4 backend regressions if any backend contract files change;
- docs/context checks when documentation changes.

Use deterministic lifecycle captures if implemented.

Do not claim a gate passed unless it actually ran.

#### Review State
When complete:
- leave Package 5 **In Progress**;
- do not mark it complete;
- do not promote Package 6;
- do not begin Farm-map/abandon UX work.

Architectural review decides completion.

#### Final Report
Report:
1. commit SHA;
2. public run-cost projection shape;
3. strict run start/current contracts;
4. RuntimeApiClient methods and idempotency retry behavior;
5. GameStore run state/reconciliation behavior;
6. startup scene-routing rule;
7. Camp Start/Resume interaction;
8. RunScene lifecycle/loading/error/return behavior;
9. responsive/orientation behavior;
10. deterministic capture results if run;
11. real-stack verification if run;
12. quality gates actually executed/results;
13. unresolved concern, if any.

Do not begin another package.
