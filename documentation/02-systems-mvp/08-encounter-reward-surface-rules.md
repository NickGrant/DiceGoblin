# Encounter Reward Surface Rules
----

Status: active  
Last Updated: 2026-06-21  
Owner: Systems Design + UX  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose

- Define what the current node-resolution and reward screens should communicate.
- Keep player-facing reward copy aligned with the actual claim payloads.

## Shared Surface Contract

The current node page should communicate:

- encounter type
- outcome or resolution status
- reward preview or claimed reward result
- next-path unlock count where relevant

Combat-like nodes additionally surface the stored battle log.

## Loot Node Surface

Loot nodes currently present:

- a treasure-focused hero panel
- teeth total
- number of dice found
- number of units found
- item label chips when label data is available
- current status
- unlocked next-path count

Loot nodes should not imply combat XP.

## Combat and Boss Surface

Combat and boss nodes currently present:

- battle outcome
- rounds
- stored battle log
- claim action for rewards

Combat and boss reward messaging may include:

- XP totals
- soft currency
- unit grants
- dice grants
- updated run resolution state after claim

## Rest Surface

Rest is intentionally separate from the generic node reward page.

The rest flow should communicate:

- current run-unit condition before recovery
- that finalizing rest heals, clears defeated state, and resets recovery-related state
- return to the map after finalize

## Exit Surface

Exit is not a normal node-resolution screen.

- Exit is completed from the run map.
- Success routes the player to run summary.

## Failure Messaging

Current player-facing messaging should reflect:

- combat or boss defeat immediately ends the run
- there is no standard in-run retry after that defeat

Older retry-oriented language should not be used for the active alpha contract.

## Idempotency Messaging

- Repeated reward claims must not imply duplicate payout.
- Reopened completed nodes should reflect the stored authoritative result.

## Validation Rules

The reward-surface docs are aligned when:

- loot nodes are documented as non-XP reward screens
- combat and boss nodes are documented around stored logs plus claim
- failure messaging reflects terminal defeat rather than a standard retry loop
