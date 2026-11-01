# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 13 - Swamp and Parity Audit

**Status:** Active

### Related Issues
- Milestone 13 Package 1 - Canonical Swamp and Frogmen authored-content foundation

Milestones 1-12 are complete and passed their required manual UAT.

Milestone 10 closed after Package 10 manual UAT acceptance through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`.

Milestone 11 closed after Package 9 focused manual UAT acceptance on 2026-10-31. Its accepted baseline includes Codex ownership/read/presentation, knowledge-aware replayable dialogue, objectives/bounties, authoritative gameplay-fact progress, automatic completion/reward finalization, production Farm dialogue/Codex/objectives, dotted-dialogue routing correction, and retirement of obsolete prototype paths.

Milestone 12 closed after Package 7 focused manual UAT acceptance on 2026-10-31. Packages 1-6 established and technically closed the authoritative Mystic Cave onboarding lifecycle. UAT then identified and corrected two concrete presentation/gameplay defects: the starting Warband now provisions four authored Basic Goblins in valid frontline/backline positions rather than one unit, and normal unit creation now assigns generated personal names rather than unit-type labels. The user explicitly accepted the corrected UAT on 2026-10-31. Accepted UAT corrections are through `c40959b37fe2d08a4b01fc8673b103a5ab8fe2b9`.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Make the Swamps/frogmen/Frog Kin the third playable authored region and use it as the repeatability/parity proof for the completed vNext gameplay architecture:
- canonical Swamp region, Frogmen enemies, encounters, boss, rewards, knowledge, and progression authored through current JSON/content boundaries;
- a complete Swamp run through the same authoritative graph, combat, event/reward, persistence, reconnect, and terminal lifecycle used by Farm and Mountains;
- first defeat of the Swamp boss grants `capability.wrong_machine_access`, representing recovery/unlock of the Wrong Machine for normal reconstruction gameplay; repeat boss defeats must not duplicate this permanent unlock;
- Frog Kin integrated through the accepted Kin/Wrong Machine reconstruction architecture rather than a parallel lineage system;
- intentional use of the encounter-depth systems already introduced for Shrine/Chaos/hazard/multi-step interactions where appropriate for Swamp design;
- explicit audit of preserved prototype gameplay behavior, retaining only behavior still intended for vNext and recording durable decisions canonically;
- third-region proof that no Farm-, Mountains-, or Swamp-specific architectural branch is required.

Hardening/cutover remains Milestone 14. Island through Savanna and The Library remain future content production rather than vNext-overhaul prerequisites.

### Architectural Direction

- Follow `documentation/07-development-path/vnext-game-overhaul.md` for milestone order and `agent/CONTEXT_ROUTER.md` for narrow canonical authorities.
- `documentation/07-development-path/01-base-game-content-roster.md` canonically assigns Swamps -> Frogmen -> Frog Kin. Transitional content documents are design references only until their details are authored into canonical JSON.
- Reuse accepted region/run graph, combat, event/reward, Codex/dialogue/objective, Kin/Wrong Machine, economy, progression, API, storage, Phaser runtime, responsive, revision, and idempotency boundaries.
- Backend/MySQL remains authoritative for durable player state and mutations. Phaser remains the gameplay client; Angular remains the host/auth/account shell.
- Authored definitions belong in canonical content JSON rather than SQL or client constants.
- The Wrong Machine is a canonical Swamp progression reward: defeating the Swamp boss for the first time grants `capability.wrong_machine_access`. Mystic Cave onboarding only introduces the Machine as knowledge/future relevance and must not grant access. The first-boss reward must use the accepted permanent unlock/reward/idempotency model so later defeats are safe no-ops for the unique capability.
- Do not introduce a region-specific service/controller/state model when the accepted generic region/run architecture can express the behavior.
- Final game-wide visual polish remains deferred; new Swamp surfaces/assets must still be usable and responsive.

### Package Queue

1. **Canonical Swamp and Frogmen authored-content foundation.** Current. Establish the current Swamp region identity, Frogmen enemy/combat roster, boss identity, authored progression relationships, and the minimum canonical content required for later Swamp packages without implementing the full run lifecycle early. This package may author the Wrong Machine capability reward identity/reference needed by the boss lifecycle, but must not grant it yet.
2. **Swamp run graph and encounter-depth content.** Planned. Author the branch-capable Swamp graph and region-appropriate Combat/Loot/Rest/Hazard/Shrine/Chaos/dialogue interactions through the accepted generic node architecture.
3. **Swamp boss, terminal rewards, and progression lifecycle.** Planned. Complete authoritative boss/Exit behavior, finalized rewards, region progression consequences, reconnect/idempotency, and normal terminal reconciliation. The first successful Swamp boss defeat grants `capability.wrong_machine_access`; repeat defeats do not duplicate the permanent unlock.
4. **Frog Kin and Wrong Machine integration.** Planned. Add Frog Kin to the accepted reconstruction/lineage model and make reconstruction available through the Wrong Machine capability earned from the first Swamp boss defeat; do not create a second machine-access grant path.
5. **Phaser Swamp presentation and third-region routing.** Planned. Ensure Camp/Run/Battle/dialogue/encounter presentation handles the third region through existing runtime boundaries with responsive and reconnect-safe behavior.
6. **Preserved-gameplay parity audit.** Planned. Compare the implemented vNext core loop against intentionally preserved gameplay rules and identify only concrete missing behavior that belongs before cutover; record durable keep/change/drop decisions in canonical repository documentation.
7. **Swamp/parity integrated verification and technical closure.** Planned. Prove the third-region lifecycle, Frog Kin integration, Wrong Machine first-boss unlock, secrecy/security, idempotency/reconnect behavior, responsive presentation, and absence of region-specific architectural forks.
8. **Focused manual UAT before Milestone 14.** Planned. Manually verify the complete Swamp/Frog Kin/Wrong Machine unlock path and accepted parity corrections before hardening/cutover begins.

### Sequencing Notes

- Milestone 12 established server-authoritative onboarding with no tutorial-history state. The accepted starting Warband is four Basic Goblins: two Bruisers in frontline positions 2 and 5 and two Marksmen in backline positions 0 and 3, each with its authored basic ability and compatible cardboard d6. Unit creation uses the shared normal creation boundary and generates personal names for onboarding, Shop acquisition, and Kin reconstruction.
- Mystic Cave Wrong Machine knowledge does not grant `capability.wrong_machine_access`, physical possession, reconstruction authority, or Swamp progression. The canonical grant point is the first successful Swamp boss defeat in Package 3, using the existing permanent unlock/reward model.
- Swamps are the third-region repeatability proof, not an invitation to fork region architecture. Any apparent need for Swamp-specific infrastructure must be justified against the accepted generic run/event/combat/content systems before implementation.
- Shrine and Chaos node vocabulary was intentionally left test-only until Swamp content required it; Package 2 is the natural place to promote only the semantics actually used by the authored Swamp graph.
- Package 6 owns the explicit parity audit. Do not use prototype implementation shape as authority; use it only as behavioral evidence and reconcile any retained behavior into current canonical docs/content.
- Package 8 is the explicit manual UAT gate. Do not begin Milestone 14 or mark Milestone 13 complete until the user explicitly accepts UAT.
