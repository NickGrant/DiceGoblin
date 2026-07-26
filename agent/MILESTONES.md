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

## Progression Rewards and Unlock Clarity

**Status:** Planned

Progression unlocks, first-clear codex additions, and reward summaries should clearly show what the player earned and when newly available systems become usable. Unlock timing must be backend-authoritative so rewards, currencies, shops, and codex discoveries cannot appear before their intended story gate.

Success criteria:

- Wrong Machine and Tooth Merchant unlock at the intended first-clear moments.
- Reward screens show newly unlocked systems, stolen codex pages, teeth, and special item drops.
- Raw Chaos cannot be earned, tracked, or salvaged before Wrong Machine recovery.
- Regression tests cover unlock timing and reward presentation.

### Related Issues

- Correct story-gated feature unlock timing
- Surface unlocks, stolen pages, and complete reward totals
- Fix lineage item drops and reward presentation

## Core UX Cleanup

**Status:** Planned

Several high-traffic screens need focused visual cleanup so they are easier to scan, less repetitive, and closer to the strongest existing shop-style presentation.

Success criteria:

- Run screens use tighter spacing and a refreshed icon set.
- Guide and landing/login pages are rebuilt around their actual player jobs.
- Academy and shrine screens reduce duplicate messaging and use clearer unlock/tier language.
- Dropdown and dialogue image assets load without visible flicker or missing images.

### Related Issues

- Refresh high-friction layouts and run presentation
- Repair flickering and missing visual assets
- Rework Academy and Shrine copy density

## Inventory Scale and Actions

**Status:** Planned

Inventory screens should remain usable as collections grow, and unlocked item actions should appear consistently in the relevant inspect flows.

Success criteria:

- Dice, unit, and related collection screens paginate or otherwise chunk large inventories.
- Dice inspect modals expose salvage only after Wrong Machine recovery.
- Redundant dice badges and duplicate prototype chips stay out of inventory views.

### Related Issues

- Add pagination to inventory collections
- Complete unlocked dice action affordances

## Node Quality Art Expansion

**Status:** Planned

Loot and shrine node presentation should use the generated quality-tier assets, including controlled variant selection, while preserving backend-authoritative rewards.

Success criteria:

- Loot and shrine nodes select an appropriate quality tier.
- A/B variants are chosen deterministically or by a documented randomization rule.
- Reward and node visuals remain aligned with the underlying reward table.

### Related Issues

- Implement loot and shrine quality tiers
