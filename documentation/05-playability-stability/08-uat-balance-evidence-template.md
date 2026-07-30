# UAT Balance Evidence Template
----

Status: active  
Last Updated: 2026-07-29  
Owner: Product + QA + Systems Design  
Depends On: `documentation/05-playability-stability/06-july-roadmap-uat-balance-checklist.md`, `documentation/02-systems-mvp/14-balancing-strategy-and-simulation.md`

## Purpose
- Capture UAT balance observations in a consistent shape before converting them into tuning issues.
- Pair subjective run feel with simulator output, account state, and exact reproduction context.
- Separate confirmed defects from balance questions that need more samples.

## Evidence Packet

Copy one packet per region, finding, or tested hypothesis.

```yaml
balance_evidence:
  id: UAT-BAL-YYYYMMDD-<initials>-<seq>
  build:
    branch:
    commit:
    migrations_current: yes | no
    environment: docker | local | production-like
  account_state:
    fresh_account: yes | no
    current_region:
    wrong_machine_unlocked: yes | no
    raw_chaos_unlocked: yes | no
    tooth_merchant_unlocked: yes | no
    squad_summary:
    notable_inventory:
  observed_run:
    region:
    run_seed:
    generator_version:
    result: clear | return_home | defeat | blocked
    nodes_seen:
    nodes_cleared:
    units_defeated:
    rewards_noted:
    player_feel: too-easy | fair | too-hard | confusing | repetitive
  simulator_context:
    command:
    sample_count:
    completion_rate:
    node_win_rate:
    average_hp_remaining_pct:
    unit_defeats_per_sample:
    item_totals:
  assessment:
    type: defect | tuning-needed | balance-question | no-action
    severity: high | medium | low | informational
    confidence: high | medium | low
    summary:
    proposed_direction:
    needs_more_samples: yes | no
```

## Command Pairing

Use the smallest command that matches the observation:

| Observation | Pair With |
| --- | --- |
| Overall Farm, Mountains, and Swamps pacing | `npm.cmd run sim:balance:run:uat-regions:docker` |
| One region feels numerically off | `php bin/simulate.php --mode=run --region=<region> --runs=25` through Docker |
| One combat node feels off | `php bin/simulate.php --mode=battle --region=<region> --node=combat --runs=25` through Docker |
| Pattern-V2 map shape feels off | `npm.cmd run run-patterns:gate:mountains:v2:docker` or `npm.cmd run run-patterns:gate:swamps:v2:docker` |
| Pattern-V2 feels different from Pattern-V1 | `npm.cmd run run-patterns:compare:mountains:v2:docker` or `npm.cmd run run-patterns:compare:swamps:v2:docker` |

## Conversion Rules

- Create a defect issue when the observed behavior violates authored rules, unlock timing, reward visibility, or graph validity.
- Create a tuning issue when at least two evidence packets point in the same direction and the simulator output supports the feel problem.
- Keep a balance question open when the feeling is plausible but the sample is too small or account state is unusual.
- Record no-action evidence when the system feels correct; good negative evidence is useful during release signoff.
