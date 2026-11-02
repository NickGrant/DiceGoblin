# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 4 - Mountains trail and Archivist introduction

**Status:** In Progress
**Priority:** High

#### Problem
Packages 2-3 now align the opening through the Mountains unlock. The Tooth Collector is rescued from the Mudking, Shop access is durable, and the server-gated post-Farm Whim interaction introduces the missing Wrong Machine, points the player toward the kobolds, and grants `unlock.region.mountains`.

The Mountains themselves still lack the canonical narrative continuation. The player must follow the Wrong Machine trail there and have the first direct conversation with The Archivist during the Mountains arc. This is a narrative/content correction inside the existing Mountains run architecture, not a new progression subsystem. Package 5 separately owns the Mountains boss conclusion that establishes the Machine's movement to Swamps and grants Swamp access.

#### Canonical target
- A player enters Mountains already knowing from The Whim that the Wrong Machine is missing and kobolds are the lead.
- Mountains gameplay advances that investigation through authored, player-facing narrative content integrated into the existing run graph/dialogue/Codex architecture.
- The player's **first direct conversation with The Archivist occurs in Mountains**.
- The Archivist is introduced as the Order-aligned antagonist through conversation, not combat.
- Mountains combat remains kobold-only for this opening arc. The player does **not** fight Archivist/Order troops here; their first combat appearance is reserved for Swamp/M13.
- Package 4 does not yet unlock Swamps or grant Wrong Machine access. Package 5 owns the boss-resolution/Swamp handoff.

#### Accepted baseline
- Package 3 is accepted through implementation `01da731f7faef5ccd202a475a15f3289f0af44eb` and evidence `362a77d45022f72b8fc625062149f5cb3c39ade9`.
- `codex.camp.wrong_machine_search` records that The Whim has told the player the Wrong Machine is missing and kobolds in Mountains are the lead.
- `unlock.region.mountains` is granted only by completing the post-Farm Whim beat.
- Mountains already use the generic run graph/encounter/combat architecture and kobold combat roster. Preserve that architecture.
- Wrong Machine capability access remains absent.
- `documentation/01-lore/01-story-and-biome-progression.md` owns the narrative order.

#### Required implementation
1. **Mountains investigation content.** Author the minimum Mountains dialogue/Codex/encounter content needed to make the Wrong Machine investigation legible during a run. Build on the existing search knowledge rather than repeating The Whim's exposition verbatim.
2. **First Archivist conversation.** Add a direct player-facing conversation with The Archivist in Mountains through the existing dialogue/encounter path. This is the canonical first direct meeting with The Archivist. The content should establish the antagonist and advance the Wrong Machine trail without prematurely resolving the Swamp destination if that revelation belongs to Package 5's boss conclusion.
3. **Run-graph integration.** Place the narrative beats into the existing Mountains run graph using generic node/encounter architecture. Preserve valid branching/reachability/terminal semantics and existing combat/reward behavior except where the narrative insertion necessarily changes graph topology.
4. **Knowledge durability.** Use Codex only for meaningful retained knowledge the player has actually learned. Dialogue/reload behavior must remain server-authoritative and recoverable through current run state; do not add generic seen/history flags.
5. **Archivist presentation identity.** Reuse the canonical Archivist identity/lore and accepted asset fallback conventions. Do not create a second antagonist identity or reinterpret the Archivist as a Mountains combatant.
6. **Kobold-only combat boundary.** Do not add Archivist/Order troops, Frogmen, or other Swamp/M13 enemies to Mountains. Existing Mountains combat encounters remain kobold-aligned. The Archivist's presence here is conversational/narrative only.
7. **No premature Swamp handoff.** Do not grant `unlock.region.swamps`, expose a startable Swamp run, grant `capability.wrong_machine_access`, or imply the player has recovered the Wrong Machine. Package 5 owns the Mountains boss conclusion and visible Swamp handoff.
8. **Revision/idempotency/reload.** Any dialogue completion rewards/knowledge must retain exact-key replay, player-revision, unique reward, and reconnect semantics. Existing run-state restoration must preserve available narrative nodes without client-local story state.
9. **Safe projection.** Browser-facing content/state may expose the narrative the player has reached, but must not leak the Package 5 boss reward/Swamp unlock, future Swamp topology, Wrong Machine recovery mechanics, or M13 content.
10. **Package boundary.** Do not implement Swamp unlock/presentation, Archivist troop combat, Wrong Machine recovery/access/reconstruction, Frog Kin, first reconstruction follow-up, broader-map opening, or M13 parity work.

#### Architectural constraints
- Follow `agent/CONTEXT_ROUTER.md`; use canonical story, progression, authored-content, reward/unlock, API, storage, Phaser/runtime, and testing authorities.
- Backend/MySQL remains authoritative; Phaser renders/routs from authoritative run/dialogue state.
- Authored narrative belongs in canonical JSON.
- Reuse the accepted dialogue/Codex and run-node systems. Do not add a Mountains-specific story service/controller/repository or generic story-history layer.
- Keep content validation structural/semantic; avoid broad literal-copy tests.
- Preserve the graph rules: all nodes reachable, one reachable terminal Exit, correct available/completed/locked frontier behavior, and deterministic encounter resolution.
- Preserve existing Mountains repeatability. One-time knowledge rewards may no-op on repeat ownership, but repeat runs must remain valid.

#### Minimum proof
1. A player with Mountains access can start a Mountains run through existing region authority.
2. The run contains a coherent Wrong Machine investigation path using existing generic node/encounter architecture.
3. The player can directly converse with The Archivist in Mountains, and the dialogue is inaccessible outside the appropriate run state/path.
4. Reload/reconnect while a Mountains narrative node is available restores it from authoritative current-run state.
5. Any retained knowledge is granted once and exact-key replay does not duplicate it or revision changes.
6. Mountains combatants remain kobold-only; no Archivist/Order troop combat is introduced.
7. No Swamp region unlock/startability is granted by Package 4.
8. `capability.wrong_machine_access` remains absent.
9. Existing Mountains graph/combat/boss/repeat behavior remains valid pending Package 5's explicit boss-handoff change.
10. Farm/Whim/Mountains-unlock regressions continue to pass.
11. No Frog Kin, Swamp/Frogmen, broader-map opening, or M13 parity behavior is introduced.

#### Verification
Run focused backend tests for Mountains graph generation/resolution, dialogue eligibility/completion in run context, Codex/reward idempotency, reconnect/current-run restoration, combat roster boundaries, region authorization, and revision behavior. Run focused frontend tests for Mountains run dialogue presentation/routing if client behavior changes. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure` because this package changes production authored content/run behavior.

#### Scope exclusions
- changes to accepted Mystic Cave/Farm/Tooth Collector/Post-Farm Whim progression except regression fixes;
- Swamp region unlock or visible handoff;
- changes to the Mountains boss terminal reward that reveal/unlock Swamps (Package 5);
- Archivist/Order combat troops;
- Swamp/Frogmen content;
- Wrong Machine capability access or reconstruction;
- Frog Kin;
- first Wrong Machine-use follow-up;
- broader-map opening;
- broad visual redesign;
- M13 parity work or M14 hardening.

#### Completion evidence required
Record implementation SHA(s); authored Mountains dialogue/Codex/encounter/run-graph paths changed; exact narrative placement and first Archivist conversation; proof the dialogue is run-state gated/reload safe; any knowledge reward identities; proof Mountains combat remains kobold-only; proof Swamp/Wrong Machine access remain absent; exact-key/revision/repeat results; focused backend/frontend counts; content revision if changed; and full package-gate results.

Leave Package 4 **In Progress** for architectural review. Do not activate Package 5 automatically from the coding agent.
