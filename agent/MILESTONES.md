# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 11 - Knowledge and Objectives

**Status:** Active

### Related Issues
- Milestone 11 Package 6 - Automatic objective completion + reward finalization

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
2. **Phaser Codex surface + Camp integration.** Approved at `37be6990b480989b83c22645cb5fa71de7291a94` (implementation `890ae102b2deea6929859acd934848ba3a9402e5`). Establishes mounted Camp -> Codex navigation, authoritative lazy-cache presentation, owned/unowned secrecy, recoverable reads, responsive list/detail presentation, and clean return lifecycle without changing backend Codex semantics.
3. **Knowledge-aware dialogue contract.** Approved through `47c6ace1b75832c528f4151e4988164c31f35fec` (implementation `0e928f31281b5cbb36d317c78701da99dd19188e`). Establishes replayable authored dialogue, authoritative Codex-aware eligibility, finalized dialogue-to-Codex rewards, idempotent completion, strict player-safe browser contracts, and no dialogue-history/story-flag state. The accompanying test-audit cleanup reduced authored-content coupling without changing production behavior.
4. **Objective/bounty authored model + persistence/read contract.** Approved through `350ec0eead1224d9cd0777cc19d574913ad8a006` (implementation `5f3212fee87af17f3361c162b1ec88fca06a47b6`). Establishes validated authored objectives, narrow user-scoped objective/requirement persistence, authoritative unstarted/active/completed read semantics, strict player-safe lazy client contracts, and fail-closed corruption handling without gameplay-fact wiring or rewards.
5. **Authoritative gameplay-fact objective progress.** Approved through `2903f69555994e3377b3f254f8176d44990024c8` (implementation `151133f6d1e271f31893554a20fdc53d29ee83af`). Connects finalized combat defeats, successful region-run completion, and legitimate dialogue completion to objective-owned counters transactionally; counters saturate, exact-key replay does not reapply progress, revision/cache semantics remain authoritative, and no client progress endpoint or completion rewards are introduced.
6. **Automatic objective completion + reward finalization.** Current. Complete objectives automatically when requirements are met and resolve rewards through the accepted event/reward pipeline exactly once.
7. **Phaser Objectives surface + authoritative reconciliation.** Planned. Present active/completed objectives and progress without client-side completion inference.
8. **Knowledge/objectives integrated verification and technical closure.** Planned. Verify persistence, reconnect/retry, secrecy, reward idempotency, regression safety, and responsive presentation.
9. **Focused manual UAT before Milestone 12.** Planned.

### Sequencing Notes

- Package 1 established the durable Codex knowledge primitive and player-safe lazy read/cache boundary used by later knowledge features.
- Package 2 established player-facing Codex presentation without changing Codex ownership or reward semantics.
- Package 3 established dialogue as replayable authored interaction that may consume Codex knowledge prerequisites and grant meaningful Codex knowledge, without generic dialogue history/story flags.
- Package 4 established objective definitions and domain-owned progress/completion persistence/read semantics without wiring gameplay facts.
- Package 5 connects authoritative finalized gameplay facts to objective-owned counters transactionally without client-authored progress or reward finalization.
- Package 6 now owns the automatic saturated-objective transition and reward finalization through the accepted event/reward pipeline. It must preserve the owning gameplay transaction and existing retry/idempotency semantics rather than adding a manual claim path.
- Do not begin Milestone 12 until Milestone 11 technical closure and focused manual UAT pass.
