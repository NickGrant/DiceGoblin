# MILESTONES BACKLOG
----

## Purpose
- `agent/MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `agent/MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `agent/MILESTONES.md` when they are opened for execution.

## Backlog Milestones

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

## Node Quality Art Expansion

**Status:** Planned

Loot and shrine node presentation should use the generated quality-tier assets, including controlled variant selection, while preserving backend-authoritative rewards.

Success criteria:

- Loot and shrine nodes select an appropriate quality tier.
- A/B variants are chosen deterministically or by a documented randomization rule.
- Reward and node visuals remain aligned with the underlying reward table.

### Related Issues

- Implement loot and shrine quality tiers
