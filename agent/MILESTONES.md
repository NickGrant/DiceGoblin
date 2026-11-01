# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 12.5 - Opening Narrative Progression Alignment

**Status:** Active

### Related Issues
- Milestone 12.5 Package 1 - Opening progression implementation audit

Milestones 1-12 are complete and passed their required manual UAT. Milestone 13 planning exists but implementation has not started and is deferred until Milestone 12.5 is complete.

Milestone 10 closed after Package 10 manual UAT acceptance through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`.

Milestone 11 closed after Package 9 focused manual UAT acceptance on 2026-10-31. Its accepted baseline includes Codex ownership/read/presentation, knowledge-aware replayable dialogue, objectives/bounties, authoritative gameplay-fact progress, automatic completion/reward finalization, production Farm dialogue/Codex/objectives, dotted-dialogue routing correction, and retirement of obsolete prototype paths.

Milestone 12 closed after Package 7 focused manual UAT acceptance on 2026-10-31. Packages 1-6 established and technically closed the authoritative Mystic Cave onboarding lifecycle. UAT then identified and corrected two concrete presentation/gameplay defects: the starting Warband now provisions four authored Basic Goblins in valid frontline/backline positions rather than one unit, and normal unit creation now assigns generated personal names rather than unit-type labels. The user explicitly accepted the corrected UAT on 2026-10-31. Accepted UAT corrections are through `c40959b37fe2d08a4b01fc8673b103a5ab8fe2b9`.

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
- Exact implementation package boundaries after Package 1 must be based on the audit of current source/content, not assumptions from older prototype behavior or chat history.
- Do not begin M13 implementation until M12.5 manual UAT is explicitly accepted.

### Package Queue

1. **Opening progression implementation audit.** Current. Trace the actual fresh-player path from M12 onboarding through Farm and Mountains against the canonical story document. Inventory current authored content, unlock/reward ownership, dialogue presentation, Shop availability, region availability, first-victory behavior, Phaser routing, and tests. Produce a concrete discrepancy/correction plan; make no broad production rewrite merely to match an assumed design.
2. **Farm access and Tooth Collector rescue correction.** Planned from Package 1 audit. Make Farm's onboarding grant a durable region unlock; author the Whim's rescue mission and name the Tooth Collector. Preserve the first Mudking victory's existing Shop unlock, make the rescue/follow-up reload safe, and retain repeat Farm behavior.
3. **Post-Farm Whim and Mountains unlock correction.** Planned from Package 1 audit. Remove the Mountains unlock from the Farm boss; add a server-gated Camp Whim beat after rescue that establishes the Wrong Machine search and grants Mountains through existing reward/unlock authority.
4. **Mountains trail and Archivist introduction.** Planned from Package 1 audit. Add the Wrong Machine trail and first direct Archivist dialogue through the existing run dialogue/Codex path. Retain kobold-only combat before Swamp.
5. **Mountains boss and visible Swamp handoff.** Planned from Package 1 audit. Make first boss victory establish the Machine's movement and grant a Swamp region unlock. Show Swamps as unlocked while entry waits for Milestone 13 gameplay; leave Wrong Machine access locked and add no Swamp run or enemies.
6. **Integrated opening-arc verification and technical closure.** Planned. Prove fresh-account and returning/reload flows across Mystic Cave -> Farm -> Tooth Collector/Shop -> Whim -> Mountains -> Archivist -> Mountains boss -> Swamp available, including idempotency/reconnect/revision/secrecy and regression coverage.
7. **Focused manual UAT before M13.** Planned. Manually verify the corrected opening progression and explicitly accept the handoff state before reactivating M13.

Package count and boundaries may be consolidated after Package 1 if the audit proves some planned corrections are already correct or can be safely delivered together. Do not create work solely to preserve this provisional package count.

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

Milestone 13 remains the Swamp and parity milestone after M12.5 acceptance. Its already-set canonical constraints remain valid:
- Swamps -> Frogmen -> Frog Kin;
- Swamp contains the first combat encounters with Archivist/Order troops;
- first successful Swamp boss defeat grants `capability.wrong_machine_access` as the permanent Wrong Machine recovery/unlock;
- Frog Kin uses the accepted Wrong Machine reconstruction architecture;
- first successful Wrong Machine use leads to new Whim dialogue that opens the broader map;
- Swamp remains the third-region repeatability/parity proof through generic architecture.

### Sequencing Notes

- M12.5 is a correction/alignment milestone created after the opening story became explicit. It does not retroactively mark M12 UAT as failed.
- M13 Package 1 was planned but the coding agent had not started it when M12.5 was inserted. No M13 implementation needs to be unwound.
- Package 1 must consult current source/tests as implementation evidence and current canonical docs as requirements. Prototype material is historical evidence only.
- Do not promote directly from Package 1 audit into speculative fixes without recording the actual discrepancies and updating later package scopes if needed.
- Package 7 is the explicit manual UAT gate. Do not reactivate M13 until the user explicitly accepts M12.5 UAT.
