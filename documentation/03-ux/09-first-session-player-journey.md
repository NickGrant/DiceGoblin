# First-Session Player Journey
----

Status: active
Last Updated: 2026-03-08
Owner: Product + UX
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`

## Purpose
- Define the canonical first-session path from login to first completed run.
- Align onboarding, scene transitions, and feedback expectations across UX and implementation.

## Journey Stages
1. Session bootstrap (`LandingScene`)
- Show clear CTA to continue/start.
- On success, route to `HomeScene`.

2. Home orientation (`HomeScene`)
- User sees three primary choices: `Start/Continue Run`, `Manage Warband`, `Manage Inventory`.
- A persistent bottom command strip is visible:
  - left segment: `Manage Warband`, `Manage Dice`, current energy level
  - right segment: `Logout`, player name

3. Pre-run setup (`WarbandManagementScene` and `SquadDetailsScene`)
- Player confirms at least one usable squad.
- If composition changes are made, success/failure feedback is shown immediately.

4. Run start (`RegionSelectScene`)
- Player selects region and gets immediate blocked feedback if unavailable.
- Successful run start routes to `MapExplorationScene`.

5. Run progression (`MapExplorationScene` -> `NodeResolutionScene`/`RestManagementScene`)
- Player chooses nodes and sees clear unlock path progression.
- Non-rest nodes resolve through `NodeResolutionScene`.
- Rest nodes allow roster and progression adjustments.

6. Run closure (`RunEndSummaryScene`)
- Show outcome, rewards, progression, survivors/defeated units.
- Continue routes back to `HomeScene`.

## First-Session Success Criteria
- User can reach first run completion without leaving game flow.
- Every blocking state includes explicit feedback and next-step clarity.
- Core loop value is visible: decision -> encounter -> outcome -> progression.
