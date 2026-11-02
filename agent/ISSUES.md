# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 6 - Integrated opening-arc verification and technical closure

**Status:** In Progress
**Priority:** High

#### Problem
Packages 2-5 now implement the corrected opening progression in their individual ownership areas. Before manual UAT, the complete path must be exercised as one authoritative lifecycle to prove that those independently accepted transitions compose correctly across registration, onboarding, Farm, Tooth Collector/Shop, post-Farm Whim, Mountains narrative, Mountains boss, and the visible-but-non-playable Swamp handoff.

Package 6 is an integration and technical-closure package. It should add the minimum end-to-end/focused proof needed to expose cross-package defects and correct only defects demonstrated by that proof. It must not become another narrative/content expansion package.

#### Canonical target
A fresh player can progress through the complete M12.5 opening arc:
- initial Whim onboarding establishes the four-unit Basic Goblin Warband and durable Farm access;
- Farm/Mudking victory rescues the Tooth Collector and grants Shop access, with reload-safe rescue follow-up;
- the server-gated Camp Whim beat introduces the missing Wrong Machine search and grants Mountains access;
- Mountains advances the trail, includes the first direct Archivist conversation, and keeps combat kobold-only;
- first Mountains boss victory establishes the Machine's movement to Swamps and grants durable `unlock.region.swamps`;
- Swamps are visible/unlocked but cannot start until M13 supplies gameplay;
- `unlock.capability.wrong_machine_access` remains absent and no M13 gameplay has occurred.

#### Accepted baseline
- Package 2 accepted through implementation `e373512194628aa227589d5bde297fa65aa3cc24`, correction `868e75bb09b92b0f03657a7b2de846dde43d5fec`, evidence `4a173da769a7e49239168d29522f1e5c3aa5eb8c`.
- Package 3 accepted through implementation `01da731f7faef5ccd202a475a15f3289f0af44eb`, evidence `362a77d45022f72b8fc625062149f5cb3c39ade9`.
- Package 4 accepted through implementation `ae75a0a2b1c2e1cf544998135aa2fe1db761cda7`, evidence `cf9b99cba3f2aab7acb48a95fe6d6df11a228a6b`.
- Package 5 accepted through implementation `d8698d695f46ddeb57616535a5ac11dafd9f1720`, evidence `1f7470fd591e9ce32e08244be8aa3d8c1730bf3f`.
- Package 5 full verification passed at backend 714 tests / 5,937 assertions and frontend 470 tests.

#### Required implementation
1. **Integrated fresh-account proof.** Exercise the opening lifecycle from real registration/session/bootstrap through onboarding/provisioning, Farm, Tooth Collector rescue/follow-up, Camp Whim, Mountains run narrative/boss, and final Swamp projection. Prefer real public application boundaries and existing commands/controllers over directly seeding intermediate progression state.
2. **Transition assertions.** At each boundary prove the durable authority changes only when intended: Farm after provisioning; Shop/rescue after Mudking; Mountains/search knowledge after Whim; Mountains narrative knowledge through reached run nodes; Swamp unlock/destination after Mountains boss.
3. **Reload/reconnect proof.** Rebuild services/session/bootstrap at meaningful pending boundaries and prove server-owned state restores the next required interaction without client-local history: Tooth Collector follow-up, post-Farm Whim, Mountains narrative nodes, post-boss chart/Swamp handoff as applicable.
4. **Replay/idempotency/revision proof.** Exercise representative exact-key retries and repeat-run/reward cases across the integrated lifecycle. Confirm original responses are stable, unique rewards remain unique, and player revision does not increment on exact-key replay.
5. **Authorization/projection proof.** Verify available playable regions and unlocked destination regions remain distinct. Farm/Mountains become startable only through their durable unlocks; Swamps become unlocked/visible after Mountains boss but remain rejected by run start without M13 run content, with no energy/run/revision mutation from the rejected start.
6. **Narrative boundary proof.** Confirm the opening path does not expose the Wrong Machine search before the post-Farm Whim beat; first direct Archivist conversation occurs in Mountains; Mountains combat is kobold-only; no Archivist/Order combat occurs before Swamp; and Swamp destination knowledge does not imply Machine possession.
7. **M13 boundary proof.** At final handoff assert `unlock.capability.wrong_machine_access` absent, no Frog Kin reconstruction, no Swamp run/encounters/enemies, and no broader-map opening.
8. **Technical correction only.** If the integrated proof exposes a real composition defect, fix the smallest owning architecture/content path and record it. Do not introduce speculative cleanup, generic story state, new narrative beats, or M13 gameplay.
9. **Client integration regression.** Verify Camp/routing projection across the relevant authoritative states, including unlocked-but-disabled Swamps and existing responsive/portrait behavior. Add new deterministic captures only if needed to prove a changed or previously unverified state.
10. **Documentation closure.** Update package evidence and any canonical architecture docs only where the integrated proof reveals a durable contract that is not already recorded. Do not duplicate package history into unrelated docs.

#### Architectural constraints
- Follow `agent/CONTEXT_ROUTER.md` and the canonical story/progression/reward/API/storage/Phaser/testing documents it routes to.
- Backend/MySQL remains authoritative. Phaser renders/routes authoritative state; Angular remains host/auth/account shell.
- Do not add generic story/chapter/seen/history state.
- Do not weaken region-start authorization or create placeholder Swamp gameplay.
- Do not reopen accepted Warband, Farm, Shop, Whim, Mountains, or reward architecture unless integration proof demonstrates a defect.
- Prefer lifecycle/integration tests over duplicated literal content assertions.

#### Minimum proof
1. Real fresh registration begins in Whim onboarding with Farm not prematurely available.
2. Provisioning creates the accepted four-unit Warband/active squad and durable Farm unlock.
3. Farm is startable; Shop and Mountains are not yet available.
4. First Mudking victory plus required follow-up durably establishes Tooth Collector rescue and Shop, but not Mountains.
5. Reload before post-Farm Whim completion restores that pending interaction.
6. Completing Whim grants search knowledge and Mountains once; Mountains becomes startable.
7. Mountains run reaches kobold trail then first direct Archivist dialogue through authoritative run state; combat remains kobold-only.
8. Reload at representative Mountains narrative state restores the available node/dialogue.
9. First Mountains boss grants Swamp destination knowledge and `unlock.region.swamps` once; Wrong Machine capability remains absent.
10. Post-boss reload projects Swamps as unlocked/visible but not available/startable.
11. Rejected Swamp start creates no run and spends/mutates nothing.
12. Representative exact-key replays do not duplicate rewards or revisions; repeat Mountains progression remains valid with already-owned unique results.
13. No Frog Kin, Swamp enemies/graph, Order troop combat, Wrong Machine recovery, or broader-map opening exists at handoff.
14. Existing package-focused regressions and full package quality gates pass.

#### Verification
Run a focused integrated lifecycle suite plus the affected onboarding, Farm, Shop, dialogue/Codex, region/run, Mountains, reward/unlock, idempotency/revision, bootstrap, and Phaser runtime/Camp regressions. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`.

#### Scope exclusions
- new story beats or dialogue beyond corrections required by demonstrated integration defects;
- Swamp run graph/playable content;
- Frogmen or Archivist/Order combat troops;
- Swamp boss behavior;
- Wrong Machine capability access/recovery/reconstruction;
- Frog Kin;
- first Wrong Machine-use follow-up;
- broader-map opening;
- broad visual redesign;
- M13 parity work or M14 hardening.

#### Completion evidence required
Record implementation/test SHA(s); exact integration test path(s); lifecycle boundaries exercised; any defects found and smallest corrections made; reload/reconnect checkpoints; replay/revision evidence; final unlock/knowledge state; proof Swamp is unlocked but unsupported for start; proof M13-only state/content remains absent; focused backend/frontend counts; any deterministic captures reviewed; and full package-gate results.

Leave Package 6 **In Progress** for architectural review. Do not activate Package 7 automatically from the coding agent.
