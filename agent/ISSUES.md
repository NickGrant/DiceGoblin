# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Complete Warband integrated verification and closure

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 2 implementation is complete through Package 8: the fresh vNext persistence model, canonical Warband content, authoritative reads/commands, controlled development fixtures, persistent Phaser Warband, squad editing, and individual unit/loadout/dice configuration have all passed package-level architectural review. The coding-agent session that implemented Package 8 ended before producing its final verification narrative, and the milestone has not yet been proven as one integrated fresh-database/browser-to-PHP-to-MySQL slice.

Close Milestone 2 by running the complete verification matrix, exercising the real authoritative Warband flow end to end, correcting only defects discovered by that verification, retiring only live prototype pathways that are now conclusively superseded, and leaving the repository ready for manual user UAT.

This is a closure package, not a new feature package.

#### Required Context
- `AGENTS.md`
- `agent/QUALITY_GATES.md`
- `agent/MILESTONES.md`
- `documentation/07-development-path/vnext-game-overhaul.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-api-contract-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/02-systems/warband-and-formation.md`
- `documentation/02-systems/ability-loadouts-and-dice-binding.md`
- `documentation/02-systems/dice-profiles-and-aspects.md`
- `documentation/02-systems/unit-stat-advancement.md`

Inspect the approved Milestone 2 implementation as one system rather than re-implementing earlier packages.

#### Approved Package State
The following are already architecturally approved and should be treated as the intended design unless integrated verification proves a concrete defect:

1. Warband persistence foundation.
2. Warband authored content + validation/projection.
3. Authoritative Warband read APIs + controlled development/UAT fixtures.
4. Squad commands + active-squad bootstrap integration.
5. Unit rename + atomic loadout/dice-binding command.
6. Phaser Warband navigation + lazy read/cache surfaces.
7. Phaser squad editor + activation/lifecycle flows.
8. Phaser unit detail + rename/loadout/dice configuration flows at `f65356bf16854c334415ba14e964d11f6c45cd0e`.

Do not redesign these packages during closure merely because another design is possible.

#### Closure Goals
Prove the complete authoritative path:

fresh vNext database
→ account/authentication
→ controlled Warband fixture
→ `/game` startup/bootstrap/content revision
→ Camp
→ lazy Warband collections
→ squad lifecycle/configuration
→ unit detail
→ rename
→ whole loadout + exact die configuration
→ authoritative cache reconciliation
→ persisted state survives deliberate reload/re-read

while maintaining:
- one persistent Phaser runtime;
- no live Angular gameplay ownership;
- no `/profile` refresh;
- no `/teams` compatibility flow;
- no SQL gameplay catalogs;
- no production starter provisioning;
- no client-authoritative durable gameplay state.

#### Fresh Database Verification
Start from the repository-supported fresh vNext database baseline.

Verify the schema contains the approved account and Warband storage through Milestone 2, including:
- account/auth tables;
- `user_state` with nullable active squad;
- owned unit instances;
- promotion history;
- durable unit ability ownership;
- ordered ability loadout;
- owned dice instances;
- exact unit/ability/slot dice binding;
- saved squads and fixed formation membership;
- command idempotency persistence required by squad creation.

Verify the Package 5 global physical-die uniqueness invariant is present.

Normal fresh account registration must still create no units, dice, squads, or production starter pack.

Do not add migrations for prototype runtime/player data.

#### Authored Content Verification
Run canonical authored-content validation and generation from the Git-tracked JSON authority.

Verify:
- all Warband stable IDs referenced by the controlled fixture resolve;
- unit type, kin, ability, dice material, dice aspect, and dice profile references are valid;
- material/aspect/profile size constraints remain coherent;
- client projection remains allowlisted and does not leak server-private handler/targeting/configuration fields;
- generated `frontend/public/game-content.json` exactly matches current canonical content/projector output;
- client/server content revision compatibility is exact and deterministic.

Do not create a second editable content source during closure.

#### Controlled Fixture Verification
Use the Package 3 controlled fixture mechanism against a real authenticated development/UAT account.

Verify:
- production remains unable to invoke it;
- explicit environment/enablement checks still apply;
- authentication and CSRF still apply when HTTP-based;
- repeat invocation replaces/recreates known logical Warband state rather than accumulating duplicates;
- fixture mutation is transactional;
- unrelated account/currency/Energy state is preserved;
- the fixture uses real canonical authored IDs.

The fixture is UAT/development support, not production onboarding.

#### Real HTTP + Browser Integration
Exercise the real PHP/MySQL stack through a browser where repository-supported tooling permits it.

Use a fresh or deliberately reset test database and an authenticated fixture-populated account.

At minimum prove this flow:

1. Load `/game` and reach Camp through the normal real bootstrap path.
2. Open Warband.
3. Observe authoritative lazy `GET /units`, `GET /dice`, and `GET /squads` data.
4. Return to Camp and reopen Warband while caches remain fresh; verify ordinary navigation does not refetch bootstrap/content or duplicate fresh collection requests.
5. Create a saved squad through the real command path.
6. Edit its name and complete nine-position formation.
7. Activate another saved squad.
8. Delete an allowed saved squad and exercise/verify the active-squad deletion rule.
9. Open a real owned unit from the roster.
10. Confirm unit detail loads lazily.
11. Rename the unit.
12. Modify the ordered active loadout using only durable owned active abilities.
13. Move an existing same-unit die and/or assign another available exact physical die.
14. Save the complete loadout atomically.
15. Return to Warband and verify authoritative roster/dice/squad presentation reflects committed results without bootstrap/profile refresh.
16. Deliberately reload/reopen/re-query authoritative state and confirm the server/database state matches what the prior client reconciliation displayed.

The test may use direct Playwright interaction, a dedicated verification script, or another repository-supported deterministic browser path.

Do not mock PHP/MySQL for the primary integrated proof.

#### Network/Runtime Assertions
During the integrated browser flow verify as practical:
- no `/api/v1/profile` requests;
- no `/teams` requests;
- initial bootstrap occurs once for ordinary in-runtime navigation;
- `game-content.json` is not refetched for each gameplay screen;
- Warband collections are lazy rather than startup payloads;
- fresh caches avoid duplicate collection requests;
- unit detail is lazy and per-instance cached;
- squad/unit mutations use the approved narrow endpoints;
- CSRF is present on mutations;
- squad create uses `Idempotency-Key`;
- the same Phaser canvas/runtime persists through Camp, Warband, squad editor, and unit configuration;
- Angular routing is not used for gameplay-screen navigation.

Do not weaken the implementation solely to make a verification script easier to write.

#### Authoritative Mutation Verification
Re-prove the important mutation invariants in the integrated system:

##### Squads
- first saved squad activation semantics;
- additional create preserves existing active selection;
- whole-formation update is atomic;
- activation adopts authoritative revision;
- already-active activation is a valid no-op;
- active squad cannot be deleted while other saved squads remain;
- deleting the last active squad leaves no active squad;
- create idempotent replay does not duplicate a squad or revision;
- same idempotency key + different request conflicts safely.

##### Units
- rename changes only name and revision as appropriate;
- same normalized name is a valid no-op;
- loadout is complete/non-empty and ordered;
- only durable per-instance owned active abilities may be equipped;
- passive abilities cannot be scheduled;
- every authored slot has exactly one die;
- one physical die appears at most once globally;
- a die bound to another unit cannot be stolen;
- same-unit die movement is allowed in one atomic replacement;
- failed commands leave previous committed configuration intact.

#### Cross-Player Security Regression
Re-run focused adversarial integration coverage with at least two users.

Verify one player cannot:
- fetch another player's unit detail;
- infer foreign unit existence through distinguishable detail responses;
- place another player's unit into a squad;
- edit/activate/delete another player's squad;
- equip another player's die;
- leak foreign identity through intentionally corrupt persisted relationships.

Persisted cross-owner corruption must continue failing as an integrity error rather than leaking another player's IDs/state.

#### Player Revision
Exercise `player_revision` across the complete slice.

Verify:
- queries do not increment it;
- real squad/unit mutations increment exactly once;
- accepted no-op commands retain the current revision;
- failed/rolled-back commands do not increment it;
- client reconciliation adopts server revision rather than incrementing locally;
- impossible revision regression is rejected/treated as integrity failure.

#### Client Cache Recovery
Exercise at least representative stale/integrity recovery paths.

Verify:
- a failed Warband domain does not destroy unrelated fresh domains;
- squad reconciliation failure does not fabricate missing state;
- unit rename reconciliation failure marks detail/roster state for deliberate recovery;
- loadout reconciliation failure marks detail/dice state for deliberate recovery;
- returning through Warband can refresh stale lazy domains before reopening the editor;
- `GameStore.clear()` clears bootstrap, Warband lazy domains, and per-unit detail caches.

Do not add a global profile/bootstrap refresh as a recovery shortcut.

#### Responsive and Visual Verification
Run deterministic presentation capture for the completed Milestone 2 gameplay surfaces.

At minimum capture and inspect:

Warband:
- Compact `844 x 390` touch/mobile;
- Standard `1600 x 900`;
- Wide `2560 x 1080`.

Squad editor:
- Compact;
- Standard;
- Wide.

Unit configuration:
- Compact;
- Standard;
- Wide.

Also run the existing touch-first portrait-gate regression.

Inspect for:
- clipping/overlap;
- controls outside safe bounds;
- inaccessible paged content;
- unreadable labels;
- broken 3x3 formation;
- unreachable ability/die slots;
- raw stable IDs dominating player-facing presentation;
- native text inputs misaligned or usable underneath command/confirmation/portrait gating;
- obvious scaling failures.

Do not reopen the deferred game-wide visual overhaul. Functional clarity is the Milestone 2 bar.

#### Required Quality Gates
Run the applicable repository gates in `agent/QUALITY_GATES.md` and report their actual results.

The closure should cover, as supported by the environment:
- `npm run llm:check`
- `npm run docs:lint`
- `npm run content:validate`
- fresh DB provision/reset verification
- backend test suite through the supported Docker path
- focused Warband database/integration tests
- full frontend test suite
- frontend production build
- bundle check
- deterministic capture commands
- `npm run verify:full` where the host environment supports all of its prerequisites.

If the aggregate full-verification command stops because the host still lacks PHP or another known host dependency, run its required constituent gates through supported environments and report that limitation precisely. Do not call a host-PATH limitation a product failure, and do not claim an aggregate command passed if it did not.

Package 8 ended before its coding-agent final verification narrative. Explicitly rerun/cover its relevant frontend tests/build/captures here rather than assuming they passed.

#### Prototype Disposition / Cleanup
After the integrated vNext Warband slice is proven, inspect the retained prototype unit/dice/team/Angular Warband pathways against:

`documentation/07-development-path/vnext-prototype-code-disposition.md`

Delete only implementation that is now conclusively:
- unreachable from live routing/composition;
- superseded by the approved vNext Warband slice;
- no longer needed as reuse evidence for a later unimplemented milestone.

Do not preserve dead compatibility code merely as an archive; Git history is the archive.

Also do not delete useful retained algorithms/tests/evidence whose actual replacement belongs to a later milestone.

In particular verify there is no live vNext dependency on:
- prototype `/teams` compatibility;
- Angular Warband/unit/dice gameplay pages or orchestration;
- prototype catch-all `/profile` synchronization;
- per-slot die assignment/clear mutation APIs;
- SQL-authored unit/dice catalogs;
- old loadout Speed/equipment-budget rules.

Keep cleanup narrow and evidence-based. Do not perform unrelated repository modernization.

#### Documentation Closure
Update active documentation only when verification/cleanup changes current truth.

Appropriate updates may include:
- prototype disposition if specific source is retired;
- source maps if a competing static source is removed;
- current architecture/roadmap wording if an accepted implementation detail is now durable.

Do not create:
- package completion reports in the documentation tree;
- UAT history documents;
- archive folders;
- legacy snapshots.

Git history remains the archive.

Do not mark Milestone 2 UAT passed. Manual UAT is performed by the user after architectural closure review.

#### UAT Handoff Readiness
Leave the repository in a state where the user can manually exercise Warband using a practical controlled fixture path in an appropriate development/UAT environment.

Do not implement production starter provisioning to make UAT convenient.

The architectural reviewer will produce the final manual UAT checklist after Package 9 passes review.

#### Explicitly Out of Scope
Do not implement:
- promotion/progression or Academy transactions;
- Shop;
- Wrong Machine;
- production onboarding/starter provisioning;
- run persistence or Enter Farm functionality;
- BattleScene functionality;
- Milestone 3;
- final game-wide visual/UI overhaul;
- speculative active-run locks before Milestone 3 creates authoritative run state.

Defects in the already-approved Milestone 2 slice may be fixed when verification demonstrates them. Do not use closure as permission for feature expansion.

#### Completion State
When technical verification and any required corrections/cleanup are complete:
- leave this Package 9 issue **In Progress**;
- do not mark Milestone 2 complete/UAT passed;
- do not promote Milestone 3;
- do not begin Farm work.

Architectural review will decide Package 9 closure and prepare the user UAT checklist.

#### Final Report
Report:
1. resulting commit SHA(s);
2. fresh-database verification and exact baseline outcome;
3. authored-content validation/revision outcome;
4. fixture verification;
5. real PHP/MySQL/browser flow actually exercised;
6. network/request assertions including bootstrap/content/profile behavior;
7. squad lifecycle/idempotency results;
8. unit rename/loadout/dice results;
9. cross-player security results;
10. `player_revision` results;
11. stale/integrity recovery results;
12. frontend/backend/database/content quality-gate commands and results;
13. production build/bundle results;
14. each responsive capture path and visual inspection findings;
15. prototype source/wiring retired, with reason;
16. retained prototype evidence intentionally left for later milestones;
17. any environment limitation or skipped optional verification;
18. unresolved Milestone 2 concern, if any;
19. whether the slice is `READY FOR ARCHITECTURAL CLOSURE REVIEW`.

Do not begin another milestone.
