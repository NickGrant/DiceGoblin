---
Title: "Objectives and Demo Guidance"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/ObjectiveService.php
  - frontend/src/app/pages/home-page/home-page.component.ts
  - frontend/src/app/pages/codex-page/codex-page.component.ts
Category: 02-systems
Tags:
  - systems
  - objectives
---

# Objectives and Demo Guidance

## Current Runtime

`ObjectiveService` derives profile objectives from durable gameplay facts rather than storing objective rows. The profile payload includes the ordered objective list, with ids, titles, descriptions, routes, status, priority, progress, and optional metadata.

Home displays only the first incomplete objective as the current guidance action. Codex displays current and completed objectives in its Objectives category.

## Current Objective Inputs

The service currently evaluates:

- active run presence and started-run count
- active squad readiness and squad cap
- dice equipment on owned units
- claimed battle victories
- completed runs
- promotion-ready or promoted units
- region unlock and completion state

## Frontend Boundary

Frontend pages render the backend objective list and choose presentation emphasis. They must not decide whether an objective is complete from local UI state.

## Known Gaps

- Objective definitions are service-authored, not DB-authored.
- The demo chain should be reviewed through first Pig Kin reconstruction.
- If designers need to tune copy or ordering frequently, objective definitions should move to a data-backed catalog.
