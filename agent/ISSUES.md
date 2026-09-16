# Active Execution Issue

## Milestone 4 - Combat

### Milestone 4 Package 8 - Combat integrated verification/closure

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-7 now implement the complete Milestone 4 combat slice: canonical level-derived stats and run HP, authored Farm combat content, the deterministic PHP combat kernel, immutable battle persistence, atomic/idempotent combat-node resolution, ownership-safe retained playback reads, Phaser playback/reload presentation, and explicit result/Continue reconciliation back to authoritative current-run truth.

The remaining work is **technical closure**, not another gameplay feature package.

This package must prove the accepted Milestone 4 boundaries together on a fresh database and real runtime, close concrete integration/regression defects if any are discovered, and leave the milestone ready for focused manual user UAT.

Do not begin Milestone 5.

#### Core outcome
Prove the integrated slice:

`Fresh account/Warband -> Start Farm -> available Combat -> one authoritative resolution -> immutable battle + terminal run HP -> retained playback -> BattleScene -> reload-safe playback/result -> Continue -> authoritative current-run reconciliation -> RunScene or Camp`

For the ordinary victory path, the final active-run truth must show:
- the same Farm run active;
- Combat completed with the finalized battle ID;
- authoritative terminal player HP;
- Loot available;
- Rest/Boss/Exit still locked.

For defeat/stalemate, backend integration must prove:
- finalized battle retained;
- Combat completed;
- terminal HP persisted;
- no child unlock;
- run status `failed` with `ended_at`;
- no Energy refund/mutation;
- no current active run;
- retained playback remains readable;
- successful client reconciliation clears active-run presentation locks and returns to Camp.

#### Closure posture
This is not an opportunity to add features or redesign accepted contracts.

Allowed production changes:
- narrow fixes for concrete defects discovered by closure verification;
- small instrumentation/testability changes required to verify existing accepted behavior;
- current documentation corrections that make accepted behavior accurate.

Not allowed:
- rewards/XP/objectives/currency;
- Loot/Rest/Boss/Exit resolution;
- Mudking/boss combat;
- Farm completion or Mountains unlock;
- new battle claim/acknowledgement state;
- run-history/replay browser expansion;
- final game-wide visual overhaul;
- speculative schema/API/framework cleanup;
- Milestone 5 scaffolding.

If closure discovers a material architectural defect, leave Package 8 In Progress and report it rather than hiding it behind verifier exceptions.

#### Fresh baseline and authored content
Start from the supported fresh vNext database reset.

Verify:
- baseline applies cleanly on MySQL 8;
- the expected current vNext tables are present, including the single `battles` table introduced by Milestone 4;
- `run_unit_state.current_hp` is concrete/non-null in the fresh baseline;
- one finalized battle per run node and same-run node/battle relational constraints remain present;
- no SQL static gameplay catalogs have returned;
- no speculative battle playback/event/reward/claim tables have appeared;
- authored content validation passes structurally and semantically;
- first Farm Combat references the canonical standard encounter;
- public/client content projection still does not expose hidden encounter roster/seed/private combat configuration;
- content revision remains deterministic for unchanged content.

Do not add migrations for prototype/current runtime data. The fresh baseline remains the vNext schema source.

#### Deterministic combat regression
Re-run and preserve the focused Package 2 deterministic/golden coverage.

Verify the accepted implemented slice remains deterministic for identical normalized input + seed, including:
- scheduling and tie ordering;
- target resolution/ties;
- Precision/Resolve behavior;
- damage/rounding and position modifiers;
- required dice/profile/aspect/passive behavior;
- required statuses and forced targeting;
- victory/defeat/stalemate;
- semantic playback sequence/version facts.

Do not tune combat balance/formulas during closure unless a test exposes an implementation/documentation contradiction. Balance iteration belongs in later product work.

#### Authoritative mutation integration
On real MySQL/PHP integration, prove the Package 4 command as a whole:
- authentication/CSRF/path/idempotency requirements;
- player-row-first serialization remains intact;
- exact authoritative snapshot assembly from run + locked Warband + authored encounter;
- one real CombatEngine invocation for a fresh resolution;
- finalized battle inserted exactly once;
- terminal player HP persisted exactly once;
- node/run lifecycle persisted atomically;
- player revision increments exactly once;
- Energy current and regeneration anchor do not change;
- exact same-key replay returns the original response without a second engine/battle/HP/node/revision mutation;
- another key cannot reroll a finalized combat node;
- post-engine failure rolls back battle/HP/node/run/revision/receipt together.

Retain explicit victory, defeat, and stalemate backend coverage. The ordinary browser real-stack path may use the deterministic Farm victory path, but terminal server outcomes must still be exercised by integration tests.

#### Ownership and non-disclosure
Use at least two authenticated players/accounts in integration coverage.

Prove:
- Player A cannot resolve Player B's run/node;
- foreign/missing run or node behavior remains non-disclosing according to the established API contracts;
- Player A cannot read Player B's finalized playback;
- foreign and missing battle playback reads remain indistinguishable;
- rejected foreign actions mutate neither player's run, HP, battle, Energy, revision, nor idempotency state;
- each player may independently own/resolve/read their own run/battle state.

Do not expose hidden encounter/combat input through error text while proving these cases.

#### Immutable historical playback
Prove playback remains historical evidence rather than a reconstruction:
- finalized input/manifest/result round-trip through persistence;
- playback GET is derived only from persisted battle evidence;
- changing current Warband name/type/configuration after a retained historical battle does not rewrite its historical participant presentation;
- mutable current run HP does not rewrite historical initial/terminal battle facts;
- active, failed, and later-abandoned retained battles remain readable by their owner;
- raw seed/normalized input/private enemy configuration remain absent from player-facing playback response.

Do not rerun CombatEngine to validate or serve historical playback.

#### Frontend authority and retry closure
Preserve Package 6-7 focused tests and add closure coverage only where an integrated gap remains.

Verify:
- Fight sends the exact no-body resolution POST;
- one logical attempt preserves one idempotency key across ambiguous retry;
- duplicate Fight submissions are suppressed;
- no optimistic HP/node/graph mutation before authority returns;
- completed-node Replay sends zero resolution POSTs;
- playback GET failure retries playback GET only;
- BattleScene consumes persisted semantic facts and performs no combat calculations;
- opposing formations render front/middle/back toward battlefield center correctly;
- historical `art_key` drives supported battle assets with neutral fallback;
- portrait gate suspends playback/result interaction;
- presentation marker is account-scoped and contains identity only;
- reload before Continue returns to the retained battle without combat rerun;
- Continue forces current-run GET even when local cache was fresh;
- failed Continue keeps marker/result and retries current-run GET only;
- successful reconciliation clears marker only after GameStore adopts authority;
- victory routes to RunScene from returned active run;
- defeat/stalemate routes to Camp from returned `run:null` and removes active-run Warband locks;
- a newer authoritative run from another tab wins over the historical marker run;
- same Phaser runtime/canvas survives scene transitions.

#### Real-stack browser verification
Run a controlled real browser + PHP + MySQL victory flow on a freshly reset environment:

`register/login -> provision/seed valid Warband through supported UAT fixture -> Camp -> Start Farm -> Combat -> Fight -> BattleScene -> playback complete -> reload -> same retained battle -> Continue -> authoritative RunScene -> reload -> RunScene`

The verifier must distinguish its own direct API probes from browser-app traffic.

For browser-app traffic prove:
- exactly one combat-node resolution POST for the logical Fight;
- resolution POST has a valid idempotency key and no request body;
- battle ID returned by resolution equals playback/current-run battle ID;
- one playback GET per BattleScene presentation/reload, with no per-event network chatter;
- no bootstrap/game-content/Warband refresh merely to enter BattleScene;
- reload before Continue issues no second resolution POST;
- Continue issues exactly one fresh current-run GET and no new resolve/playback/bootstrap/Warband request;
- reconciled Combat is completed, HP is authoritative, Loot is available, Rest/Boss/Exit locked;
- marker is cleared after successful Continue;
- same Phaser canvas/runtime survives RunScene -> BattleScene -> RunScene;
- reload after Continue enters RunScene rather than BattleScene.

Do not weaken the verifier to accept multiple equivalent mutation requests.

#### Terminal result presentation
Use deterministic frontend fixtures/captures for victory, defeat, and stalemate result presentation if forcing terminal outcomes through the browser real stack is impractical.

At minimum verify:
- persisted outcome shown clearly;
- player terminal HP/defeated state readable;
- Continue present and actionable only when allowed;
- failed synchronization explains that combat is already finalized and only return synchronization needs retry;
- no fake reward/XP/loot grant presentation;
- terminal result can return to Camp after authoritative null-run reconciliation.

#### Responsive and visual closure
Produce/re-run deterministic captures for the live Milestone 4 surfaces at minimum:
- Farm map with available Combat action;
- BattleScene early/mid playback Standard;
- playback/result victory Standard;
- result defeat Standard;
- Compact battle/result;
- Wide battle/result;
- portrait gate during playback or completed result.

Visually inspect for functional usability only:
- formation orientation correct;
- combatants/HP/captions/result readable;
- no overlap that blocks Fight/Continue;
- pointer cursor only on actionable controls;
- disabled/submitting controls do not advertise clickability;
- portrait gating blocks interaction/advancement;
- raw internal IDs do not dominate presentation.

Final production art/animation polish remains deliberately deferred.

#### Regression gates
Run the applicable supported gates from `agent/QUALITY_GATES.md` and report the exact commands/results.

At minimum closure evidence must include:
- supported fresh DB reset/schema verification;
- focused deterministic combat tests;
- focused Package 4 combat-resolution/MySQL tests;
- focused Package 5 playback/current-run tests;
- focused Package 6-7 frontend lifecycle/reconciliation tests;
- complete backend suite in the supported Docker environment;
- complete frontend suite;
- authored content validation through the supported environment;
- production frontend build;
- bundle check;
- deterministic captures;
- real-stack combat verifier;
- `npm run llm:check`;
- docs/context lint/checks;
- `git diff --check` or equivalent cleanliness check.

Run `verify:full` if the host environment supports all of its dependencies. If it stops because a documented host dependency such as PHP is unavailable, report that honestly and provide the supported constituent Docker/frontend results instead. Do not claim the aggregate passed when it did not.

Check GitHub status/workflow evidence if available, but absence of attached workflow status is not itself a closure failure when the required supported gates have been run and reported.

#### Prototype/scope audit
Before declaring closure ready, inspect the changed Milestone 4 surface for scope discipline:
- no frontend combat simulation path added;
- no SQL gameplay catalogs added;
- no server pending-playback/claim lifecycle added;
- no rewards/progression/currency side effects in combat resolution;
- no Package 5/Milestone 5 node-resolution behavior leaked into Combat;
- no duplicate combat API introduced;
- current accepted docs describe the live architecture rather than a superseded placeholder flow.

Do not perform unrelated prototype cleanup merely for aesthetic completeness. Git history remains the archive; remove/adjust only live paths that actually conflict with the accepted vNext slice.

#### UAT readiness
Package 8 does **not** mark Milestone 4 UAT passed.

When technical closure is ready:
- leave this issue **In Progress** for architectural closure review;
- do not promote Milestone 5;
- report the exact closure commit SHA;
- summarize any production corrections made during closure;
- report exact verification commands/results and environment limitations;
- identify the deterministic UAT setup path used for a fresh tester;
- provide any known non-blocking visual limitations that are deliberately deferred.

After architectural closure review passes, planning will move to **manual UAT pending** and the user will receive a focused Milestone 4 combat checklist.

#### Completion requirements
Before review:
1. implement no new gameplay beyond concrete closure fixes;
2. preserve all accepted Package 1-7 authority/persistence/retry boundaries;
3. add or strengthen integrated verification where necessary;
4. run/report the supported regression/real-stack/visual gates honestly;
5. keep current canonical docs accurate;
6. leave Package 8 **In Progress**;
7. do not begin Milestone 5;
8. report the closure SHA, changed files/purpose, exact verification evidence, environment limitations, UAT setup path, and unresolved concerns.
