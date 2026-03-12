---
name: scene-screenshot
description: Capture a specific Dice Goblins Phaser scene as a deterministic screenshot by driving the local debug scene loader and Playwright capture command. Use when Codex needs to review UI changes, compare scene layouts, inspect a single scene without manual clicking, or generate repeatable screenshot artifacts for scenes such as Home, Warband, Map, Rest, Node Resolution, or Run Summary.
---

# Scene Screenshot

Use the repo capture command instead of manual browser navigation whenever the user wants a screenshot of a specific scene.

## Workflow

1. Read [scene-debug-params.md](references/scene-debug-params.md) to confirm the supported scene aliases, auth modes, and payload format.
2. Decide whether the target scene can boot from just the debug loader or needs `debugSceneData` such as `unitId`, `squadId`, `runId`, or `nodeId`.
3. If Playwright browsers are not installed, run `npm run capture:scene:install`.
4. Capture the scene with `npm run capture:scene -- --scene <scene> ...`.
5. If the user asks for analysis of the image, inspect the saved file after capture.

## Commands

Basic capture:

```powershell
npm run capture:scene -- --scene home
```

Pass scene data:

```powershell
npm run capture:scene -- --scene unit --scene-data "{\"unitId\":\"12\"}"
```

Use an existing frontend server instead of auto-starting Vite:

```powershell
npm run capture:scene -- --scene map --base-url http://127.0.0.1:5173/
```

## Rules

- Prefer the root capture command over ad hoc Playwright snippets so the URL params and ready signal stay consistent.
- When a scene loads async data, let the command wait for the scene-ready flag and use `--settle-ms` if the visual state needs a little extra time.
- If the scene requires live API-backed state, point the command at the running local frontend/backend instead of inventing mock navigation steps.
- If capture fails because the scene cannot boot without required payload, add the missing `--scene-data` object instead of retrying blindly.
