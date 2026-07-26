# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Chaos Encounters as Combat

### Finalize chaos reels into battle-backed encounters

**Milestone:** Chaos Encounters as Combat
**Status:** In Progress
**Priority:** High

#### Problem
Chaos nodes currently finalize into direct reward payout and node clearing. Player expectation is that accepted reels should produce the appropriate combat and then use the normal battle playback/reward claim flow.

#### Acceptance Criteria

- Finalizing a generated chaos result creates or returns one persisted battle for that run node.
- The battle log meta records the chaos reel summary and selected symbols.
- Existing finalized chaos results remain idempotent and do not duplicate rewards or battles.
- Backend integration coverage proves chaos finalization returns a battle payload with playback events.

### Transition chaos frontend into combat playback

**Milestone:** Chaos Encounters as Combat
**Status:** Open
**Priority:** High

#### Problem
The run-node UI treats confirmed chaos results as complete and returns to the map. It should instead show the battle result after finalization so the player can watch combat play out and claim rewards.

#### Acceptance Criteria

- The finalize button copy reflects starting/locking the encounter, not direct payout.
- A successful chaos finalize response sets the node result battle payload.
- Confirmed chaos nodes with an existing battle reopen into the battle playback surface.
- Frontend tests cover the chaos-to-playback transition.

### Expand chaos reel combat authoring

**Milestone:** Chaos Encounters as Combat
**Status:** Open
**Priority:** Medium

#### Problem
The first battle-backed version can map reels onto existing encounter templates, but the long-term design needs authored enemy-family, encounter-shape, and rule/reward catalogs that can produce more exact combat setups.

#### Acceptance Criteria

- Document the authoring contract for chaos enemy family, encounter shape, and rule/reward effects.
- Add backlog-ready work for richer combat modifiers such as bolstered enemies, ambush opening state, guaranteed loot, and Raw Chaos reward hooks.
- Keep the current implementation deterministic and backend-authoritative while leaving room for catalog growth.
