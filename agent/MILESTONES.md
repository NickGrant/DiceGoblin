# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 12.5 - Opening Narrative Progression Alignment

**Status:** Active

### Related Issues
- Milestone 12.5 Package 5 - Mountains boss and visible Swamp handoff

Milestones 1-12 are complete and passed their required manual UAT. Milestone 13 planning exists but implementation has not started and is deferred until Milestone 12.5 is complete.

Milestone 12.5 Package 1 completed the evidence-based opening progression audit through `e746b37f254e4d4d35bab1bd359aed2be38f1a3a`. The audit is recorded in `documentation/07-development-path/m12-5-opening-progression-audit.md`; focused existing-behavior proof passed 38 tests / 443 assertions.

Milestone 12.5 Package 2 completed the Farm/onboarding alignment through implementation `e373512194628aa227589d5bde297fa65aa3cc24` and narrative correction `868e75bb09b92b0f03657a7b2de846dde43d5fec`, with evidence through `4a173da769a7e49239168d29522f1e5c3aa5eb8c`.

Milestone 12.5 Package 3 completed the post-Farm Whim transition through implementation `01da731f7faef5ccd202a475a15f3289f0af44eb`, with evidence through `362a77d45022f72b8fc625062149f5cb3c39ade9`.

Milestone 12.5 Package 4 completed the Mountains investigation and first Archivist conversation through implementation `ae75a0a2b1c2e1cf544998135aa2fe1db761cda7`, with evidence through `cf9b99cba3f2aab7acb48a95fe6d6df11a228a6b`. The Mountains run now contains a kobold-trail investigation and the canonical first direct Archivist conversation as run-state-gated dialogue nodes. Mountains combat remains kobold-only, retained knowledge is idempotent/reload-safe, and neither Swamp access nor Wrong Machine capability access is granted. Full verification passed: backend 713 tests / 5,914 assertions and frontend 469 tests.

After M12 acceptance, the opening campaign sequence was clarified and recorded canonically in `documentation/01-lore/01-story-and-biome-progression.md`. Because Farm and Mountains were implemented before that narrative sequence became canonical, this milestone aligns the existing opening progression before Swamp/M13 work begins. This does not reopen or invalidate M12's accepted onboarding architecture.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Align the implemented Mystic Cave -> Farm -> Mountains handoff with the canonical opening story before Swamp development begins:
- Mystic Cave establishes the Basic Goblin Warband and Farm access;
- first Mudking victory rescues the Tooth Collector and makes the Shop available;
- the post-Farm Whim beat sends the player after the missing Wrong Machine and unlocks Mountains;
- Mountains continue the Wrong Machine trail and contain the player's first direct conversation with The Archivist;
- first Mountains boss victory establishes that the Wrong Machine has moved to the Swamps and unlocks Swamps;
- the handoff into M13 leaves the Wrong Machine unavailable, the Archivist already introduced, and Archivist troops not yet encountered.

Milestone 13 then owns Swamp content, the first combat encounters with Archivist/Order troops, Swamp boss recovery of the Wrong Machine, Frog Kin integration, first reconstruction follow-up, broader-map opening, and the third-region parity proof.

### Architectural Direction

- `documentation/01-lore/01-story-and-biome-progression.md` is the canonical authority for opening story order and story-gated progression.
- Follow `agent/CONTEXT_ROUTER.md` for architecture authorities. Reuse accepted reward/unlock, dialogue/Codex, region/run, API, storage, Phaser runtime, revision, and idempotency boundaries.
- Backend/MySQL remains authoritative for durable player state and mutations. Phaser remains the gameplay client; Angular remains the host/auth/account shell.
- Authored definitions belong in canonical content JSON rather than SQL or client constants.
- Preserve the accepted M12 onboarding architecture. Do not add generic `story_progress`, chapter counters, `has_seen_dialogue`, tutorial-history, or a catch-all progression store.
- Prefer the smallest durable result already represented by accepted progression concepts: permanent region/system/capability unlocks, unique rewards, Codex knowledge where genuinely player-facing, and replayable/eligible dialogue.
- Player-facing narrative transitions matter. Do not collapse every story beat into invisible boss rewards merely because the resulting durable unlock can be granted transactionally.
- Do not begin M13 implementation until M12.5 manual UAT is explicitly accepted.

### Package Queue

1. **Opening progression implementation audit.** Complete.
2. **Farm access and Tooth Collector rescue correction.** Complete.
3. **Post-Farm Whim and Mountains unlock correction.** Complete.
4. **Mountains trail and Archivist introduction.** Complete. Mountains now advance the Wrong Machine investigation through run-state dialogue and contain the first direct Archivist conversation; combat remains kobold-only and no Swamp/Machine access is granted.
5. **Mountains boss and visible Swamp handoff.** Current. Make first boss victory establish the Machine's movement to Swamps and grant a durable Swamp region unlock. Show Swamps as unlocked/visible while run entry remains unavailable until Milestone 13 supplies Swamp gameplay. Leave Wrong Machine access locked and add no Swamp enemies/run graph.
6. **Integrated opening-arc verification and technical closure.** Planned. Prove fresh-account and returning/reload flows across Mystic Cave -> Farm -> Tooth Collector/Shop -> Whim -> Mountains -> Archivist -> Mountains boss -> Swamp available, including idempotency/reconnect/revision/secrecy and regression coverage.
7. **Focused manual UAT before M13.** Planned. Manually verify the corrected opening progression and explicitly accept the handoff state before reactivating M13.

### Required Handoff State to Milestone 13

At M12.5 completion, a fresh player who follows the canonical opening path must be able to reach a state where:
- the Basic Goblin Warband exists and Farm is available;
- the Tooth Collector has been rescued and Shop access is available;
- the Whim has directed the player toward the Wrong Machine and Mountains are available;
- the player has directly encountered/conversed with The Archivist in Mountains;
- the Mountains boss has revealed/established the Wrong Machine's movement to Swamps, which is visible and unlocked while run entry waits for Milestone 13 gameplay;
- `capability.wrong_machine_access` is still absent;
- no Frog Kin reconstruction has occurred;
- no Archivist/Order combat troops have been encountered as part of the canonical opening path;
- no broader post-Swamp map opening has occurred.

### Deferred Milestone 13 Plan

Milestone 13 remains the Swamp and parity milestone after M12.5 acceptance:
- Swamps -> Frogmen -> Frog Kin;
- Swamp contains the first combat encounters with Archivist/Order troops;
- first successful Swamp boss defeat grants `capability.wrong_machine_access` as the permanent Wrong Machine recovery/unlock;
- Frog Kin uses the accepted Wrong Machine reconstruction architecture;
- first successful Wrong Machine use leads to new Whim dialogue that opens the broader map;
- Swamp remains the third-region repeatability/parity proof through generic architecture.

### Sequencing Notes

- M12.5 is a correction/alignment milestone created after the opening story became explicit. It does not retroactively mark M12 UAT as failed.
- M13 Package 1 was planned but the coding agent had not started it when M12.5 was inserted. No M13 implementation needs to be unwound.
- Packages 2-4 now align the opening through the Mountains investigation and first Archivist conversation. Package 5 owns only the Mountains boss conclusion and Swamp handoff.
- Package 7 is the explicit manual UAT gate. Do not reactivate M13 until the user explicitly accepts M12.5 UAT.
