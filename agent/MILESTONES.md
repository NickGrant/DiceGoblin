# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Chaos Encounters as Combat

**Status:** Active

Chaos nodes should mature from reward-only reel payouts into battle-backed encounters. Once the player accepts the settled reels, the backend should create or return an authoritative battle result using the reel outcome, and the frontend should show the normal combat playback before reward claim.

Success criteria:

- Chaos reel finalization creates or returns a persisted battle contract.
- Finalized chaos rewards are claimed through the same battle reward path as combat nodes.
- The frontend transitions from settled reels into combat playback instead of ending at a direct payout panel.
- Docs and tests describe the new expectation clearly.

### Related Issues

- Finalize chaos reels into battle-backed encounters
- Transition chaos frontend into combat playback
- Expand chaos reel combat authoring
