# MILESTONES BACKLOG
----

## Purpose
- `agent/MILESTONES_BACKLOG.md` tracks deferred milestone groupings outside the active execution lane.
- Keep `agent/MILESTONES.md` focused on active/current milestone execution context.
- Promote milestones from this file into `agent/MILESTONES.md` when they are opened for execution.

## Backlog Milestones

---
name: Authenticated Shell Fullscreen UX Pass
status: not-started
issues:
  - UX-001
  - UX-002
  - UX-003
  - UX-004
description: Shift the authenticated game experience away from page-like layouts toward a responsive full-screen shell that feels like a game client across mobile, tablet, and desktop. The pass should establish a mobile-first layout contract, unify breakpoint behavior at 0-440px, 441-760px, and 761px+, and introduce screen-to-screen transitions that reduce the feel of traditional web page swaps.
goals:
  - Make authenticated screens fill the viewport and feel like a persistent game shell rather than isolated web pages.
  - Define and implement a mobile-first responsive layout system for 0-440px, 441-760px, and 761px+.
  - Introduce reusable route and screen transitions that make navigation feel intentional and game-like.
  - Validate the shell, HUD, navigation, spacing, and content behavior across core screens before broad rollout.
current_code_context: Primary implementation will likely touch frontend shell/layout components, route containers, shared page-frame components, top HUD/navigation behavior, viewport spacing rules, and screen-level SCSS. UX reference docs under documentation/03-ux/ should be updated to reflect the new shell and motion rules.
exit_criteria:
  - Authenticated pages use a consistent full-screen shell instead of page-like centered layouts where not intentionally required.
  - The three responsive breakpoints are documented and consistently implemented.
  - Mobile navigation, HUD density, and page framing remain usable without wasting excessive vertical space.
  - Core screens share a reusable transition pattern for route changes and stateful screen reveals.
  - The revised shell is verified across home, warband, inventory, shop, academy, and run-related flows.
