# July Roadmap UAT Evidence
----

Status: active  
Last Updated: 2026-07-27  
Owner: QA + Engineering  
Depends On: `documentation/07-roadmap/2026-07-25-completion-analysis.md`, `agent/ISSUES.md`, `agent/MILESTONES.md`

## Purpose
- Capture UAT evidence for the July 25 roadmap release path.
- Keep progression, unlock timing, reward visibility, and balance-feel notes in one repeatable format.
- Turn player-facing failures into active issues with severity and reproduction notes.

## Fresh Account Path
Use a fresh account unless the note explicitly says the account is reused after reset.

```yaml
playtest_id: JULY-UAT-YYYYMMDD-<initials>-<seq>
build_ref: <commit/tag/local>
environment:
  browser: <name/version>
  backend: <docker/local/staging>
  db_state: fresh_account | reset_account | reused_account
result: pass | fail | blocked
critical_path:
  farm_first_clear: pass | fail | blocked
  mountains_first_clear: pass | fail | blocked
  swamps_first_clear: pass | fail | blocked
  mudking_first_defeat: pass | fail | blocked
  wrong_machine_recovery: pass | fail | blocked
  mystic_cave_return: pass | fail | blocked
  pig_kin_reconstruction: pass | fail | blocked
unlock_timing:
  tooth_merchant: pass | fail | blocked
  academy: pass | fail | blocked
  wrong_machine: pass | fail | blocked
  raw_chaos_tracker: pass | fail | blocked
reward_visibility:
  teeth: pass | fail | blocked
  dice: pass | fail | blocked
  units: pass | fail | blocked
  items: pass | fail | blocked
  stolen_pages: pass | fail | blocked
  raw_chaos: pass | fail | blocked | not_applicable
story_comprehension: |
  <what the player understood, what felt missing, and any confusing repeated exposition>
defects:
  - severity: low | medium | high | blocker
    route_or_screen: <screen/route>
    reproduction: <steps>
    expected: <expected behavior>
    actual: <observed behavior>
    issue_status: logged | needs_issue | follow_up
notes: |
  <freeform observations>
```

## Repeat Run Checks
- Repeat Farm, Mountains, and Swamps after first clear.
- Confirm first-clear story does not replay as full exposition.
- Confirm stolen pages are visible when new codex information is awarded.
- Confirm unlock reward messaging does not appear early or late.
- Confirm failed or abandoned runs do not grant first-clear-only rewards.

## Encounter And Consumable Sampling
Run several seeds or account attempts and record a compact line for each sample.

```yaml
sample_id: JULY-ENCOUNTER-YYYYMMDD-<seq>
region: farm | mountains | swamps
run_seed: <if visible>
nodes_seen:
  combat: <count>
  loot: <count>
  rest: <count>
  boss: <count>
  hazard: <count>
  shrine: <count>
  chaos: <count>
consumables:
  healing: useful | weak | too_strong | not_seen
  energy: useful | weak | too_strong | not_seen
encounter_copy: clear | confusing | repetitive
reward_feel: low | fair | high | swingy
notes: |
  <balance/readability observations>
```

## Exit Criteria
- Fresh-account critical path reaches first Pig Kin reconstruction without a blocker.
- Repeat-run story, stolen pages, and unlock messaging behave as one-time or repeatable exactly where expected.
- Encounter variety and consumable pacing have at least one recorded sample each for Farm, Mountains, and Swamps.
- All high-severity or blocker findings are represented in `agent/ISSUES.md`.
