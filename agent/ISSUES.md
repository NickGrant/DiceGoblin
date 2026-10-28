# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 9 - Focused manual UAT before Milestone 12

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-8 have implemented and technically verified the Milestone 11 knowledge/objective lifecycle. The remaining gate is focused player-visible manual UAT before the milestone may close and Milestone 12 may begin.

#### Accepted baseline
- Milestones 1-10 are complete and passed their required manual UAT.
- Milestone 11 Packages 1-7 are approved as recorded in `agent/MILESTONES.md`.
- Package 8 is approved through `3834110440ca02ee747860fe7badccae9a398183` (verification implementation `63e59bacf3d5b6248cbed4aa0501a995eef1dbaf`).
- Full Package 8 verification passed with 795 backend tests / 5,769 assertions and 524 frontend tests.
- Package 8 found no production-code or canonical-architecture correction necessary.
- Backend/MySQL remains authoritative for Codex ownership, dialogue eligibility, objective progress/completion, rewards, revision, and idempotency.
- Phaser surfaces are read/presentation clients of authoritative Codex/objective state.
- The major game-wide visual/UI overhaul remains deferred.

#### Purpose
Perform focused manual UAT of the player-visible Milestone 11 behavior. This package is a gate, not an implementation package. Do not add features while executing UAT. Any defect found should be recorded precisely and corrected in a narrow follow-up before acceptance.

#### UAT preparation
Use a clean/test account or otherwise known state that allows the tester to distinguish undiscovered/discovered Codex entries and unstarted/active/completed objectives. Use the normal game client and backend rather than debug-only mutation paths except where a documented test setup is necessary to reach a state.

#### Manual UAT checklist

##### A. Camp surfaces and navigation
1. Open Camp and confirm both **Codex** and **Objectives** destinations are visible and usable alongside existing destinations.
2. Enter Codex, return to Camp, enter Objectives, return to Camp, and repeat several times. Confirm navigation remains responsive with no duplicated input behavior, stale overlays, or obvious lifecycle artifacts.
3. Resize/use representative landscape sizes where practical and confirm both surfaces remain legible and navigable. On portrait touch-first layout, confirm the existing rotate-device gate remains in control.

##### B. Codex knowledge presentation
1. Open Codex before earning the test/available knowledge entry. Confirm the entry is visibly undiscovered and its private body text is not shown.
2. Trigger the legitimate dialogue/gameplay flow that grants the knowledge entry.
3. Reopen or refresh Codex and confirm the entry is now discovered and its body is visible.
4. Reload/reconnect and confirm the discovered state persists.
5. Replay the granting dialogue where available and confirm the Codex does not create a duplicate entry or otherwise visibly duplicate the unique reward.

##### C. Knowledge-aware dialogue
1. Before owning the prerequisite Codex knowledge, open the relevant dialogue and confirm only the non-prerequisite choice(s) are available.
2. After earning the prerequisite knowledge, reopen the dialogue and confirm the knowledge-aware choice becomes available.
3. Confirm dialogue remains replayable; prior viewing alone must not remove it or fabricate a viewed/history state.
4. If practical with a second test account, confirm one account's Codex ownership does not unlock the knowledge-aware choice for the other account.

##### D. Objective presentation and progress
1. Open Objectives in a known initial state and confirm unstarted/active/completed labels and requirement counters look coherent.
2. Perform an authoritative qualifying gameplay action represented by current authored content (combat enemy defeat, successful region run completion, and/or legitimate dialogue completion).
3. Return to Objectives and confirm the appropriate counter advances without any manual claim/progress action.
4. Confirm unrelated objectives/requirements do not visibly advance.
5. Reload/reconnect and confirm progress persists.

##### E. Objective completion and rewards
1. Complete an available objective through legitimate gameplay facts.
2. Confirm the objective automatically becomes completed when its requirements are satisfied; there should be no Claim button or client-side completion action.
3. Confirm any authored completion reward is reflected through its normal authoritative surface (wallet/unlock/Codex/inventory/unit/etc.) rather than reconstructed inside Objectives.
4. Repeat/replay a previously accepted action where the UI permits it and confirm objective completion/rewards are not visibly duplicated.
5. Reload/reconnect and confirm completed status and reward state persist.

##### F. Error/recovery sanity
Where practical without modifying production behavior, exercise a temporary read failure/offline interruption while entering Codex or Objectives. Confirm the surface fails visibly, offers the existing retry path, and recovers after connectivity returns without displaying fabricated/stale state as current.

#### Acceptance criteria
Package 9 passes when the user explicitly confirms the focused UAT is acceptable and no blocking Milestone 11 defect remains. Minor visual polish that belongs to the deferred game-wide visual overhaul may be recorded separately and does not block acceptance unless it prevents usable interaction or comprehension.

If a blocking defect is found:
- leave Package 9 In Progress;
- record the exact reproduction and expected/actual behavior;
- make only the narrow correction required by accepted Milestone 11 semantics;
- rerun affected automated gates and the relevant UAT steps before requesting acceptance again.

#### Scope exclusions
Do not add new gameplay/product mechanics, objective requirement kinds, dialogue history/story flags, manual objective claims, generic progression/history state, Mystic Cave/onboarding, Swamp/Frog Kin, production Shrine/Chaos expansion, broad visual redesign, or other Milestone 12+ work during this UAT package.

#### Completion evidence required
Record the user's explicit UAT acceptance, any defects/corrections and verification SHAs, and the final accepted Milestone 11 baseline. Only after explicit acceptance should `agent/MILESTONES.md` mark Package 9 approved / Milestone 11 complete and activate Milestone 12 planning/execution.

Leave Package 9 **In Progress** until the user explicitly accepts UAT.

#### Blocking UAT finding: Farm dialogue is unreachable in the normal client

- Reproduction (local Nick account, user ID 1, run ID 14): complete the authored Farm route. The persisted nodes were Combat, Loot, Rest, Boss, Exit, all completed; no dialogue appeared during the run.
- Expected by the tester: one or two conversations encountered during Farm play so the Package 9 knowledge-aware dialogue and Codex-grant steps can be exercised through the normal client.
- Actual: `backend/content/run-generation/the-farm.json` has no dialogue node; `dialogue.camp.farm_scout` is authored as a Camp conversation, but no GameScene or RunScene dialogue entry/surface is mounted. Only the backend dialogue endpoints and strict browser API contracts exist. UAT checklist B.2-B.5 and C.1-C.3 cannot be completed through the normal client.
- The tester chose a mandatory Farm map step after Loot as the placement for the first playable conversation. The Package 9 correction inserts the authored Farm Scout there, using the existing run interaction and dialogue knowledge contracts. UAT must be repeated in a newly started Farm run; the previously completed run retains its persisted five-node graph. Package 9 remains In Progress until the tester accepts the renewed UAT.

#### Blocking UAT finding: Mudking conversations absent at the Boss encounter

- The tester reached the Farm Scout, then reported that the conversation presentation needs later visual improvement and that Mudking needs distinct first encounter, first victory/merchant liberation, and repeat encounter conversations.
- Accepted placement: attach the conversations to the Boss encounter when practical; separate nodes were allowed as a fallback. The first victory grants Shop access immediately through the existing Boss reward, so the merchant liberation conversation appears once when that unlock is newly granted. Shop ownership selects the first or return greeting on later Boss visits.
- The narrow correction authors three Mudking dialogues on the Farm Boss encounter. The available Boss read exposes only the selected dialogue title/body/choice labels; the finalized Boss receipt names the merchant conversation only when Shop access was newly granted. RunScene requires the selected greeting before combat, and BattleScene requires the merchant conversation before returning from the first victory. Both completion mutations retain their idempotency attempts; the server remains authoritative for Shop ownership, objective progress, and player revision.
- The Farm Scout uses the canonical dialogue run-node path after Loot. Tests that had duplicated literal Farm node counts were changed to assert authored graph shape or local transitions. The tester's broader conversation-layout feedback is recorded for the planned visual pass; Package 9 UAT still requires explicit acceptance.
- Focused verification: Boss endpoint 13 tests / 127 assertions; Chaos reload 2 tests / 85 assertions; Shrine reload 2 tests / 99 assertions; affected RunScene/BattleScene/read-contract browser suites 88 tests. Authored content validation regenerated the browser-safe projection at revision `e050a72e25550cabc2d3b716a813fc6e41422e35e4d189a3974bdd50225687ce`; dialogue bodies, responses, and Boss encounter fields are absent from that projection. Deterministic first/return Boss captures passed at Compact, 1600x900, and Wide landscape sizes. The first complete package gate exposed two direct-query reload fixtures lacking the production dialogue/unlock dependencies (797 backend tests / 5,750 assertions, 2 errors); both fixtures now use the production query factory and passed focused reruns. Final `npm run verify:package` passed: 797 backend tests / 5,829 assertions, 530 frontend tests, PHP front controller syntax, content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. Package 9 remains In Progress for renewed manual UAT and explicit tester acceptance.

#### Blocking UAT finding: Objectives destination has no production entries

- Reproduction: Objectives opens from Camp on the local UAT account, but the list says no objectives are available. The production content registry had zero `objective` definitions; all prior objective examples were test-only fixtures. The endpoint and screen correctly reflected that empty authored catalog.
- Correction: add two small Farm objectives based on existing authoritative gameplay facts: speak with the Farm Scout; and defeat a Mudwrestler plus complete a Farm run. No new requirement kind, reward, balance grant, or client progress authority was added. The local UAT account now reads both entries as `unstarted`; previously completed actions are not backfilled, so renewed UAT must perform new qualifying actions.
- The objective test fixture now excludes production objective definitions before inserting its own scenario objectives. This preserves isolation and avoids assuming that production content is empty.

#### UAT review: dialogue file structure and content-specific combat tests

- All Farm dialogue definitions and the Scout map encounter are now grouped in one `dialogue/the-farm.json` region file. The dialogue encounter wrapper no longer duplicates the Scout title/body; the dialogue definition owns that player-visible text. Stable encounter and dialogue IDs remain distinct because persisted run nodes reference an encounter while dialogue read/completion uses a dialogue identity. `kind` and `family` remain required for generic encounter validation and server dispatch; removing them would change the accepted persisted and API contract rather than simplify file organization.
- Retired the Farm and Mountains combat content validation files, which repeated roster-specific names, lengths, balance, and exact status assertions. A single generic enemy combat contract test now verifies normalization against referenced definitions, browser secrecy, and malformed enemy/encounter rejection across authored combat encounters without hard-coding a region, enemy, or map size. Existing combat engine tests continue to own handler behavior.
- Focused verification: generic enemy combat contract 3 tests / 350 assertions; Objective 23 tests / 190 assertions; Dialogue 12 tests / 138 assertions; Farm 36 tests / 592 assertions. The complete `npm run verify:package -- --continue-on-failure` gate passed: 757 backend tests / 5,918 assertions, 530 frontend tests, PHP front controller syntax, content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. Browser-safe content revision: `8a72cc883c8c992abc81135bdf6907018ffc3c68cda21b9d0393413cf203915a`. Package 9 remains In Progress for renewed manual UAT and explicit tester acceptance.

#### UAT maintenance: retire unreachable prototype UI and Debug code

- At the tester's request, a route/import audit confirmed that the old Angular Debug Panel, home, Regions, run, and Wrong Machine pages are absent from the live Angular route table and have no production callers. Retired those pages, their page-only services/tests and Debug API types, plus the unregistered prototype `DebugController`/`DevToolsService` pair. The detached Codex prototype spec was also retired after its own README's Phaser-coverage condition was met.
- Kept the live Phaser game, auth shell, deterministic capture hooks, and authenticated Warband UAT fixture endpoint. No objectives, dialogue, gameplay, or account state changed in this cleanup.
- `npm run verify:package -- --continue-on-failure` passed: 757 backend tests / 5,918 assertions, 514 frontend tests, front-controller syntax, content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. The frontend count decreased by 16 tests from removing specs attached to unreachable pages/services. Package 9 remains In Progress for the user's ongoing objectives UAT.

#### Blocking UAT finding: repeat Mudking dialogue request never reaches PHP

- Reproduction (local user 1, Farm run 17, Boss node 105): selecting the return-visit Mudking conversation shows “The response is uncertain. Retry the same conversation.” Backend logs show `OPTIONS /api/v1/dialogues/dialogue.farm.mudking_return/complete` returning the PHP built-in server's HTML 404 before `public/index.php` runs. There is no `complete_dialogue` receipt, and the Boss remains available. The dotted dialogue ID is interpreted as a static-file path by the local built-in server when no router script is configured.
- Correction: the Docker PHP server now uses `public/index.php` as its router script, matching the existing Apache rewrite behavior for API paths. A canonical backend test boots the configured PHP server and proves that dotted dialogue and ordinary run paths reach front-controller preflight handling. The local backend was rebuilt and restarted; the dotted preflight now returns 204, and an unauthenticated POST reaches PHP and returns JSON 401. Farm run 17 and Boss node 105 remain active/available in MySQL. The backend restart may require the tester to sign in again before resuming UAT.
- Focused verification: Dialogue, Boss-node, and built-in-server routing suites passed: 25 tests / 268 assertions. The complete `npm run verify:package -- --continue-on-failure` gate passed: 758 backend tests / 5,927 assertions, 514 frontend tests, PHP front-controller syntax, content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. Package 9 remains In Progress pending renewed Mudking UAT and explicit acceptance.

#### Blocking UAT finding: Mudking Codex entry remains locked after conversation

- Reproduction: local user 1 has a finalized `dialogue.farm.mudking_return` challenge receipt with `knowledge: null`, and Codex ownership contained only `codex.farm.old_paths`. The authored `codex.farm.mudking` entry had no acquisition event on either Mudking challenge choice. The dialogue endpoint and client reflected the authored omission correctly.
- Correction: both first and return Mudking challenge choices now reference one authored, certain Codex reward event for `codex.farm.mudking`. The standard dialogue transaction grants the entry once, reports `already_owned` on later conversations, and preserves exact-key replay. Generic tests verify that authored Codex entries have dialogue acquisition paths and that every eligible rewarded dialogue choice grants/replays through the authoritative command without asserting a fixed content count.
- Local UAT account repair: the recorded return-conversation receipt was validated, then the new event was applied once through `RewardApplicationService` with that receipt's source identity in one transaction. Ownership now contains both Farm Codex entries; one Mudking event is `applied`; `player_revision` advanced from 24 to 25; the original immutable dialogue receipt remains `knowledge: null`. The temporary repair script was removed. The tester should reload Codex to refresh the cached read.
- Focused verification: Codex acquisition and dialogue lifecycle suites passed: 7 tests / 68 assertions. Content validation regenerated the browser-safe projection at revision `0279255a9c7f34e7ea8ca9eb8748c26acb0eeb62435f5e21f6a9fe524962c044`. The complete `npm run verify:package -- --continue-on-failure` gate passed: 760 backend tests / 5,945 assertions, 514 frontend tests, PHP front-controller syntax, content validation, LLM/context, docs lint, frontend production build, bundle budget, and diff whitespace. Package 9 remains In Progress pending renewed UAT and explicit acceptance.
