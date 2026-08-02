---
Title: "Mobile Viewport Regression Checklist"
Status: Canonical
Last Updated: 2026-08-01
Owner: Product + QA + Engineering
Depends On:
  - agent/MILESTONES.md
  - documentation/04-ux/08-page-layout-zones.md
  - documentation/06-testing-release/00-testing-strategy.md
Category: 06-testing-release
Tags:
  - testing-release
---

# Mobile Viewport Regression Checklist

## Purpose
- Provide a repeatable mobile regression pass for the Angular play flows most likely to regress during layout or HUD changes.
- Catch viewport-specific failures before automated handoff or broader manual playtests.

## When To Run
- Before handing mobile UI changes to automated validation or broader playtesting.
- After edits to:
  - `frontend/src/app/layout/game-shell/`
  - `frontend/src/app/layout/bottom-command-strip/`
  - page-level SCSS under `frontend/src/app/pages/`
  - shared card/grid components used on the listed routes

## Required Viewports
- Portrait phone: `390x844`
- Landscape phone: `844x390`

Optional spot-check when a page is especially dense:
- Narrow portrait fallback: `360x800`

## Setup
1. Start the frontend and backend with the same build/config you plan to hand off for testing.
2. Open Chrome DevTools device emulation.
3. Disable responsive browser zoom drift by keeping page zoom at `100%`.
4. Use an authenticated account for all in-game routes and an anonymous session for `/login`.
5. For run-only routes, use an account with an active run that can reach map, node, and summary screens.

## Global Pass Criteria
- No horizontal page overflow at either required viewport.
- The fixed bottom command strip does not cover the current screen's primary action.
- Cards and list items remain readable without clipped titles, values, or CTA labels.
- Primary actions remain reachable without impossible scroll traps or content hidden behind the HUD.
- No route shows overlapping text that prevents understanding or input.

## Route Checklist

| Route | Viewport focus | Pass checks |
| --- | --- | --- |
| `/login` | portrait + landscape | Sign-in card stays fully readable, primary submit remains visible, no horizontal scroll. |
| `/guide` | portrait + landscape | Section cards wrap cleanly, headings stay readable, `Sign In` or return CTA remains reachable. |
| `/home` | portrait + landscape | Primary feature card remains readable, main CTA stays above the HUD, secondary cards do not clip. |
| `/warband` | portrait + landscape | Squad and unit cards remain single-tap reachable, active-squad status text does not overlap controls. |
| `/warband/squads/:squadId` | portrait + landscape | Formation grid, pool cards, and save action remain reachable; bottom HUD does not cover tap-placement controls. |
| `/warband/units/:unitId` | portrait + landscape | Tab content, loadout bars, dice-slot controls, and save actions remain readable and reachable. |
| `/dice` | portrait + landscape | Filter controls wrap without overlap, dice cards avoid horizontal overflow, `Sell` and unit-view actions remain tappable. |
| `/shop` | portrait + landscape | Tab bar remains usable, deal and upgrade cards stay readable, purchase buttons remain visible. |
| `/regions` | portrait + landscape | Region art/card copy stays readable, start/continue CTA remains fully visible above the HUD. |
| `/run/map` | portrait + landscape | Node map remains scrollable on its intended axis only, formation/status cards stay readable, HUD does not block run actions. |
| `/run/node/:nodeId` | portrait + landscape | Battle or loot summary content stays readable, reward/claim CTA remains reachable, battle log does not force horizontal scroll. |
| `/run/summary` | portrait + landscape | Reward and progression cards stack cleanly, `Return Home` remains visible or reachable without HUD overlap. |

## Failure Signatures To Log
- `hud-overlap`: the bottom command strip covers a CTA, form control, or required state.
- `x-overflow`: the page or a major card forces horizontal scrolling.
- `card-unreadable`: text, numbers, or labels clip or overlap enough to break comprehension.
- `primary-unreachable`: the main path forward exists but cannot be reached through normal scrolling/tapping.

## Evidence Template
Copy this block for each run of the checklist:

```yaml
mobile_regression_id: MI-004-YYYYMMDD-<initials>-<seq>
build_ref: <commit/tag/local>
environment:
  browser: Chrome
  frontend: <local/staging>
  backend: <local/staging>
viewports:
  portrait_390x844: pass | fail | blocked
  landscape_844x390: pass | fail | blocked
routes:
  login: pass | fail | blocked
  guide: pass | fail | blocked
  home: pass | fail | blocked
  warband: pass | fail | blocked
  squad_details: pass | fail | blocked
  unit_details: pass | fail | blocked
  dice: pass | fail | blocked
  shop: pass | fail | blocked
  regions: pass | fail | blocked
  run_map: pass | fail | blocked
  run_node: pass | fail | blocked
  run_summary: pass | fail | blocked
defects:
  - route: <route key>
    viewport: portrait_390x844 | landscape_844x390
    code: hud-overlap | x-overflow | card-unreadable | primary-unreachable
    summary: <brief repro>
notes: |
  <anything unusual>
```
