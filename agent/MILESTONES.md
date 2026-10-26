# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 11 - Knowledge and Objectives

**Status:** Active

### Related Issues
- Milestone 11 Package 2 - Phaser Codex surface + Camp integration

Milestones 1-10 are complete and passed their required manual UAT.

Milestone 10 closed after Package 10 manual UAT acceptance through `d97bcb52c4b834cf72a68d827eac8e196c05adbb`. The accepted Milestone 10 baseline includes authoritative exploration-frontier branching, generalized non-combat encounters, production Mountains Hazards and variable optional side trails, test-proven Shrine/Chaos families, run-scoped modifiers, contextual consumable/multi-step interactions, and generic Phaser RunScene integration. UAT corrections restored Shop/Academy progression gates, accepted finalized Combat/Boss material receipts in the client contract, and expanded Mountains route variety. Further map-generation variety is deferred.

The major game-wide visual/UI overhaul remains intentionally deferred.

### Outcome

Establish retained player knowledge and gameplay objectives without introducing a generic progression/history system:
- Codex entries as unique collectible rewards and durable knowledge;
- Codex read/presentation surfaces;
- knowledge-aware dialogue selection where meaningful dialogue grants Codex knowledge;
- authored objectives/bounties with domain-owned mutable progress;
- progress driven from authoritative gameplay facts/events rather than client claims;
- automatic objective completion and rewards through the accepted event-to-reward pipeline;
- reconnect-safe persistence and strict player-safe projection.

Mystic Cave/onboarding remains Milestone 12. Swamp/Frog Kin remains Milestone 13. Hardening/cutover remains Milestone 14.

### Architectural Direction

- Follow `documentation/07-development-path/vnext-progression-state-model.md` as the canonical progression/Codex/objective decision.
- Do not create a generic `player_progression`, story-flag, historical-event, `has_seen_dialogue`, or catch-all state layer.
- Codex entries are unique collectible rewards representing meaningful retained knowledge, lore, discoveries, or important dialogue information.
- Important dialogue may grant Codex entries; dialogue selection may use Codex ownership as a prerequisite. Dialogues remain replayable and duplicate Codex rewards resolve through the normal unique-reward rule.
- Objectives/bounties own their mutable intermediate progress because the counter itself matters. Objective counters are not unlocks or rewards.
- Objective completion emits/uses the accepted event-to-reward model; completion rewards must retain existing finalization/idempotency guarantees.
- Backend/MySQL remains authoritative for Codex ownership, objective progress/completion, eligibility, rewards, revision, and idempotency.
- Authored definitions belong in canonical content JSON. Do not move content catalogs into SQL or client constants.
- Client surfaces consume player-safe projections and authoritative mutation/read results; they do not infer completion from local gameplay state.
- Reuse existing reward/unlock, authored-content, API, storage, client-cache, and idempotency boundaries where semantically valid.
- Prototype implementation/tests are migration evidence only. Preserve useful behavior, not obsolete prototype architecture.
- Final visual polish remains deferred; new Phaser surfaces must remain responsive and usable.

### Package Queue

1. **Codex ownership and read-model foundation.** Approved through correction `88ed69f62826977f37d5c5eb365913d9805dcc5f`; evidence recorded at `eaea0d2a497d2b4a1cf7300232ff7a5d58f47ec7`. Establishes authored Codex definitions, unique ownership persistence/reward application, player-safe read projection, strict validation, and durable front-controller composition verification.
2. **Phaser Codex surface + Camp integration.** Current. Present owned/discovered knowledge through the accepted runtime/cache architecture and responsive Phaser UI.
3. **Knowledge-aware dialogue contract.** Planned. Establish authored replayable dialogue selection using meaningful Codex ownership prerequisites and Codex reward completion without generic story flags.
4. **Objective/bounty authored model + persistence/read contract.** Planned. Establish authored requirements and domain-owned mutable player progress/completion state.
5. **Authoritative gameplay-fact objective progress.** Planned. Feed objective progress from accepted authoritative gameplay outcomes/events with transactional/idempotent semantics rather than client claims.
6. **Automatic objective completion + reward finalization.** Planned. Complete objectives automatically when requirements are met and resolve rewards through the accepted event/reward pipeline exactly once.
7. **Phaser Objectives surface + authoritative reconciliation.** Planned. Present active/completed objectives and progress without client-side completion inference.
8. **Knowledge/objectives integrated verification and technical closure.** Planned. Verify persistence, reconnect/retry, secrecy, reward idempotency, regression safety, and responsive presentation.
9. **Focused manual UAT before Milestone 12.** Planned.

### Sequencing Notes

- Package 1 established the durable Codex knowledge primitive and player-safe lazy read/cache boundary used by later knowledge features.
- Package 2 adds the player-facing Codex presentation without changing Codex ownership or reward semantics.
- Dialogue is sequenced after Codex ownership/read presentation so it consumes an established knowledge primitive rather than defining one implicitly.
- Objective persistence is established before wiring gameplay facts so progress semantics and ownership are explicit.
- Objective reward completion follows authoritative progress wiring and must reuse the accepted reward pipeline rather than adding a parallel grant path.
- Do not begin Milestone 12 until Milestone 11 technical closure and focused manual UAT pass.
