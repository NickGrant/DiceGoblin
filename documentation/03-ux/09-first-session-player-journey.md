# First-Session Player Journey
----

Status: active
Last Updated: 2026-07-23
Owner: Product + UX
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`

## Purpose

- Define the canonical first-session path from login to first completed run.
- Keep onboarding, objective framing, and first-run feedback aligned.

## Onboarding Goal

A new player should quickly understand:

- what they are trying to do
- where to prepare their squad
- how a run progresses
- why rewards and progression matter

The first-session objective is simple:

- start and finish a run

## Journey Stages

### 1. Login

- Show local registration and sign-in as the primary access path.
- Keep Discord sign-in available as an alternate path.
- If the player is already authenticated, route directly into the app.
- Successful local registration should feel identical to any other first login: the next screen is the authenticated game shell, not a separate account setup flow.

### 2. Home Orientation

- Make the primary run action the most obvious choice.
- Show that warband and dice management exist as preparation surfaces.
- Reinforce that energy and currency are persistent resources.
- Keep navigation density responsive across 0-440px, 441-760px, and 761px+ so the home shell still reads like a game screen on smaller devices.

Suggested framing:

- primary objective: start or continue a run
- secondary objective: adjust squad and dice before committing

### 3. Warband Preparation

- Player confirms at least one usable squad.
- Squad purpose should be explained plainly: squads determine who enters a run and where they start.
- Success or failure feedback should be immediate after edits.

### 4. Region Selection

- Player chooses an unlocked region.
- Locked-region feedback should explain why the region is blocked and what unlocks it.
- Region choice should imply differences in encounter tone or pacing without overwhelming detail.

### 5. Run Progression

- Player chooses available nodes and sees clear path progression.
- Map affordances should explain:
  - rest means recovery
  - other nodes mean encounter resolution
- Node previews should help decision-making without revealing exact outcomes.

### 6. Run Closure

- Show outcome, rewards, progression, and squad impact.
- Return the player to home with a clear sense of what improved and what to do next.

## Messaging Rules

- Do not rely on hidden rules or external knowledge at critical moments.
- Blocked states should always explain the next step.
- Objective framing should stay short and action-oriented.
- The player should always know whether they are preparing, resolving, or collecting outcomes.

## First-Session Success Criteria

- The player can reach first run completion without leaving the main game flow.
- Every blocking state includes clear feedback.
- The value of the loop is visible: prepare -> choose -> resolve -> progress -> repeat.
