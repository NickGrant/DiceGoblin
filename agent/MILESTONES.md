# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Balance Simulation and Telemetry

**Status:** Active

Add repository-local tools for measuring combat, run, reward, and progression balance before and after gameplay tuning changes.

Success criteria:

- A Docker-friendly simulation command can run large deterministic batches without the frontend.
- Simulation fixtures cover fresh account, starter squad, region-appropriate squad, and at least one overleveled comparison profile.
- Reports include p50, p75, p90, and worst-observed values for required progression goals.
- Documentation explains how to interpret simulation output and attach it to balance PRs.

### Related Issues

- BST-002: Add progression-goal simulation reports
- BST-003: Add balance report workflow to PR validation
