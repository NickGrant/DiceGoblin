# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Critical Path UAT

**Status:** Active

Validate the completed July 25 roadmap through the player-facing critical path and turn observed failures into severity-ranked issues.

Success criteria:

- Fresh-account UAT covers Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- Repeat-run behavior is checked for story, stolen pages, and unlock messaging.
- Reward summaries, unlocks, Raw Chaos gating, special item rewards, and Wrong Machine actions are verified in-game.
- Encounter variety and consumable pacing are sampled across multiple regions and seeds.
- Any UAT failures are logged with reproduction notes and player-facing severity.

### Related Issues

- Run fresh-account July roadmap UAT
- Verify reward and unlock clarity in UAT
- Validate encounter and consumable feel

## UAT Polish Backlog

**Status:** Planned

Convert UAT observations into focused release-hardening issues and confirm merge/artifact hygiene before broader release validation.

Success criteria:

- Frontend polish and copy findings are grouped by severity.
- High-severity UAT findings are promoted into implementation-ready issues.
- Low-severity improvements are deferred without blocking release hardening.
- `main`, roadmap analysis, active tracker, and generated-artifact policy are reconciled.

### Related Issues

- Triage frontend polish findings from UAT
- Confirm release merge and generated-artifact hygiene
