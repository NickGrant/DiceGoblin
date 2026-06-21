# Save and Resume Scope

Status: active  
Last Updated: 2026-06-21  
Owner: Backend + Systems Design  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose

- Define the persistence guarantees for active runs in the current alpha build.
- Document the server-authoritative resume model actually used by the run system.

## Active Run Constraint

- A player may have at most one active run at a time.
- Starting a new run while another run is active is rejected.
- An active run remains resumable until it becomes:
  - `completed`
  - `failed`
  - `abandoned`

## Resume Entry

When the player returns with an active run:

- profile hydration exposes `active_run`
- the run service can request the current run payload
- the client returns the player to the run map

The run map is the primary resume entry surface.

## Persisted Run State

The current implementation persists:

- run record
- run map nodes
- run map edges
- run-scoped unit state
- battle records
- battle logs
- battle rewards

## Run-Scoped Unit Snapshot

Run state is stored separately from the persistent roster so that the game can preserve:

- current HP
- defeated flags
- cooldown state
- status effects

This snapshot is what carries attrition between encounters.

## Battle Persistence

- A battle is resolved server-side.
- The canonical log is stored after resolution.
- Rewards are stored separately from the log.
- Claim behavior is idempotent and tied to stored battle state.

## Idempotency Rules

- Re-resolving the same node should return the existing battle result unless a specific retry branch is allowed by controller logic.
- Re-claiming rewards must not duplicate grants.
- Resume never depends on client re-simulation.

## Rest Persistence

- Opening rest reads the current run-unit snapshot without mutating it.
- Finalizing rest mutates run-unit state, clears the node, unlocks downstream progression, and applies auto-level processing.

## Explicit Scope

The current save/resume contract covers:

- refresh
- browser close and reopen
- reconnecting to an active run
- replaying stored battle logs

## Out of Scope

- mid-combat reconnect into a still-running simulation
- client-authoritative resume
- multiple simultaneous active runs
- offline progression

## Validation Rules

The save/resume system is aligned when:

- an active run restores map and run-unit state after refresh
- battle results and reward claims stay idempotent
- rest and battle state persist through reconnects
