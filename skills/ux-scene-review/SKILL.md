---
name: ux-scene-review
description: Run iterative UX review loops for Dice Goblins Phaser scenes by combining deterministic scene screenshots with a Senior Developer fix pass and a QA Lead screenshot review pass. Use when the user asks for a scene-by-scene visual polish pass, wants UI criteria checked against captured screenshots, or wants repeated capture to fix to recapture to QA review cycles for one or more scenes.
---

# UX Scene Review

Use this skill when the user wants a structured UI review workflow rather than a one-off screenshot or a generic frontend cleanup.

## Workflow

1. Read [review-loop.md](references/review-loop.md) and extract the target scenes, the user-supplied criteria, and the maximum iteration limit.
2. For each scene, capture a baseline screenshot using `skills/scene-screenshot/SKILL.md`.
3. Switch to the `Senior Developer` lens and make the smallest layout/visibility changes that satisfy the criteria.
4. Capture the updated scene again.
5. Switch to the `QA Lead` lens and review the updated screenshot for remaining issues against the requested criteria.
6. If issues remain, repeat the scene-specific loop up to 5 times before moving to the next scene.
7. Report which scenes passed cleanly, which required multiple iterations, and which still need user judgment.

## Rules

- Reuse `scene-screenshot` for capture details and debug-scene parameters; do not duplicate that workflow unless the capture command itself needs repair.
- Treat the user's criteria list as the acceptance source of truth for the review pass.
- Keep code changes scene-scoped and layout-focused; avoid unrelated styling refactors.
- If a screenshot reveals a likely intentional overlay or art direction choice and the intent is unclear, stop and ask instead of "fixing" it blindly.
- If a scene cannot boot with current debug parameters, either provide the needed scene data or state the blocker before continuing.
- Prefer visible, high-signal fixes first: text contrast, title/body collisions, action-column sizing, clipped controls, and distorted imagery.
- At the end of each scene review, state whether QA accepted the screenshot or whether residual issues remain.

## Prompt Shape

Example trigger:

```text
Please do a UX Scene Review on the Region Select Scene, check for the following:
- Label readability over background graphics
- Good use of vertical and horizontal space for limited content
  - You can make the content larger
```

When the user gives multiple scenes, process them sequentially and complete the full loop per scene before moving on.
