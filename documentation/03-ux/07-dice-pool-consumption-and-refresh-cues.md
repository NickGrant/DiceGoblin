# Dice Pool Consumption and Refresh Cues
----

Status: active  
Last Updated: 2026-03-03  
Owner: Combat Systems + UX + Frontend  
Depends On: `documentation/02-systems-mvp/01-dice-system.md`, `documentation/03-ux/04-combat-viewer-readability.md`

## Purpose
- Define how dice consumption and pool refresh behavior are surfaced in combat logs and UI.
- Ensure players can understand ability costs and outcomes without guessing hidden pool state.

## Source Rules (Gameplay)
- Dice are consumed largest-to-smallest for each ability with `diceCost > 0`.
- If the smallest die is consumed, the pool refreshes immediately.
- If an ability cost exceeds remaining dice:
  - consume remaining dice,
  - refresh,
  - consume the remainder.

## Required Combat Log Events
- `Dice Spent`: include ability name and dice consumed (for example `d10, d8`).
- `Pool Refresh`: explicit event whenever refresh occurs.
- `Post-Action Pool`: summary of remaining dice after each costing action.

## Visual Cue Contract

### Primary HUD Surface
- Show current pool as ordered chips (largest to smallest).
- Spent dice animate out in the same order they are consumed.
- Refresh animation repopulates the full ordered pool with a short pulse effect.

### Log Surface
- Each costing action appends one grouped entry:
  - line 1: ability + cost,
  - line 2: consumed dice,
  - line 3 (conditional): refresh note when triggered.
- On mobile, grouped entry must remain readable without hover.

## Labeling Rules
- Always use die-size labels (`d4`, `d6`, `d8`, `d10`).
- Do not abbreviate refresh event text below `Pool Refreshed`.
- If no dice were consumed (`diceCost = 0`), do not emit spent/refresh events.

## Edge Case Presentation
- Multi-refresh action: show each refresh as separate event in order.
- Partial consume then refresh: log both phases in one grouped action block.
- Missing pool telemetry from backend:
  - keep combat playable,
  - show fallback message `Dice details unavailable for this action.`

## QA Checks
- Largest-to-smallest visual spend order matches rules for all enabled die sizes.
- Refresh event appears exactly when smallest die is consumed.
- Overflow-cost actions show consume -> refresh -> consume ordering correctly.
- Mobile log entries remain legible and complete for all costing actions.

