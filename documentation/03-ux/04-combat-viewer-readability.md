# Combat Viewer Readability Contract - MVP
----

Status: active  
Last Updated: 2026-03-03  
Owner: UX + Frontend  
Depends On: `documentation/03-ux/00-ux-and-debug-scope.md`, `documentation/02-systems-mvp/00-combat-system.md`

## Purpose
- Define concrete readability requirements for combat playback on desktop and mobile.
- Prevent ambiguous battle outcomes and improve replay comprehension.

## Shared Readability Requirements
- Outcome state must remain visible at all playback speeds.
- Current round/tick must be visible while replay is active.
- Action actor and target must be clear in event rows.
- Status effects must use consistent icon + text label pairing.
- Damage/heal numbers must be legible against background art.

## Desktop Contract
- Minimum viewport target: `1280x720`.
- Combat log panel must be visible by default.
- Log panel must show at least 8 full rows without scrolling.
- Playback controls (play/pause, speed, step, skip) must be visible simultaneously.
- HP bars and status icons must remain visible while log is scrolled.

## Mobile Contract
- Minimum viewport target: `390x844`.
- Combat log is collapsed by default but must have an obvious expand affordance.
- Expanded log must cover no more than 55% of viewport height.
- Playback controls must fit in one row or two compact rows without overlap.
- Tap targets for controls must be at least 40px effective size.

## Event Pacing Contract
- `1x` is baseline readable pace.
- `2x` and `4x` are optional speedups but must preserve order and event integrity.
- Skip to outcome must show a summary state before moving to reward claim.
- Step forward/back must be deterministic and not skip events.

## Logging Contract
Each visible event row should include:
- event index or stable ordering cue
- action type (attack, status, heal, defeat, phase)
- actor label
- target label (when applicable)
- magnitude/value when relevant

## Failure Conditions
- Any layout overlap that obscures HP bars, controls, or outcome text.
- Event text truncation that removes actor/target identity.
- Missing status labels where only iconography is shown.
- Control placement that requires horizontal scrolling.

## QA Acceptance Checklist
- Desktop: controls + log + HP remain legible at `1x`, `2x`, `4x`.
- Mobile: collapsed/expanded log transitions do not hide primary controls.
- Skip and step produce deterministic event positions across repeated replays.
- Battle outcome is understandable without opening debug overlay.
