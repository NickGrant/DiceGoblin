# Domain Events Evaluation

----

Status: planning  
Last Updated: 2026-07-24  
Owner: Backend  
Depends On: `agent/MILESTONES.md`, `agent/ISSUES.md`, `documentation/01-architecture/03-backend-api-contracts.md`

## Purpose

Evaluate whether Dice Goblins should add domain events after the backend structural cleanup pass.

## Decision

Do not add a broad event bus yet.

The backend should continue to prefer direct service calls for core gameplay mutations until the run lifecycle, grant paths, shop, academy, and controller helper boundaries have fully settled. Current gameplay mutations are still easiest to understand when their transaction flow is visible in the owning service.

## Allowed Near-Term Pattern

Use narrow synchronous events only when all of these are true:

- the event is emitted inside the same backend request and transaction boundary
- the caller still owns the primary mutation and response contract
- the listener is deterministic and backend-authoritative
- the listener failure should fail the whole mutation
- the event name maps to a real gameplay fact, not a transport action
- tests can prove idempotency or single-application behavior

Good candidate names:

- `battle.claimed`
- `run.completed`
- `run.failed`
- `unit.promoted`
- `dice.salvaged`
- `bounty.progress_recorded`

## Deferred Pattern

Do not add these yet:

- async queues
- background workers
- retryable outbox processing
- generic publish/subscribe infrastructure
- frontend-authored event progress
- analytics-style events mixed into gameplay mutation code

Those patterns are useful later, but they add operational and idempotency complexity before the game needs them.

## Best First Use

The first practical event-like extraction should probably be bounty or objective progress.

Reason:

- objectives and bounties need to react to normal gameplay facts
- they should not force every controller to know every possible progress rule
- progress must be backend-owned and idempotent
- failure to record progress should be visible during development, not silently ignored

Recommended shape:

1. Keep the core service mutation direct.
2. Build a small service such as `ProgressSignalService`.
3. Call explicit methods like `recordBattleClaimed(...)` or `recordRunCompleted(...)`.
4. Internally route to objective or bounty progress rules.
5. Only introduce a generic dispatcher if two or more consumers need the same emitted fact.

## Current Non-Goals

- Event sourcing
- Replaying historical game state
- Cross-process async delivery
- Hiding core gameplay flow behind subscribers
- Creating a generic event bus before objective or bounty systems exist

## Acceptance Guidance

A future synchronous domain event implementation is acceptable when it makes one of these clearer:

- a run-end follow-up that is currently duplicated
- a grant-related side effect that applies to several reward sources
- objective or bounty progress that reacts to multiple existing gameplay facts

It is not acceptable if it makes it harder to answer: "What happens when the player claims this battle?"
