# July Roadmap UAT Balance Checklist
----

Status: active  
Last Updated: 2026-07-28  
Owner: Product + Design + QA  
Depends On: `documentation/05-playability-stability/01-critical-path-playtest-script.md`, `documentation/05-playability-stability/02-player-friction-severity-rubric.md`, `agent/ISSUES.md`

## Purpose
- Provide a shared worksheet for UAT and balance review after the July 25 roadmap implementation.
- Capture design intent, observed player experience, and follow-up tuning work in one repeatable format.
- Separate blockers, feel problems, and future balance targets before creating implementation issues.

## How To Use
- Use one copy of the Session Header per testing session.
- Fill in observations while playing; avoid solving balance in the moment unless the issue is a blocker.
- Record run seed, region, account state, and major unlock state whenever available.
- Convert high-confidence defects into issues immediately.
- Convert unclear feel problems into balance questions until at least two comparable runs have evidence.
- Pair region-pacing UAT notes with `npm.cmd run sim:balance:run:uat-regions:docker` when the issue is about attrition, reward feel, or comparative Farm/Mountains/Swamps pressure.
- Pair procedural map-shape UAT notes with `npm.cmd run run-patterns:gate:v2-uat:docker` when the issue is about Pattern-V2 route choice, occupied rows/columns, branch counts, or boss approach pacing in Mountains and Swamps.
- Pair shrine-feel notes with `documentation/05-playability-stability/09-shrine-tuning-sample-evidence.md` and `npm.cmd run sim:shrines:docker` when the issue is about shrine quality, effect mix, costs, or perceived reward value.

## Session Header

```yaml
session_id: JULY-UAT-YYYYMMDD-<initials>-<seq>
tester:
agent_partner:
build_ref:
environment:
  frontend:
  backend:
  database: fresh | reused | migrated
  debug_tools_enabled: yes | no
account_state:
  fresh_account: yes | no
  wrong_machine_unlocked: yes | no
  raw_chaos_unlocked: yes | no
  current_region:
  current_run_seed:
result: pass | fail | blocked | needs-more-runs
top_findings:
  - severity: high | medium | low | balance-question
    summary:
```

## Progression Intent

Answer these before or during the first full pass. Treat them as target hypotheses, not final truth.

- [ ] Farm should take roughly `___` runs before first clear.
- [ ] Mountains should take roughly `___` runs before first clear.
- [ ] Swamps should take roughly `___` runs before first clear.
- [ ] Wrong Machine should unlock after `___`.
- [ ] Raw Chaos should become visible/useful when `___`.
- [ ] Mystic Cave return should happen after `___`.
- [ ] First Pig Kin reconstruction should require roughly `___` runs after unlock.
- [ ] Expected first-session failure rate:
  - Farm: `___`
  - Mountains: `___`
  - Swamps: `___`
- [ ] Expected player shortage by phase:
  - Early Farm: `___`
  - Pre-Mountains clear: `___`
  - Pre-Swamps clear: `___`
  - Post-Wrong Machine: `___`
  - First reconstruction push: `___`

## Critical Path Pass

### Fresh Account Start
- [ ] Landing/login explains enough to begin.
- [ ] New account reaches Home without confusion.
- [ ] Current objective is clear and singular.
- [ ] Initial squad, dice, inventory, and region state are understandable.
- Notes:

### Farm
- [ ] First Farm run starts cleanly.
- [ ] Run map node choices are understandable.
- [ ] Combat result clarity is acceptable.
- [ ] Loot and reward screen explain what changed.
- [ ] Rest value is understandable.
- [ ] Player can clear Farm without unexplained progression gaps.
- Notes:

### Mountains
- [ ] Mountains unlock timing feels correct.
- [ ] Pattern-V2 gate and comparison output still passes before visual map-shape notes are treated as current.
- [ ] Attrition pressure is noticeable but not punishing.
- [ ] Hazards are visible and their outcomes are clear.
- [ ] Shrines are visible and their outcomes are clear.
- [ ] Boss approach pacing feels intentional.
- [ ] First clear rewards and stolen pages are visible.
- Notes:

### Swamps
- [ ] Swamps unlock timing feels correct.
- [ ] Pattern-V2 gate and comparison output still passes before visual map-shape notes are treated as current.
- [ ] Map width and route choice feel meaningfully different from Mountains.
- [ ] Wrong Machine unlock occurs at the intended moment.
- [ ] Raw Chaos is not earned before Wrong Machine unlock.
- [ ] Raw Chaos tracker appears once unlocked.
- [ ] Swamps first-clear rewards and stolen pages are visible.
- Notes:

### Wrong Machine And Return Loop
- [ ] Wrong Machine reward screen representation is clear.
- [ ] Post-unlock dialogue options appear where expected.
- [ ] Mystic Cave return objective is clear.
- [ ] Tooth Merchant unlock timing is correct.
- [ ] First Pig Kin reconstruction requirements are clear.
- [ ] Region items needed for reconstruction drop and appear in rewards.
- Notes:

## Run Feel Targets

Use this section once per sampled run.

```yaml
run_sample:
  region:
  seed:
  generator_version:
  node_count:
  branch_count:
  start_to_boss:
  boss_to_exit:
  result: clear | fail | abandon | in-progress
  squad_level_range:
  units_defeated:
  healing_items_used:
  energy_items_used:
  teeth_gained:
  dice_gained:
  units_gained:
  raw_chaos_gained:
  region_items_gained:
  subjective_length: too-short | right | too-long
  subjective_difficulty: too-easy | right | too-hard | swingy
  best_decision_point:
  worst_decision_point:
  notes:
```

### Map And Route Choice
- [ ] Required route length feels appropriate.
- [ ] Optional branches feel worth considering.
- [ ] Boss route is not accidentally bypassable.
- [ ] Rest, shrine, hazard, loot, chaos, and combat nodes are distributed legibly.
- [ ] Pattern-v1 map does not feel too small compared to lane-v1 expectations.
- Notes:

### Encounter Pressure
- [ ] Normal combat consumes appropriate HP/resources.
- [ ] Boss combat feels like a region climax.
- [ ] Enemy variety is noticeable.
- [ ] Dice rolls feel like full-value rolls, not muted.
- [ ] No combat appears to depend on fallback or missing abilities.
- Notes:

### Hazards, Shrines, And Chaos
- [ ] Hazard results are visible immediately.
- [ ] Shrine results are visible immediately.
- [ ] Active run effects are easy to review.
- [ ] Chaos reel outcomes match the reel, not only the current biome.
- [ ] Chaos rewards and costs feel appropriate after unlock.
- Notes:

### Consumables
- [ ] Healing consumables compete meaningfully with rest nodes.
- [ ] Healing consumables are neither mandatory nor irrelevant.
- [ ] Energy consumables are understandable.
- [ ] Energy consumables do not break pacing.
- Notes:

## Economy And Reward Targets

### Teeth
- [ ] Teeth gained per Farm clear feels: too-low | right | too-high
- [ ] Teeth gained per Mountains clear feels: too-low | right | too-high
- [ ] Teeth gained per Swamps clear feels: too-low | right | too-high
- [ ] Shop prices create useful choices.
- [ ] Academy prices create useful choices.
- [ ] Reconstruction costs create useful goals.
- Notes:

### Dice
- [ ] Dice acquisition rate feels: too-low | right | too-high
- [ ] Dice inventory stays readable.
- [ ] Salvage availability and value feel correct once unlocked.
- [ ] Rarity and quality information are understandable without clutter.
- Notes:

### Units
- [ ] Unit acquisition rate feels: too-low | right | too-high
- [ ] Squad size and available bench create useful decisions.
- [ ] Unit stat growth is noticeable.
- [ ] Promotion/recruitment pressure feels healthy.
- Notes:

### Raw Chaos
- [ ] Raw Chaos is gated until Wrong Machine unlock.
- [ ] Raw Chaos tracker is visible after unlock.
- [ ] Raw Chaos earnings feel: too-low | right | too-high
- [ ] Raw Chaos spend goals are understandable.
- Notes:

### Region Items And Stolen Pages
- [ ] Pig Ear drops when expected.
- [ ] Mud King Crown Fragment drops when expected.
- [ ] Region items appear in rewards when earned.
- [ ] Stolen pages appear in rewards after first clear.
- [ ] Codex additions are understandable after run completion.
- Notes:

## Story And Unlock Clarity

- [ ] First-clear dialogue does not repeat incorrectly.
- [ ] Repeat-run dialogue does not hide needed information.
- [ ] Rewards screens represent newly unlocked systems.
- [ ] Home objective updates to the current object only.
- [ ] Codex contains older objectives and stolen-page information.
- [ ] Kobold dialogue images load.
- [ ] The Whim has appropriate post-Wrong Machine dialogue.
- [ ] Mountain kobolds have appropriate post-Wrong Machine dialogue.
- Notes:

## Issue Capture Template

Use this for anything that should become an implementation issue.

```yaml
issue_candidate:
  title:
  severity: high | medium | low
  type: blocker | bug | balance | clarity | polish
  region:
  seed:
  unlock_state:
  reproduction_steps:
    - 
  expected:
  actual:
  player_impact:
  proposed_direction:
  evidence:
```

## Balance Question Template

Use this when the behavior might be correct but needs more samples.

```yaml
balance_question:
  question:
  affected_phase:
  current_observation:
  desired_feel:
  metrics_needed:
    - 
  sample_count_needed:
  possible_tuning_levers:
    - 
```

## Tuning Lever Map

- Run length:
  - pattern profile node budgets
  - branch count
  - boss path depth
  - region generator version rollout
- Difficulty:
  - encounter template selection
  - enemy ability sets
  - enemy stats
  - boss stats
  - healing availability
- Attrition:
  - hazard frequency
  - rest frequency
  - shrine effect mix
  - healing consumable drop rate
- Economy:
  - teeth rewards
  - shop prices
  - academy costs
  - reconstruction costs
  - region item drop rates
- Progression:
  - unlock prerequisites
  - objective ordering
  - dialogue placement
  - codex/stolen-page surfacing

## Closeout

- [ ] All high-severity blockers have issues.
- [ ] All medium-severity problems have issues or accepted deferral notes.
- [ ] Balance questions have enough samples or are scheduled for another pass.
- [ ] Pattern-v1 Mountains rollout is accepted or blocked with reason.
- [ ] Pattern-v1 Swamps rollout is accepted or blocked with reason.
- [ ] Final UAT summary links issue IDs and evidence locations.
