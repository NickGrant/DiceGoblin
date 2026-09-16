# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 7 - Battle result + authoritative return-to-run reconciliation

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-6 now provide the complete authoritative combat mutation, immutable battle history/read path, idempotent browser combat initiation, and reload-safe Phaser playback. When `battle_ended` is consumed, `BattleScene` intentionally stops in a local playback-complete state. The presentation marker remains retained and the current-run cache may still be stale from the pre-combat map.

The remaining gameplay gap is leaving that result safely.

Package 7 adds the result/Continue boundary and reconciles browser runtime state from the existing authoritative `GET /api/v1/runs/current` read before navigating away from the retained battle. It must not infer the post-combat graph, HP, run status, or locks from playback animation state.

#### Core outcome
Implement:

`BattleScene playback complete -> result presentation -> Continue -> authoritative current-run read -> GameStore reconciliation -> clear retained presentation -> RunScene or Camp`

Destination after successful reconciliation:
- authoritative current run exists -> `RunScene`;
- authoritative current run is null -> `GameScene` / Camp.

This naturally covers:
- ordinary Farm combat victory -> same active Farm run with completed Combat, persisted terminal HP, and direct child availability;
- defeat/stalemate -> terminal failed run, no current run, Camp;
- reload after playback -> same reconciliation behavior even though the in-memory Package 4 resolution response is absent;
- cross-tab changes -> the newest authoritative current-run read wins rather than stale presentation assumptions.

Do not begin Milestone 5 systems.

#### Required context
Read before implementation:
- `documentation/07-development-path/vnext-phaser-client-architecture.md`;
- `documentation/07-development-path/vnext-api-contract-model.md`;
- `documentation/07-development-path/vnext-endpoint-inventory.md`;
- current `GameStore` current-run reconciliation;
- current `BattlePresentationState`;
- current `BattleScene`/`BattlePlaybackController`;
- Package 4 node-resolution result contract;
- Package 5 current-run/playback contracts;
- Package 6 Fight/Replay/reload lifecycle and verifier.

The existing `GET /api/v1/runs/current` and `GameStore.reconcileCurrentRun` behavior is the intended authority path. Do not add a battle claim/acknowledgement/complete endpoint just to leave presentation.

#### Result presentation
When persisted `battle_ended` has been consumed, replace the temporary playback-complete-only presentation with a small deliberate result state.

At minimum show:
- authoritative persisted outcome: Victory / Defeat / Stalemate;
- historical participating player combatants with terminal HP/defeated state where useful;
- a clear `CONTINUE` action;
- reconciliation/loading/error feedback when Continue is used.

The result screen is presentation only.

Do not show or invent:
- XP gained;
- Teeth/rewards;
- objective progress;
- loot;
- unlock grants;
- claim state;
- a Mudking/Farm-complete result.

Those remain Milestone 5.

Do not automatically leave `BattleScene` when `battle_ended` occurs. The player must explicitly Continue.

#### Continue authority boundary
`CONTINUE` must force an authoritative `GET /api/v1/runs/current` reconciliation before navigating away.

Use the existing runtime API/current-run parser and GameStore reconciliation rather than creating a parallel battle-result state model.

Important:
- force the read even if the current-run cache was previously `fresh` (Replay path) or contains stale pre-combat data (Fight path);
- do not call bootstrap merely to determine the destination;
- do not reload Warband, units, dice, squads, or authored content merely to Continue;
- do not send another combat-resolution POST;
- do not fetch playback again merely to Continue from an already loaded completed result.

The authoritative current-run response's `player_revision` must be adopted through the normal GameStore reconciliation path.

That reconciliation must also update the bootstrap-derived active-run truth already owned by GameStore:
- active current run -> canonical active-run summary remains/updates;
- null current run -> bootstrap `active_run` becomes null.

This is required so active-run Warband presentation locks disappear after terminal defeat/stalemate without a global bootstrap refresh.

#### Active-run victory reconciliation
When the authoritative current-run read returns an active run:
- GameStore current-run becomes `fresh` from that returned aggregate;
- terminal player HP comes from returned `run_unit_state` projection, not playback-derived client mutation;
- the completed Combat node and its persisted `battle_id` come from the returned aggregate;
- direct child availability comes from the returned graph state;
- the returned `player_revision` becomes authoritative;
- navigate to the persistent `RunScene` only after successful reconciliation.

For the ordinary just-resolved Farm victory, verify the selected Combat node is completed with the retained battle ID and Loot is available while Rest/Boss/Exit remain locked.

Do not patch the old cached graph using Package 4's `newly_available_node_ids` or playback events.

The Package 4 resolution response may be retained as a consistency aid within the same runtime, but it is not a substitute for the current-run GET and must not be required after reload/Replay.

If an active authoritative run exists but it is a newer/different run because another tab changed lifecycle state, treat the freshly read run as current truth. Do not resurrect the historical battle's run from the marker.

#### Terminal defeat/stalemate reconciliation
When authoritative current-run returns `run: null`:
- GameStore current-run becomes fresh/null through the existing reconciliation path;
- bootstrap active-run summary becomes null;
- authoritative revision is adopted;
- active-run Warband lock derivation must therefore disappear;
- clear the battle presentation marker/resolution context;
- navigate to Camp.

This must work both:
- immediately after a just-resolved defeat/stalemate where the in-memory bootstrap still contains the old pre-combat active-run summary;
- after a browser reload where bootstrap already reports no active run and only the retained presentation marker caused BattleScene startup.

Do not refund Energy or mutate Energy timestamps during result reconciliation.

#### Presentation marker clearing
Do **not** clear the retained battle marker merely because the playback reached `complete` or the player first pressed Continue.

Clear marker + in-memory retained resolution only **after** the authoritative current-run read has parsed and reconciled successfully.

Why:
- if current-run GET has a transient failure, reload must still rediscover the same retained battle/result;
- a failed Continue must never strand the player between scenes with no recovery identity.

After successful reconciliation and immediately before/with navigation:
- clear `BattlePresentationState` completely;
- subsequent reload follows normal bootstrap/current-run startup rather than returning to the old battle.

#### Reconciliation failures
Continue must be retry-safe and must not rerun combat.

If current-run GET fails because of:
- network failure;
- HTTP 5xx;
- malformed/integrity response;
- another read failure;

then:
- remain on the completed battle result;
- retain the presentation marker;
- do not clear terminal presentation;
- show understandable retry/recovery feedback;
- Retry Continue sends only another current-run GET;
- no resolve POST;
- no new playback GET unless the user actually reloads the page;
- no bootstrap/Warband refetch.

Prevent concurrent duplicate Continue submissions.

If GameStore reports reconciliation integrity failure, do not guess a destination from the old bootstrap or playback; remain recoverable and require retry/reload.

#### Revision/coherence expectations
The current-run read is allowed to return a `player_revision` equal to or greater than the Package 4 resolution result because another authoritative action may have happened after combat in another tab.

Never accept a regressed revision; existing GameStore reconciliation should continue rejecting it.

When the freshly returned active run is the same run/node as the retained battle, require ordinary current-run integrity guarantees already established by Package 5:
- completed Combat carries a non-null battle ID;
- the retained battle/node relationship is not contradicted.

Do not over-constrain a genuinely newer active run belonging to the same player merely because the presentation marker refers to historical combat from an older run.

#### Replay behavior
A completed Combat node can already enter BattleScene through Replay without a resolution POST.

After replay completes, Continue follows the same forced authoritative current-run reconciliation path.

Do not special-case Replay by trusting the current cache without revalidation.

Replay -> Continue must therefore produce:
- zero node-resolution POSTs;
- one forced current-run GET per Continue attempt;
- marker clear only after success;
- return to the authoritative active run or Camp.

#### Reload behavior
A retained marker currently routes startup to BattleScene and restarts immutable playback from event zero.

Preserve that behavior until Continue reconciliation succeeds.

After successful Continue:
- marker is cleared;
- reload no longer returns to BattleScene;
- victory reload/startup follows active-run behavior;
- defeat/stalemate reload/startup follows Camp behavior.

Do not add persistent animation/result acknowledgement state.

#### GameStore boundary
Prefer to keep authoritative lifecycle reconciliation inside GameStore rather than directly mutating bootstrap/current-run objects from BattleScene.

A small explicit public method/helper may be added if needed to express post-battle reconciliation cleanly, but it must consume the existing strict `CurrentRunResult` semantics and preserve revision/coherence checks.

Do not expose setters that allow scenes to assign arbitrary active-run summaries, player revisions, node status, HP, or graph state.

Warband caches should not be globally invalidated simply because combat ended; the active-run lock derives from reconciled bootstrap/current-run truth. Only mark/reload domains if a concrete current contract requires it.

#### BattleScene result interaction
The result/Continue controls must follow existing interaction conventions:
- pointer cursor only when actionable;
- disabled while reconciliation request is in flight;
- visible retry action after failed reconciliation;
- portrait gate blocks interaction and maintains the completed local result state;
- returning to landscape restores the same result/Continue state.

Do not remount Phaser or create another canvas during Continue navigation.

#### Tests
At minimum prove:
- `battle_ended` enters result/playback-complete state without automatic scene navigation;
- result uses persisted outcome/terminal participant facts and invents no reward/progression facts;
- Continue forces `GET /api/v1/runs/current` even if the old cache was fresh;
- Continue from stale pre-combat cache does not locally patch node/HP/unlocks before the GET succeeds;
- successful victory reconciliation replaces cache with authoritative completed Combat/battle ID/terminal HP/direct-node availability and adopts revision;
- successful victory clears presentation marker/context and navigates to RunScene;
- successful defeat/stalemate reconciliation accepts `run:null`, clears bootstrap active run/Warband lock, adopts revision, clears marker/context, and navigates to Camp;
- immediate terminal reconciliation works when in-memory bootstrap still says the old run is active;
- reload-style terminal reconciliation works when bootstrap already has `active_run:null`;
- Replay -> Continue issues zero resolve POSTs and still forces current-run GET;
- network/5xx/malformed/integrity failure during Continue retains marker and result state;
- Retry Continue issues another current-run GET only;
- simultaneous Continue clicks do not create overlapping reads/navigation;
- regressed revision or contradictory current-run data cannot silently navigate;
- a newer/different authoritative active run can become the destination without reviving the historical marker run;
- marker is cleared only after successful GameStore reconciliation;
- after successful Continue, startup routing no longer prefers BattleScene;
- portrait gating cannot accidentally fire Continue;
- same Phaser canvas/runtime persists through BattleScene -> RunScene/GameScene.

#### Captures / visual verification
Produce deterministic captures for at least:
- victory result at Standard;
- defeat result at Standard;
- result at Compact;
- result at Wide;
- Continue reconciliation error/retry state;
- portrait-gated completed result if capture infrastructure supports it.

Visually inspect that:
- outcome and Continue are clear;
- terminal HP/defeat state remains readable;
- no fake rewards/XP appear;
- error state communicates that the battle is already safe/finalized and only return synchronization needs retrying;
- Compact has no overlapping result controls.

Final art/animation polish remains deferred.

#### Real-stack verification
Where practical extend the controlled browser/PHP/MySQL verifier to exercise:

`Start Farm -> Fight -> one resolve POST -> playback complete -> Continue -> GET current run -> RunScene or Camp`

For a victory verify:
- exactly one resolve POST total;
- Continue causes current-run GET and no new playback/resolve/bootstrap/Warband request;
- RunScene shows the authoritative completed combat/battle ID and next-node availability;
- authoritative persisted HP matches returned RunScene current HP;
- presentation marker is gone;
- reload goes to RunScene, not BattleScene;
- same Phaser canvas/runtime survives BattleScene -> RunScene.

Use deterministic frontend fixtures for defeat/stalemate result UX if forcing those real-stack outcomes is awkward. Package 8 integrated closure will exercise terminal outcomes again.

#### Verification gates
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum report actual results for:
- focused BattleScene result/Continue tests;
- GameStore current-run reconciliation tests;
- RunScene/Camp destination tests;
- Package 6 playback/marker regression tests;
- full frontend suite;
- production frontend build;
- bundle check;
- deterministic result captures;
- real-stack Continue verifier when environment permits;
- relevant backend/current-run regressions if fixture contracts change;
- `npm run llm:check` and docs/context checks when applicable;
- `git diff --check`.

Do not claim absent GitHub CI or an unavailable host-only aggregate passed.

#### Explicitly out of scope
Do not implement or scaffold:
- rewards, XP, objectives, Teeth/currency grants;
- battle claim/acknowledgement server lifecycle;
- reward/claim idempotency;
- Loot/Rest/Boss/Exit resolution;
- Mudking boss combat;
- Farm completion or Mountains unlock;
- run-history/replay browser beyond the current active-run completed-node Replay affordance;
- permanent playback-progress state;
- final game-wide battle visual overhaul;
- Milestone 5 work;
- Package 8 closure work beyond tests directly needed by this package.

#### Completion requirements
Before review:
1. implement only this package;
2. add/update focused tests and deterministic captures;
3. run/report the applicable gates honestly;
4. update only current canonical docs if this package materially changes a documented accepted behavior;
5. leave this issue **In Progress**;
6. do not promote Package 8;
7. report the exact implementation commit SHA, Continue/reconciliation state machine, GameStore changes, marker-clearing policy, active-vs-null destination behavior, failure/retry behavior, tests/captures, real-stack evidence, and unresolved concerns.
