# Player-Value Feature Ordering Model
----

Status: active
Last Updated: 2026-03-07
Owner: Product + Game Design
Depends On: `ISSUES.md`, `MILESTONES.md`, `documentation/03-ux/09-first-session-player-journey.md`

## Purpose
- Provide a repeatable rubric for ordering upcoming features by player impact.
- Improve sequencing decisions across UX, systems, and maintainability lanes.

## Scoring Dimensions
- Clarity Impact (0-3)
  - How much the change reduces confusion in core loop decisions.

- Engagement Impact (0-3)
  - How much the change increases meaningful player agency/fun.

- Retention Impact (0-3)
  - How much the change improves likelihood of second/third session return.

- Delivery Risk (0-3, inverse)
  - Lower score means lower implementation risk and faster reliable delivery.

## Priority Heuristic
- Compute composite score:
  - `player_value = clarity + engagement + retention`
  - `delivery_modifier = 3 - risk`
  - `total = player_value + delivery_modifier`

- Prioritize:
  1. high `total` and low regression risk
  2. features that unblock other high-value UX improvements
  3. polish work only after core clarity/flow blockers are resolved

## Sequencing Rules
- Do not prioritize aesthetic polish above first-session clarity blockers.
- Prefer improvements that tighten feedback loops (decision -> result -> next step).
- Bundle related UX surfaces when shared components already exist.

## Usage in Backlog Triage
- During milestone planning, assign each candidate issue provisional scores.
- Document rationale in issue description when scores materially affect order.
- Re-score when dependencies or implementation scope shifts.
