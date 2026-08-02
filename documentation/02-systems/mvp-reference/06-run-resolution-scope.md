---
Title: "Run Resolution Scope"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - documentation/02-systems/mvp-reference/03-encounter-scope.md
  - documentation/02-systems/mvp-reference/05-save-and-resume-scope.md
Category: 02-systems
Tags:
  - systems
  - mvp-reference
---

# Run Resolution Scope

## Purpose

- Define how runs currently fail, complete, abandon, and clean up.
- Document the live cleanup rules enforced by the backend and reflected in the guide.

## Attrition During a Run

- Run-unit HP persists between encounters.
- Defeated flags persist until recovery or run cleanup.
- Cooldowns and status effects are part of run-scoped unit state.
- Rest is the main in-run recovery point.

## Rest Finalization

When a rest node is finalized, the backend currently:

- heals run units
- clears defeated flags
- resets cooldown data
- clears status effects
- clears the rest node
- unlocks downstream nodes
- applies auto-level processing for run units

## Combat Defeat

Current alpha rule:

- a combat or boss defeat immediately ends the run
- the run is marked `failed`
- the failed node returns status `failed`

The public guide and run controller are aligned on this terminal-failure behavior.

## Abandon Run

- The player may abandon an active run manually.
- Abandoning the run marks it `abandoned`.
- Energy spent to start the run is not refunded.
- Abandon uses the same cleanup model as other run endings, with defeated-unit XP reset still applied where appropriate.

## Successful Completion

- Success is completed through the dedicated run exit endpoint.
- Exit requires the exit node to be available.
- A successful exit marks the run `completed`.
- The exit node is marked cleared during completion.

## Cleanup Rules

Run-end cleanup currently restores run-unit usability by:

- restoring missing HP
- clearing defeated flags
- resetting cooldown state
- clearing status effects

## XP Preservation and Reset

Current backend behavior distinguishes success from defeated cleanup:

- completed runs preserve unit XP
- defeated units can have XP reset to `0` during failed or abandoned cleanup
- run summaries still preserve the pre-cleanup progression snapshot for player-facing reporting

This means the summary can show what was earned during the run even when defeated units lose their stored XP afterward.

## Summary Expectations

Run summaries currently separate:

- rewards
- progression
- survivors
- defeated
- reward detail
- progression detail

Progression detail may include defeated units with zero gained XP so that the summary reflects who participated and who was lost.

## Retry Rules

- There is no normal in-run retry loop after a terminal combat defeat.
- Reward claim remains idempotent.
- Controller-level edge handling may support battle-row cleanup for specific previously claimed defeat records, but that should not be documented as a normal player-facing retry system.

## Validation Rules

The run-resolution documentation is aligned when:

- combat and boss defeat are documented as terminal
- rest is documented as a full recovery and auto-level checkpoint
- successful exit preserves XP
- failed or abandoned cleanup can reset defeated-unit XP while still showing pre-cleanup summary details
