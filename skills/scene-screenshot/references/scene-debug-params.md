# Scene Debug Params

Use these URL-driven debug parameters through `npm run capture:scene`.

## Required

- `debugScene`
  - Scene key or alias.
  - Supported aliases: `boot`, `preload`, `landing`, `home`, `region`, `warband`, `squad`, `unit`, `dice`, `map`, `node`, `rest`, `summary`.

## Optional

- `debugAuth`
  - `authenticated` (default), `guest`, or `live`
  - `live` keeps the normal session bootstrap instead of injecting a debug session.
- `debugDisplayName`
  - Display name for authenticated debug mode.
- `debugUserId`
  - User id for authenticated debug mode.
- `debugSceneData`
  - JSON object passed to the target scene.
  - Examples:
    - `{"unitId":"12"}`
    - `{"squadId":"7"}`
    - `{"runId":"9","nodeId":"501"}`
    - `{"status":"completed","rewards":["Gold x10"]}`
- `debugSettleMs`
  - Extra client-side wait after the scene reports ready.

## Notes

- `HomeScene`, `WarbandManagementScene`, `UnitDetailsScene`, `SquadDetailsScene`, `DiceInventoryScene`, `MapExplorationScene`, `NodeResolutionScene`, and `RestManagementScene` still depend on live API responses for their full content.
- `NodeResolutionScene`, `RestManagementScene`, `UnitDetailsScene`, `SquadDetailsScene`, and `DiceInventoryScene` may need `debugSceneData` to avoid missing-context fallbacks.
