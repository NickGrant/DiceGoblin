# UX Rebuild - Component Specifications
----

Status: active  
Last Updated: 2026-03-09  
Owner: UX + Frontend  
Depends On: `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose
- Define implementation contracts for rebuild components with minimal duplication.

## Global Rules
- List lifecycle (`ListContainer`): `loading`, `ready`, `empty`, `error`; pagination visible only when `totalPages > 1`.
- Button variants: `Accept` and `Reject` are semantic style variants of the generic action button behavior.
- All authenticated scenes must include:
  - `BackgroundImage`
  - `BottomCommandStrip`
  - `ContentAreaFrame` + `SectionTitleBar` shells for major zones

## Component Contracts
Each contract lists only required inputs, behavior, and failure policy.

### Navigation
- `HomeNavigationPanel`
  - Inputs: `title`, `targetSceneKey`, `areaRect`, optional `bodyImageKey|bodyColor`.
  - Behavior: panel click routes to scene; supports disabled and hover states.
  - Failure: if `bodyImageKey` missing, render color fallback and keep interaction.
- `BottomCommandStrip`
  - Inputs: profile source (`playerName`, `energyCurrent`, `energyMax`) and actions (`home`, `warband`, `dice`, `logout`).
  - Behavior: fixed bottom split strip; left shows `home/warband/dice + energy`; right shows `playerName + logout`.
  - Failure: stale profile values allowed; logout network failure must not block local logout transition.
- `RegionSelectionCard`
  - Inputs: `regionId`, `label`, `lockedState`, `onSelect`.
  - Behavior: unlock-aware region start action.
  - Failure: blocked/failed start surfaces inline feedback and preserves scene usability.

### Map Flow
- `RunMapGraphSurface`
  - Inputs: `nodes[]`, `edges[]`, `runState`.
  - Behavior: renders graph; routes node click handling.
  - Failure: scene-level fallback text/action when map payload is invalid.
- `RunNodeVisual`
  - Inputs: `nodeType`, `nodeStatus`, `position`.
  - Behavior: stateful node icon + affordance (`locked`, `available`, `cleared`, `selected`).
  - Failure: unknown type renders generic fallback node icon.
- `RunNodeEdgeIndicator`
  - Inputs: `fromNodeId`, `toNodeId`, `edgeState`.
  - Behavior: directional lock/unlock edge cues.
  - Failure: invalid edges are skipped.
- `RunActionList`
  - Inputs: `actions[]`.
  - Behavior: shared action button stack for map operations.

### Layout
- `SectionTitleBar`
  - Inputs: `title`, `rect`, optional `backgroundTextureKey`.
  - Behavior: full-width section heading aligned to zone width.
  - Failure: texture fallback to solid-color title bar.
- `ContentAreaFrame`
  - Inputs: `rect`, `title`, optional `marginPx`, `bodyColor|bodyImageKey`.
  - Behavior: title + body structural shell with consistent margins.
  - Failure: body image fallback to configured color.

### Lists and Grids
- `ListContainer`
  - Inputs: `items[]`, `loadState`, `pageState`, `renderVariant`, optional retry callback.
  - Behavior: centralized loading/empty/error/pagination orchestration.
  - Failure: explicit error panel with optional retry.
- `NameLinkListVariant`
  - Inputs: `items[]`, `displayLabel`, `onSelect`.
  - Behavior: selectable row list.
- `GridListVariant`
  - Inputs: `items[]`, `cardRenderer`, `columns`, `onSelect`.
  - Behavior: card-grid renderer with stable spacing and selection.
- `FormationGrid3x3`
  - Inputs: `cells`, `formationState`, `onCellClick`.
  - Behavior: placement and selection grid for formation editing.
  - Failure: invalid placement blocked with feedback.

### Buttons, Dialogs, Feedback
- `GenericActionButton`
  - Inputs: `label`, `onClick`, `enabledState`.
  - Behavior: shared `default/hover/pressed/disabled` interaction contract.
- `AcceptButton`, `RejectButton`
  - Inputs: `label`, `onClick`.
  - Behavior: semantic style variants of `GenericActionButton`.
- `ConfirmationDialog`
  - Inputs: `title`, `message`, `acceptLabel`, `rejectLabel`, `onAccept`, `onReject`.
  - Behavior: modal confirmation with blocked background interaction.
  - Failure: callback errors surface inline and keep dialog open.
- `ToastMessage`
  - Inputs: `message`, `severity`, `durationMs`.
  - Behavior: transient non-blocking feedback.
- `Tooltip`
  - Inputs: `text`, `anchorTarget`, `placement`.
  - Behavior: contextual hover/focus assist.

### Summaries
- `RestSummaryPanel`
  - Inputs: `healingData`, `progressionData`.
  - Behavior: post-rest outcome summary.
- `RunEndSummaryPanel`
  - Inputs: `runStatus`, `rewards[]`, `progression[]`, `survivors[]`, `defeated[]`.
  - Behavior: terminal run summary with grouped outcome sections.
