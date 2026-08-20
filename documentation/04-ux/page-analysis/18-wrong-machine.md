---
Title: "Wrong Machine Page Analysis"
Status: Needs Review
Last Updated: 2026-08-19
Owner: Product + UX
Depends On:
  - documentation/04-ux/page-analysis/00-index.md
  - documentation/04-ux/08-page-layout-zones.md
  - documentation/02-systems/09-kin-reconstruction.md
Category: 04-ux
Tags:
  - ux
  - page-analysis
  - wrong-machine
---

# Wrong Machine Page Analysis

Route: `/wrong-machine`  
Auth: authenticated, feature-gated  
Component: `frontend/src/app/pages/wrong-machine-page/WrongMachinePageComponent`

## UX Pieces

- Shared authenticated HUD.
- PageFrame header with HQ breadcrumb.
- Alert stack for errors, success messages, locked state, and loading state.
- Feature-locked state when the Wrong Machine has not been recovered.
- Reconstruction stage with visual machine core, status summary, and key facts.
- Recipe panel for the first Pig Kin reconstruction.
- Requirement cards with Raw Chaos and material progress meters.
- Grant preview showing the resulting kin unit reward.
- Reconstruction result/action row with stateful CTA.
- Post-success grant card linking to the granted unit details page.

## Data Displayed

- Feature unlock state from the Wrong Machine reconstruction payload.
- Available reconstruction options, currently rendered as the first Pig Kin option.
- Per option:
  - lineage slug and name
  - description
  - unlocked state
  - eligibility state
  - missing requirements
  - Raw Chaos owned and required
  - item costs, owned quantities, required quantities, and met state
  - granted unit count
- Last reconstruction result:
  - lineage name
  - newly reconstructed flag
  - granted unit id for navigation

## Interaction States

- Loading: shows a single warm-up alert.
- Locked: shows a feature-gated alert and no recipe controls.
- Incomplete: shows missing requirements and disables reconstruction.
- Ready: enables reconstruction and previews spend/result.
- Busy: disables the active reconstruction button and changes CTA copy.
- Already unlocked: displays unlocked state and keeps duplicate reconstruction idempotent from the UI perspective.
- Success: reloads reconstruction state, shows success alert, and surfaces the granted unit link.

## Notes

- This page is demo-critical because first Pig Kin reconstruction is the current release endpoint.
- Current runtime treats the first Pig Kin reconstruction as a one-time lineage unlock with idempotent duplicate behavior.
- Target-system docs describe a more repeatable recipe/request model; do not implement that UX until the backend contract changes.
- The Figma pass should reuse shared requirement/cost and result-preview primitives where possible.
