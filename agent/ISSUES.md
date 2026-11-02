# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 3 - Post-Farm Whim and Mountains unlock correction

**Status:** In Progress
**Priority:** High

#### Problem
Package 2 corrected the onboarding/Farm boundary. Initial Mystic Cave onboarding now establishes the Basic Goblin Warband, Farm access, and the Tooth Collector rescue mission without prematurely introducing the Wrong Machine. First Mudking victory rescues the Tooth Collector and grants Shop access, while Mountains remain locked.

The canonical next beat is currently missing: after the Tooth Collector is recovered, the player must return to/interact with The Whim in Camp. The Whim introduces the missing Wrong Machine search, points the player toward the kobolds in the Mountains, and the completion of that player-facing beat unlocks Mountains. Mountains must not become available merely because the Farm boss was defeated or Shop access exists.

Package 3 owns this post-Farm Whim transition only. Package 4 owns Mountains trail content and the first direct Archivist conversation.

#### Canonical target
- Before Tooth Collector rescue/follow-up, no post-Farm Wrong Machine search is available and Mountains remain locked.
- After the Tooth Collector rescue is durably established, Camp exposes an appropriate Whim interaction.
- The Whim explains that the Wrong Machine is missing and directs the player to investigate the kobolds/Mountains.
- Completing the authoritative Whim beat grants meaningful retained knowledge for the search and `unlock.region.mountains` exactly once.
- Mountains become visible/available only after that player-facing beat completes.
- Reload/reconnect before completing the beat restores its availability; reload after completion preserves Mountains access without replaying the interaction as required progression.
- Wrong Machine capability access remains locked. The player is learning/searching for the machine, not obtaining or using it.

#### Accepted baseline
- Package 2 is accepted through implementation `e373512194628aa227589d5bde297fa65aa3cc24`, narrative correction `868e75bb09b92b0f03657a7b2de846dde43d5fec`, and evidence `4a173da769a7e49239168d29522f1e5c3aa5eb8c`.
- Farm access is `unlock.region.the_farm` and uses normal region authority.
- Tooth Collector rescue is represented by the post-Mudking Farm dialogue/Codex path and is reload safe.
- Shop access remains `unlock.capability.shop_access`, granted by first successful Mudking boss resolution.
- Farm boss no longer grants `unlock.region.mountains`.
- Initial Mystic Cave onboarding has one Whim knowledge step and does not author/grant the former pre-Farm Wrong Machine knowledge path.
- Unit ownership remains the onboarding completion marker; do not reopen onboarding architecture.

#### Required implementation
1. **Author the post-Farm Whim beat.** Add canonical authored dialogue/encounter/event/reward/Codex content as needed for The Whim to introduce the missing Wrong Machine search after the Tooth Collector rescue. The copy should establish that the Wrong Machine is missing and that the kobolds/Mountains are the next lead. Do not imply the player already has access to or control of the machine.
2. **Server-owned eligibility.** The interaction must be unavailable before the durable Tooth Collector rescue result and available afterward until completed. Do not rely on a client-only condition. Use accepted progression/dialogue/Codex/unlock concepts and the smallest authoritative eligibility mechanism. Do not add generic story flags, chapter counters, `has_seen_dialogue`, or catch-all history state.
3. **Mountains grant.** Completion of the Whim progression beat must grant `unlock.region.mountains` through normal unique reward/unlock authority in the same idempotent player intention that completes the beat. Do not restore Mountains to the Farm boss reward and do not grant Mountains merely on Shop ownership/bootstrap read.
4. **Meaningful knowledge.** Record only player-facing knowledge that matters for the continuing search. If a Wrong Machine/search Codex entry is authored, it should represent what the Whim has now actually told the player at this point in the story. Do not resurrect the removed pre-Farm Mystic Cave onboarding step.
5. **Camp presentation/routing.** Expose the eligible Whim interaction from Camp through the existing Phaser/runtime architecture. Backend state remains authoritative; Phaser must not invent eligibility or persist completion locally. Preserve current responsive/portrait behavior and existing Camp system affordances.
6. **Reload/reconnect.** If the player becomes eligible and reloads before completing the Whim beat, the interaction remains available. After successful completion, Mountains remain available from durable unlock state and the required progression interaction is no longer pending. Exact-key replay returns the original result without duplicate Codex/unlock/revision mutation.
7. **Safe projection.** Client-facing state may reveal the eligible/current Whim interaction and resulting Mountains availability, but must not leak Mountains future topology/rewards, Swamp unlock details, Wrong Machine recovery mechanics, or private prerequisites.
8. **Package boundary.** Do not add Mountains run dialogue, Archivist presentation, Swamp identity/unlock, Archivist/Order troops, Wrong Machine access/reconstruction, Frog Kin, or broader-map opening. Those remain Packages 4-5/M13.

#### Architectural constraints
- `documentation/01-lore/01-story-and-biome-progression.md` owns narrative order.
- Follow `agent/CONTEXT_ROUTER.md` for progression, authored content, reward/unlock, API/storage, Phaser/runtime, and testing authorities.
- Backend/MySQL remains authoritative. Phaser is the gameplay client; Angular remains host/auth/account shell.
- Reuse existing permanent unlock/reward semantics for `unlock.region.mountains`.
- Prefer meaningful Codex ownership as retained knowledge where appropriate, but do not use Codex as a disguised generic story-history ledger.
- Ordinary dialogue must not become globally readable/completable merely because its ID is known if it owns progression. Enforce the post-Farm eligibility boundary server-side.
- Do not add a parallel story-state subsystem.
- Preserve player-revision, exact-key idempotency, and stale-response reconciliation semantics.

#### Minimum proof
1. A provisioned player before Tooth Collector rescue cannot access/complete the post-Farm Whim progression beat and cannot start Mountains.
2. First Mudking victory still grants Shop but not Mountains.
3. Completing the Tooth Collector rescue/follow-up makes the post-Farm Whim beat authoritatively eligible.
4. Reload/reconnect while that beat is pending restores it from server-owned state.
5. Completing the Whim beat grants the intended search knowledge and `unlock.region.mountains` exactly once.
6. Mountains becomes available/startable because the durable region unlock now exists.
7. Exact-key replay/retry does not duplicate knowledge, unlocks, or revision changes.
8. Reload after completion preserves Mountains access and does not require replaying the progression conversation.
9. `capability.wrong_machine_access` remains absent.
10. No Mountains Archivist content, Swamp content, Frog Kin, Order troops, or broader-map opening is introduced.
11. Existing onboarding, Farm, Tooth Collector, Shop, dialogue/Codex, region-start, Camp/runtime, revision, and idempotency regressions pass.

#### Verification
Run focused backend tests for dialogue eligibility/completion, Tooth Collector prerequisite, reward/unlock application, region availability/start authorization, exact-key replay, revision, and reload/reconnect. Run focused frontend tests for Camp Whim presentation/routing, authoritative refresh/reconciliation, and affected responsive/portrait behavior. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure` because this package changes production progression/content/client behavior.

#### Scope exclusions
- changes to the accepted starting Warband/Farm provisioning flow;
- changes to Tooth Collector rescue or Shop grant except regressions necessary to support the eligibility prerequisite;
- Mountains run graph narrative changes;
- first direct Archivist conversation;
- Swamp identity/unlock/presentation;
- Archivist/Order combat troops;
- Wrong Machine capability access or reconstruction;
- Frog Kin;
- broader-map opening;
- broad visual redesign;
- M13 parity work or M14 hardening.

#### Completion evidence required
Record implementation SHA(s); exact authored content and backend/client paths changed; the authoritative prerequisite used for Whim eligibility; how the interaction is surfaced in Camp; the event/reward path that grants Mountains; retained knowledge identity and semantics; proof of pending reload recovery and post-completion durability; exact-key replay/revision results; focused backend/frontend counts; content revision if changed; and full package-gate results.

#### Implementation evidence (awaiting architectural review)
- Implementation: `01da731f7faef5ccd202a475a15f3289f0af44eb`. Authored `backend/content/{dialogue,events,rewards,codex}/camp.json` and mirrored test fixtures; client projection revision `83051ecee55f92cdfaf5def530125d681925dcd576b856ac88a0cd67ec908a5f` in `frontend/public/game-content.json`.
- `DialogueAvailabilityPolicy` gates `dialogue.camp.whim_machine_search` on durable `codex.farm.tooth_collector_rescued` and Shop ownership, while search knowledge and Mountains unlock are absent. `DialogueQuery`, `CompleteDialogueCommand`, and `GameBootstrapQuery` enforce/project that eligibility. Bootstrap exposes only nullable `progression.camp_whim_dialogue_id`; Camp renders a Whim action from it, and `PostFarmWhimFlow`/`PostFarmWhimScreen` read and complete the server dialogue.
- Choice `follow_lead` resolves `event.camp.whim_machine_search` through `reward_definition.camp.whim_machine_search`: guaranteed `codex.camp.wrong_machine_search` (the missing machine and kobold/Mountains lead) plus guaranteed `unlock.region.mountains` in one transaction. `CompleteDialogueCommand` applies both and returns the public unlock result. Wrong Machine capability access is never granted.
- `PostFarmWhimProgressionIntegrationTest` proves no pre-rescue read/complete/Mountains start; Shop alone is insufficient; a new service/bootstrap restores the pending beat after rescue; completion grants each durable identity once and increments revision once; exact-key replay returns the original result with no further mutation; a different key cannot repeat it; reload preserves Mountains access, clears the pending interaction, and permits a Mountains run. Existing Farm boss regression proves Shop without Mountains and pending Whim only after the Tooth Collector follow-up.
- Focused verification: post-Farm/backend content tests 4 tests/48 assertions, affected dialogue/Farm/bootstrap/onboarding/region tests 60 tests/604 assertions, Codex ordering regression 4 tests/32 assertions; frontend Whim flow 4 tests plus Camp projection/reflow coverage. Deterministic Camp captures reviewed at 844x390, 1600x900, and 2560x1080 in `artifacts/verification/camp-whim-*.png`.
- Full `npm run verify:package -- --continue-on-failure`: **PASS**, backend 713 tests/5,889 assertions, frontend 469 tests; LLM/context, docs lint, PHP syntax, content validation, frontend build, bundle, and diff checks passed. See `artifacts/verification/package-summary.json`.

Leave Package 3 **In Progress** for architectural review. Do not activate Package 4 automatically from the coding agent.
