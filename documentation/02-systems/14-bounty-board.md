---
Title: "Bounty Board"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - backend/src/Services/BountyBoardService.php
  - backend/src/Controllers/BountyBoardController.php
  - backend/migrations/61_bounty_board_foundation.sql
Category: 02-systems
Tags:
  - systems
  - bounties
---

# Bounty Board

## Current Runtime

Bounties use `bounty_definitions` for authored objectives and rewards, and `user_bounties` for accepted player state. The board endpoint returns definitions with the user's current acceptance/progress state.

Players can accept, sync progress, and claim completed bounties. Claiming is idempotent: an already claimed bounty returns the claimed payload without duplicating rewards.

## Objective Inputs

Current objective JSON supports event-style checks such as completed runs and claimed battle victories, with optional region scoping.

## Backend Boundary

The backend owns active bounty limits, duplicate acceptance prevention, progress sync, completion detection, reward granting, and idempotent claim behavior.

## Known Gaps

- No primary frontend board page is documented in the current Figma queue.
- Reward shapes should stay aligned with the shared reward materializer proposed in the API cleanup plan.
