# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 6 - Phaser BattleScene playback lifecycle

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-5 now establish the complete authoritative backend combat path and immutable playback read contract. A Farm combat node can be resolved exactly once, its finalized battle and post-combat run state are persisted atomically, and the browser can retrieve a presentation-safe historical playback derived only from that immutable battle record.

The remaining gap is client gameplay presentation. The Farm map still treats combat-node selection as informational only, `BattleScene` is still a placeholder, and the browser has no idempotent node-resolution mutation flow or reload-safe way to continue presenting a just-finalized battle.

This package establishes the **combat initiation + persisted playback presentation lifecycle**. It does not make Phaser a combat authority and does not own the final post-playback Continue/reconciliation flow; that remains Package 7.

#### Core outcome
Implement this browser lifecycle:

`RunScene available Combat -> Resolve Combat -> authoritative battle ID -> fetch persisted playback -> BattleScene -> autoplay exact semantic playback -> playback-complete presentation state`

Also support:
- watching an already-completed active-run combat node through its persisted `battle_id` without resolving it again;
- browser reload during/after playback safely returning to the same retained battle presentation without rerunning combat;
- retrying ambiguous node-resolution transport failures with the same logical idempotency key;
- retrying playback reads without another node-resolution POST.

Package 7 will add the final result/Continue behavior and authoritative return to RunScene/Camp.

#### Required context
Read before implementation:
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/02-systems/combat-resolution.md`
- `documentation/02-systems/target-resolution.md`
- current `RunScene`/run-map model;
- current `BattleScene` placeholder and Phaser scene registration;
- `RuntimeStartup`, `GameStore`, `RuntimeApiClient`, `RuntimeViewport`;
- Package 4 node-resolution response contract;
- Package 5 battle-playback/current-run contracts.

Do not use prototype Angular battle/run pages or prototype battle log/claim services as runtime architecture.

#### Node-resolution frontend contract
Add a strict framework-neutral parser/type for Package 4's successful node-resolution response.

Validate exactly the accepted response facts:
- battle ID;
- outcome;
- engine/playback versions;
- ending round/tick;
- resolved node ID/status/completed timestamp;
- newly available node IDs;
- terminal player HP keyed by canonical owned unit ID;
- resulting run ID/status/terminal timestamp;
- `player_revision`.

Reject:
- missing/extra fields;
- malformed IDs/timestamps;
- unsupported versions/outcomes;
- duplicate newly available IDs;
- invalid/non-integer HP;
- `run.status = active` with an `ended_at` value;
- `run.status = failed` without an `ended_at` value;
- node status other than `completed`;
- impossible revision values.

This is transport/coherence validation only. Do not recompute combat outcome, HP, graph unlocks, or battle mechanics in TypeScript.

#### Runtime API node resolution
Add:

`POST /api/v1/runs/:runId/nodes/:nodeId/resolve`

through `RuntimeApiClient`.

Requirements:
- canonical run/node IDs;
- credentials;
- current bootstrap CSRF token;
- `Idempotency-Key`;
- **no request body**;
- strict response parsing through the new contract.

Do not add another battle-resolution endpoint.

#### One logical combat attempt
One user action to resolve one combat node owns one idempotency key and exact run/node identity.

Prevent simultaneous duplicate submissions.

Ambiguous outcomes must retain that same logical attempt/key:
- network failure;
- malformed success response;
- HTTP 5xx;
- other transport state where the server may have committed but the client cannot safely know the result.

Retry submits the exact same run/node with the same key.

Definitive domain/auth 4xx rejection may release the attempt when appropriate.

If the server reports the node already resolved under another attempt, do **not** blindly create another POST loop. Refresh authoritative current-run state when available and use its `battle_id` discovery if it identifies the finalized battle; otherwise require recovery/reload.

Never manufacture terminal HP/node/run state after an ambiguous result.

#### Farm-map combat affordance
Retain node selection as presentation behavior, but add a deliberate action in the selected-node detail area.

For an **available** `run_node_type.combat` node with `battle_id = null`:
- show an action such as `ENTER COMBAT` / `FIGHT`;
- make clear that selecting/entering combat will resolve the fight authoritatively;
- action is disabled while submitting;
- use the shared pointer-cursor convention only when actionable.

For a **completed** combat node with a non-null `battle_id`:
- expose `WATCH BATTLE` / `REPLAY BATTLE`;
- this must issue **no** node-resolution POST;
- it sets the presentation target and reads the persisted playback directly.

Locked combat nodes remain non-resolvable.

Non-combat nodes remain Milestone 5+ and must not gain fake resolution actions.

Do not locally complete/unlock nodes when the user presses Fight. The Package 4 server response is authoritative gameplay state; Package 7 owns final run-cache reconciliation after playback.

#### Battle presentation marker
Add a deliberately **ephemeral client-only presentation marker** for a retained battle that should be watched.

Session-scoped browser storage is appropriate because it survives an ordinary reload while remaining presentation state rather than durable gameplay authority.

Store only enough identity to safely rediscover presentation, for example:
- authenticated account/user identity from bootstrap;
- battle ID;
- run ID;
- run-node ID.

Do not store:
- playback events;
- combat seed;
- computed HP/status state;
- node completion authority;
- rewards;
- a server-style `pending`/`claimed` flag.

Treat the marker as untrusted navigation/presentation state:
- only honor it after bootstrap identifies the same account;
- the owned playback GET remains the authorization source;
- verify returned battle run/node identity matches the marker;
- stale/corrupt/foreign marker data must not expose data or mutate gameplay;
- clear incompatible marker state and recover safely.

Do not add any server-side playback-progress, acknowledgement, claim, or pending-presentation table/API.

#### Startup/reload behavior
Extend startup routing carefully.

Normal Package 5 behavior remains:
- no marker + no active run -> Camp;
- no marker + active run -> RunScene.

When a valid presentation marker for the currently authenticated account exists:
- route to the real persistent `BattleScene`;
- `BattleScene` fetches the persisted playback by battle ID;
- it verifies battle/run/node identity against the marker before presentation.

This must also work when the battle outcome was defeat/stalemate and bootstrap correctly has `active_run: null`, because retained playback remains readable after terminal run failure.

If the marker is stale/missing/foreign for the current account:
- do not issue a combat resolve request;
- clear the unusable marker as appropriate;
- recover to the normal bootstrap-derived Camp/RunScene route.

A reload does **not** need to remember the exact animation frame/event index. Restarting the same immutable playback from the beginning is acceptable and preferred over inventing authoritative playback-progress state.

#### BattleScene authority boundary
Replace the placeholder with a functional Phaser playback scene.

`BattleScene` consumes only the strict Package 5 `BattlePlaybackResult`.

It must never:
- call CombatEngine logic;
- roll dice;
- choose targets;
- calculate hit chance;
- calculate damage/healing;
- decide status application/resistance;
- decide deaths/outcome;
- modify authoritative run/node/HP state.

The scene may maintain **ephemeral presentation state** by applying facts already contained in persisted events, for example:
- `damage_dealt.hp_after` updates the displayed target HP;
- `death` marks a combatant visually defeated;
- status applied/removed events alter displayed status badges;
- dice events show the persisted rolls;
- action/hit/damage events drive captions/highlights.

Do not derive missing gameplay facts. If a required presentation fact is unavailable/incoherent despite the strict parser, fail presentation safely rather than simulate it.

#### Initial battle presentation
Use persisted playback participants for all combatant presentation:
- historical display name;
- historical art key;
- side;
- persisted battle-start position;
- initial/max HP;
- terminal facts only for end/result presentation.

Do not look up current unit names/types/content as a replacement for historical identity.

Asset resolution may use the persisted `art_key` against existing static assets. If an asset is unavailable, use a bounded neutral fallback without changing combat facts.

Do not expose raw internal IDs as the main player-facing combatant label.

#### Playback scheduler
Create a small presentation-only playback controller/model independent of Phaser drawing where practical.

It should:
- start at the first persisted event;
- advance events in exact sequence order;
- use event type/facts to choose presentation timing only;
- never reorder or skip authoritative events internally;
- support deterministic tests with a fake/controlled clock or explicit advance calls;
- expose a clean `playing`, `paused` if needed, `complete`, and error state appropriate to the implementation.

Do not map server `tick` directly to real wall-clock duration as if it were a physics simulation. Server ticks are semantic ordering facts. Use client presentation durations appropriate to event classes.

A modest default pace is sufficient. Optional minimal speed-up/skip-to-end presentation control is acceptable only if it consumes the same recorded event sequence and does not alter gameplay authority.

#### Minimum visible playback behavior
This package does not need final combat art polish, but playback must be understandable.

At minimum present:
- both sides positioned coherently from persisted `{x,y}`;
- names and HP bars/values;
- current acting combatant/action;
- target highlighting where event facts provide it;
- persisted dice roll facts when present;
- hit/miss/critical indication;
- recorded damage and HP change;
- death/defeated indication;
- recorded status applied/resisted/removed feedback;
- battle outcome when `battle_ended` is reached.

A compact event caption/log area is appropriate.

Do not reconstruct a prose combat log server-side.

#### Playback completion boundary
When the `battle_ended` event is consumed:
- enter a clear local `playback-complete` presentation state;
- show the authoritative outcome;
- do not automatically mutate/reload RunScene state;
- do not award rewards or mark anything claimed;
- do not clear the presentation marker yet if doing so would make reload-after-playback lose the retained result presentation before Package 7 can reconcile it.

Package 7 owns final result UX, marker clearing policy, authoritative current-run reconciliation, and Continue destination.

A temporary non-authoritative label such as `Playback complete` is acceptable for this package. Do not build the final Milestone 4 result screen early.

#### Current-run cache handling after resolution
After a valid node-resolution response:
- do not optimistically edit node statuses/HP/edges in the cached `CurrentRun`;
- retain the authoritative resolution response for BattleScene/Package 7 as presentation/reconciliation context if useful;
- mark the current-run cache stale rather than pretending the old pre-combat graph remains fresh;
- do not refetch bootstrap/profile/Warband merely to enter BattleScene.

Package 7 will reconcile the post-battle authoritative run state before leaving the result flow.

#### Playback fetch/retry
Once a battle ID is known, playback fetch is a GET and must be independent of node-resolution retry state.

If playback GET has a network/5xx failure:
- keep the presentation marker;
- show retry;
- retry the GET only;
- never issue another node-resolution POST just because playback could not be loaded.

If playback is malformed:
- treat it as presentation integrity failure;
- do not simulate/reconstruct it;
- keep enough recovery information for reload/retry.

If playback is non-disclosing not-found after a supposedly successful resolution:
- do not rerun combat;
- enter recovery/integrity handling.

#### Defeat/stalemate lifecycle
A failed combat may already have made the server run terminal before BattleScene starts.

BattleScene must still play the retained defeat/stalemate battle through the Package 5 read contract.

Do not require an active-run GameStore object to render BattleScene.

Do not redirect to Camp merely because bootstrap/current-run says there is no active run while a valid retained-battle presentation marker exists.

Package 7 owns the eventual Continue -> Camp behavior after terminal battle playback.

#### Viewport/orientation
Use the existing persistent RuntimeViewport and orientation gate.

Verify BattleScene at:
- Compact `844×390`;
- Standard `1600×900`;
- Wide `2560×1080`.

Touch-first portrait gate must block interaction and **pause/suspend presentation advancement** so the player does not miss playback behind the gate. Returning to landscape continues safely from the same local presentation state.

Desktop/fine-pointer portrait remains subject to the existing viewport policy rather than a new BattleScene-specific rule.

Do not remount Phaser or create a second canvas.

#### Tests
At minimum prove:
- strict node-resolution parser accepts a real Package 4 response and rejects malformed/extra fields, IDs, lifecycle/HP/version/outcome/revision incoherence;
- RuntimeApiClient node resolve sends exact URL/method, CSRF, idempotency key, credentials, and **no request body**;
- one logical resolution attempt preserves the same key/run/node across network, malformed-response, and 5xx ambiguity;
- simultaneous Fight submissions are prevented;
- definitive rejection does not masquerade as an ambiguous committed battle;
- successful resolution records the battle presentation marker before playback navigation/fetch;
- ambiguous resolution never optimistically changes Energy, HP, node status, or unlocks;
- already-completed combat with `battle_id` uses Watch/Replay and sends no resolution POST;
- playback GET retry never sends resolution POST;
- marker is scoped to authenticated account identity and rejects stale/mismatched identity;
- reload with valid marker routes to BattleScene for both active-run victory and terminal-run defeat/stalemate scenarios;
- reload restarts or safely resumes presentation from persisted playback without combat rerun;
- BattleScene initial participants come from playback projection, not current Warband/content state;
- playback events are consumed exactly in persisted sequence order;
- presentation state uses recorded event facts rather than recalculating hit/damage/target/status/outcome;
- `battle_ended` produces playback-complete state with the persisted outcome;
- portrait gate suspends playback advancement and landscape resume continues correctly;
- current-run cache becomes stale after resolution but is not locally rewritten with guessed combat state;
- scene transition does not refetch bootstrap/game-content or remount Phaser;
- existing RunScene Return/Resume and M3 interaction behavior remain intact.

#### Captures / visual verification
Produce deterministic captures for at least:
- BattleScene initial/early playback at Standard;
- mid-combat event at Standard;
- playback-complete victory state;
- Compact battle playback;
- Wide battle playback;
- portrait gate while BattleScene has loaded playback.

Where practical also capture defeat playback-complete using a deterministic fixture/test harness.

Visually inspect:
- combatants fit and are distinguishable;
- HP is readable;
- action/dice/damage feedback is understandable;
- event captions do not dominate the screen;
- Compact does not overlap controls/combatants;
- portrait gate hides/blocks combat controls and advancement;
- raw IDs do not dominate presentation.

Final combat art/animation polish remains deferred.

#### Real-stack verification
Where practical run a controlled real PHP/MySQL/browser path:

`Start Farm -> select Combat -> Fight -> one resolve POST -> playback GET -> BattleScene -> playback complete -> reload -> same retained battle presentation`

For the real Farm victory path verify:
- exactly one node-resolution POST for the logical attempt;
- persisted battle ID from resolution equals playback battle ID;
- no client combat simulation/network chatter per event;
- no bootstrap/content/Warband refetch merely to enter BattleScene;
- same Phaser canvas/runtime/store survives RunScene -> BattleScene;
- server state is already finalized before/during playback.

A deterministic frontend fixture may cover defeat/stalemate BattleScene presentation in this package; Package 8 integrated closure will re-prove full server outcomes as needed.

#### Verification gates
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum report actual results for:
- focused node-resolution frontend contract/API tests;
- focused BattleScene/playback-controller tests;
- existing playback parser/current-run tests;
- full frontend suite;
- production frontend build;
- bundle check;
- deterministic BattleScene captures;
- relevant backend regression if backend contract fixtures are exercised;
- real-stack verifier when environment permits;
- `npm run llm:check` and docs/context checks when applicable;
- `git diff --check`.

Do not claim absent GitHub CI or an unavailable host-only aggregate passed.

#### Explicitly out of scope
Do not implement or scaffold:
- rewards, XP, objectives, Teeth/currency grants;
- server/client battle claim lifecycle;
- final post-battle result/Continue reconciliation back to RunScene/Camp;
- authoritative run-cache replacement after playback;
- loot/rest/boss/exit resolution;
- Mudking/boss combat;
- Farm completion/Mountains unlock;
- server-side playback progress/acknowledgement/pending flags;
- event-sourcing/playback-progress tables;
- new combat mechanics;
- final combat visual overhaul;
- Milestone 5.

#### Review state
When complete:
- leave Package 6 **In Progress**;
- do not mark it complete;
- do not promote Package 7;
- do not begin the final result/return reconciliation package.

Architectural review decides completion.

#### Final report
Report:
1. exact implementation commit SHA;
2. strict node-resolution client contract;
3. RuntimeApiClient mutation behavior;
4. idempotent resolution-attempt state machine;
5. Farm-map Fight vs Watch/Replay behavior;
6. presentation-marker shape/storage/account scoping;
7. startup/reload routing behavior;
8. BattleScene playback model and confirmation it derives no combat mechanics;
9. persisted event -> presentation behavior;
10. playback completion state;
11. current-run cache behavior after resolution;
12. playback GET retry/recovery behavior;
13. defeat/stalemate presentation behavior;
14. responsive/orientation behavior;
15. deterministic captures and visual inspection;
16. real-stack verification results;
17. exact test/build/gate results and environment limitations;
18. unresolved concern, if any.

Do not begin another package.
