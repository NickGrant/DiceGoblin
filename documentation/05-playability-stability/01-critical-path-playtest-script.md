# First Pig Kin Demo Critical Path Playtest Script
----

Status: active
Last Updated: 2026-07-30
Owner: QA + Product
Depends On: `documentation/07-roadmap/2026-07-30-first-pig-kin-demo-roadmap.md`, `documentation/05-playability-stability/00-release-gate-criteria.md`

## Purpose

Provide a repeatable manual test flow for the formal demo release. The script ends when the player creates their first Pig Kin.

## Execution Notes

- Run against the same build/version used for automated gate validation.
- Prefer a fresh account.
- Capture screenshots or notes for confusing story, objective, reward, or unlock moments.
- If progression becomes impossible, record the blocker and stop.

## Script

1. Session bootstrap:
   - Create or reset to a fresh account.
   - Confirm landing/login flow reaches the authenticated game shell.
   - Confirm Home shows one current objective, not the full objective backlog.
2. Mystic Cave opening:
   - Complete the introductory Mystic Cave flow.
   - Confirm dialogue loads and The Farm unlocks.
   - Confirm any codex/stolen-page messaging is understandable.
3. Farm first path:
   - Start and complete the required Farm progression.
   - Confirm combat, loot, rest, reward, and run-summary surfaces are understandable.
   - Confirm Pig Ear and Mudking Crown Fragment protection where applicable.
   - Confirm Mountains unlocks.
4. Mountains path:
   - Start and complete a Mountains run.
   - Confirm generated map shape, node unlocking, hazards, shrines, chaos nodes, and rewards are understandable.
   - Confirm post-run dialogue/unlock messaging is not confusing.
   - Confirm Swamps unlocks.
5. Swamps path:
   - Start and complete a Swamps run.
   - Confirm Wrong Machine recovery occurs at the intended point.
   - Confirm Raw Chaos gating and chaos node rewards behave as intended.
   - Confirm repeat or first-clear dialogue does not block progression.
6. Post-recovery return:
   - Follow the current objective after Wrong Machine recovery.
   - Confirm The Whim or other required dialogue directs the player toward first Pig Kin creation.
   - Confirm Farm repeat behavior after Wrong Machine unlock if it is part of the current objective chain.
7. Consumables and recovery:
   - Inspect consumable inventory when unlocked.
   - Use a healing consumable if available and confirm it does not make rest nodes feel obsolete.
   - Use or inspect energy consumables if available and confirm pacing remains bounded.
8. Wrong Machine reconstruction:
   - Open the Wrong Machine.
   - Confirm Pig Kin eligibility, costs, missing requirements, Raw Chaos, materials, and catalyst are clear.
   - Create the first Pig Kin.
   - Confirm the lineage unlock, granted Pig Kin unit, inventory spend, and profile/warband refresh.
9. Resume and idempotency:
   - Refresh during at least one active run and after Pig Kin creation.
   - Confirm active run state, unlock state, inventory, and Pig Kin ownership persist.
   - Retry any completed claim/reconstruction path where safe and confirm it is idempotent.
10. Demo stop:
   - Stop the demo evaluation after first Pig Kin creation unless testing a specific logged issue.

## Evidence Capture Template

```yaml
playtest_id: PIG-KIN-DEMO-YYYYMMDD-<initials>-<seq>
build_ref: <commit/tag/local>
environment:
  browser: <name/version>
  backend: <local/staging/prod>
  db_state: fresh | reset | migrated
  migrations_current: yes | no | unknown
result: pass | fail | blocked
steps:
  session_bootstrap: pass | fail | blocked
  mystic_cave_opening: pass | fail | blocked
  farm_first_path: pass | fail | blocked
  mountains_path: pass | fail | blocked
  swamps_path: pass | fail | blocked
  post_recovery_return: pass | fail | blocked
  consumables_and_recovery: pass | fail | blocked
  wrong_machine_reconstruction: pass | fail | blocked
  resume_and_idempotency: pass | fail | blocked
  demo_stop: pass | fail | blocked
notes: |
  <observations>
defects:
  - id: <issue title or TBD>
    severity: low | medium | high
    summary: <brief defect summary>
```