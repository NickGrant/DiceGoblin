# UX Rebuild - Component Specifications
----

Status: active  
Last Updated: 2026-03-08  
Owner: UX + Frontend  
Depends On: `documentation/07-ux-rebuild/01-all-up-component-list.md`

## Purpose
- Define rebuild components with enough structure for implementation, UX review, and QA.

## Spec Template
- Name
- Category
- Inputs (Required Data)
- Extends/Composes
- Functionality
- States
- Acceptance Criteria
- Failure Behavior
- Player Goal
- Description

## Shared Patterns
### List Behavior Matrix (`ListContainer`)
- `loading`: show loading state; hide pagination and item renderer.
- `ready` with items: render selected variant (`NameLinkList` or `GridList`) and pagination if needed.
- `ready` with zero items: show empty state with optional action.
- `error`: show error state with optional retry callback.
- Pagination: `Prev/Next` disabled at bounds; page label always visible when `totalPages > 1`.

### Button Variant Pattern
- `Generic Action Button` is the base behavior.
- `Accept Button` and `Reject Button` are style/intent variants of the base, not separate behavior classes.

## Specifications
### Home Navigation Panel
- Name: Home Navigation Panel
- Category: Navigation
- Inputs (Required Data): `title`, `targetSceneKey`, `areaRect`, `bodyImageKey`
- Extends/Composes: Composes `Section Title Bar` and image-backed body region.
- Functionality: Renders home section panel and routes to target scene on click.
- States: `default`, `hover`, `disabled`
- Acceptance Criteria: Click anywhere in panel routes correctly; title and body image match configured panel.
- Failure Behavior: If image key missing, render fallback body color and log warning.
- Player Goal: Quickly recognize and enter desired top-level flow.
- Description: Reusable home-panel component used for run, warband, and inventory entry.

### Bottom Command Strip (Split Left/Right)
- Name: Bottom Command Strip
- Category: Global Navigation + Status
- Inputs (Required Data): `playerName`, `energyCurrent`, `energyMax`, link callbacks (`warband`, `dice`, `logout`)
- Extends/Composes: Composes shared strip shell plus two visual segments (`left`, `right`).
- Functionality: Provides persistent authenticated-session controls at screen bottom.
- States: `loading`, `ready`, `error(stale)`, `disabled(action-scoped)`
- Acceptance Criteria:
  - Left segment always shows links to Warband and Dice plus current energy value.
  - Right segment always shows Logout action and current player name.
  - Strip remains anchored to bottom on resize.
- Failure Behavior:
  - If profile fetch fails, preserve last known name/energy and keep strip interactive where safe.
  - If logout mutation fails, show inline feedback without collapsing strip.
- Player Goal: Access account/navigation controls and resource state without scanning corners.
- Description: Replaces legacy corner-home and corner-energy widgets with one split global strip.

### Region Selection Card/Panel
- Name: Region Selection Card/Panel
- Category: Navigation
- Inputs (Required Data): `regionId`, `label`, `lockedState`, `onSelect`
- Extends/Composes: Extends clickable panel base.
- Functionality: Lets user start run in selected region when allowed.
- States: `unlocked`, `locked`, `hover`, `disabled`
- Acceptance Criteria: Locked regions show blocked feedback; unlocked regions route/start run.
- Failure Behavior: Show inline/overlay error and keep scene interactive.
- Player Goal: Choose a region and enter run flow without confusion.
- Description: Region selection surface used before map entry.

### Run Map Graph Surface
- Name: Run Map Graph Surface
- Category: Map Visualization
- Inputs (Required Data): `nodes[]`, `edges[]`, `runState`
- Extends/Composes: Composes `Run Node Visual`, `Run Node-Edge/Unlock Indicator`.
- Functionality: Renders map graph and dispatches node click actions.
- States: `loading`, `ready`, `empty`, `error`
- Acceptance Criteria: Nodes are in bounds; edges connect correctly; node clicks resolve by type/status.
- Failure Behavior: Show map-level fallback text and retry path.
- Player Goal: Understand available choices and progress route.
- Description: Active-run map container.

### Run Node Visual
- Name: Run Node Visual
- Category: Map Visualization
- Inputs (Required Data): `nodeType`, `nodeStatus`, `position`
- Extends/Composes: Atomic graph node renderer.
- Functionality: Shows icon/state and click affordance for node.
- States: `locked`, `available`, `cleared`, `selected`
- Acceptance Criteria: Node visual state matches backend status.
- Failure Behavior: Render generic fallback node if type is unknown.
- Player Goal: Identify what each node is and whether it is actionable.
- Description: Individual node presentation for run map.

### Run Node-Edge/Unlock Indicator
- Name: Run Node-Edge/Unlock Indicator
- Category: Map Visualization
- Inputs (Required Data): `fromNodeId`, `toNodeId`, `edgeState`
- Extends/Composes: Edge/line renderer layer.
- Functionality: Shows directional unlock relationships between nodes.
- States: `lockedPath`, `availablePath`, `clearedPath`
- Acceptance Criteria: Direction and state coloring/line style are consistent and readable.
- Failure Behavior: Hide invalid edge and log warning.
- Player Goal: Understand dependencies between encounters.
- Description: Relationship lines for map progression clarity.

### Run Action List
- Name: Run Action List
- Category: Controls
- Inputs (Required Data): `actions[]`
- Extends/Composes: Composes `Generic Action Button` instances.
- Functionality: Hosts map-level actions (refresh, abandon, etc.).
- States: `ready`, `disabled`
- Acceptance Criteria: Buttons execute mapped callbacks in order/labeling.
- Failure Behavior: Disabled buttons remain visible with reason where possible.
- Player Goal: Perform run-level operations quickly.
- Description: Scene-local action stack for map flow.

### Confirmation Dialog
- Name: Confirmation Dialog
- Category: Dialog
- Inputs (Required Data): `title`, `message`, `acceptLabel`, `rejectLabel`, `onAccept`, `onReject`
- Extends/Composes: Composes `Accept Button` + `Reject Button`.
- Functionality: Captures explicit user confirmation for sensitive actions.
- States: `open`, `closed`
- Acceptance Criteria: Background is blocked while open; accept/reject routes correctly.
- Failure Behavior: On callback error, keep dialog open and show error text.
- Player Goal: Make safe decisions before destructive actions.
- Description: Reusable modal confirmation surface.

### Toast/Status Feedback Message
- Name: Toast/Status Feedback Message
- Category: Feedback
- Inputs (Required Data): `message`, `severity`, `durationMs`
- Extends/Composes: Lightweight message overlay.
- Functionality: Displays transient operation feedback.
- States: `showing`, `hidden`
- Acceptance Criteria: Message appears at configured position and auto-dismisses.
- Failure Behavior: If toast queue overflows, keep latest severity `error` message.
- Player Goal: Receive immediate operation result feedback.
- Description: General non-blocking notification component.

### Section Title Bar
- Name: Section Title Bar
- Category: Layout
- Inputs (Required Data): `title`, `rect`, `backgroundTextureKey`
- Extends/Composes: Structural header element.
- Functionality: Renders full-width title header for section.
- States: `default`
- Acceptance Criteria: Title bar width equals section width; title readable across scene themes.
- Failure Behavior: Fallback to solid color bar if texture missing.
- Player Goal: Understand what each section is for.
- Description: Shared heading pattern.

### Content Area Frame
- Name: Content Area Frame
- Category: Layout
- Inputs (Required Data): `rect`, `title`, `marginPx`, `bodyColor|bodyImageKey`
- Extends/Composes: Composes `Section Title Bar` plus body region.
- Functionality: Provides consistent section shell for both image and non-image bodies.
- States: `default`
- Acceptance Criteria: Margin and body sizing rules follow UX contract.
- Failure Behavior: If body asset fails, render fallback color body.
- Player Goal: Parse sections quickly and predict interaction boundaries.
- Description: Structural wrapper for major screen regions.

### List Container
- Name: List Container
- Category: List
- Inputs (Required Data): `items[]`, `loadState`, `errorState`, `pageState`, `renderVariant`
- Extends/Composes: Composes loading, error, empty, pagination, and variant renderer.
- Functionality: Centralized list state and pagination orchestration.
- States: `loading`, `ready`, `empty`, `error`
- Acceptance Criteria: Follows shared list behavior matrix for all states.
- Failure Behavior: Shows error state and optional retry action.
- Player Goal: Read and act on list data without ambiguity.
- Description: Base list lifecycle shell used by all list screens.

### Name/Link List Variant
- Name: Name/Link List Variant
- Category: List
- Inputs (Required Data): `items[]`, `displayLabel(item)`, `onSelect(item)`
- Extends/Composes: Render variant for `List Container`.
- Functionality: Renders textual row list with clickable entries.
- States: `default`, `hover`, `selected`, `disabled`
- Acceptance Criteria: Labels are readable; selected row is visually distinct.
- Failure Behavior: Invalid rows are disabled and excluded from selection callbacks.
- Player Goal: Scan and choose one item from a textual set.
- Description: Row list used for squads and similar link-like entities.

### Grid List Variant
- Name: Grid List Variant
- Category: List
- Inputs (Required Data): `items[]`, `cardRenderer`, `columns`, `onSelect(item)`
- Extends/Composes: Render variant for `List Container`.
- Functionality: Renders card grid for asset-rich entities.
- States: `default`, `hover`, `selected`, `disabled`
- Acceptance Criteria: Card spacing/selection remain consistent across page sizes.
- Failure Behavior: Render placeholder card for malformed items.
- Player Goal: Compare visual items and select quickly.
- Description: Grid list used for units, dice, and similar card surfaces.

### Formation Grid (3x3)
- Name: Formation Grid (3x3)
- Category: Grid
- Inputs (Required Data): `cells`, `formationState`, `onCellClick`
- Extends/Composes: Standalone interactive grid.
- Functionality: Supports placement, clearing, and selection on formation cells.
- States: `empty`, `occupied`, `selected`, `disabled`
- Acceptance Criteria: Placement and clear behavior preserve formation invariants.
- Failure Behavior: Reject invalid placement and show feedback.
- Player Goal: Arrange units in desired tactical formation.
- Description: Core squad-position editing surface.

### Promotion Selection Controls
- Name: Promotion Selection Controls
- Category: Controls
- Inputs (Required Data): `primaryUnitId`, `secondaryUnitIds[]`, `eligibilityState`
- Extends/Composes: Composes action buttons + selection state indicators.
- Functionality: Manages promotion candidate selection and validity.
- States: `idle`, `primarySet`, `secondaryPending`, `ready`, `blocked`
- Acceptance Criteria: Promotion can only execute when valid primary+2 secondary set is present.
- Failure Behavior: Shows explicit validation reason and blocks action.
- Player Goal: Complete promotion flow with clear constraints.
- Description: Control set for unit promotion workflow.

### Generic Action Button
- Name: Generic Action Button
- Category: Controls
- Inputs (Required Data): `label`, `onClick`, `enabledState`
- Extends/Composes: Base button behavior and styling contract.
- Functionality: Executes scene operation callback with standard interaction states.
- States: `default`, `hover`, `pressed`, `disabled`
- Acceptance Criteria: Visual and callback behavior consistent across scenes.
- Failure Behavior: Disabled state blocks callback and indicates non-interactive state.
- Player Goal: Trigger actions confidently.
- Description: Base actionable button primitive.

### Accept Button
- Name: Accept Button
- Category: Controls
- Inputs (Required Data): `label`, `onClick`
- Extends/Composes: Extends `Generic Action Button` with positive intent style token.
- Functionality: Executes confirm/approve actions with affirmative style.
- States: `default`, `hover`, `pressed`, `disabled`
- Acceptance Criteria: Behavior equals base button; style communicates positive intent.
- Failure Behavior: Follows base button disabled/error behavior.
- Player Goal: Confirm a safe or desired action.
- Description: Positive semantic button variant.

### Reject Button
- Name: Reject Button
- Category: Controls
- Inputs (Required Data): `label`, `onClick`
- Extends/Composes: Extends `Generic Action Button` with negative/cancel style token.
- Functionality: Executes cancel/reject actions with negative style.
- States: `default`, `hover`, `pressed`, `disabled`
- Acceptance Criteria: Behavior equals base button; style communicates cancel/destructive intent.
- Failure Behavior: Follows base button disabled/error behavior.
- Player Goal: Back out or reject an action.
- Description: Negative semantic button variant.

### Rest Summary Panel
- Name: Rest Summary Panel
- Category: Summary
- Inputs (Required Data): `healingData`, `progressionData`
- Extends/Composes: Summary text/table block.
- Functionality: Displays rest-finalization results.
- States: `ready`, `empty`
- Acceptance Criteria: Healing and progression values are accurate and readable.
- Failure Behavior: Show fallback summary text when data is incomplete.
- Player Goal: Understand what changed during rest.
- Description: Post-rest outcome summary.

### End-of-Run Summary Panel
- Name: End-of-Run Summary Panel
- Category: Summary
- Inputs (Required Data): `runStatus`, `rewards[]`, `progression[]`, `survivors[]`, `defeated[]`
- Extends/Composes: Summary block with grouped sections.
- Functionality: Presents final run outcomes and completion action.
- States: `ready`, `empty`
- Acceptance Criteria: All sections render with correct grouping and fallback values.
- Failure Behavior: Show minimal status-only summary when payload is partial.
- Player Goal: Understand run results and next step.
- Description: Terminal run-results surface.

### Tooltip
- Name: Tooltip
- Category: Feedback
- Inputs (Required Data): `text`, `anchorTarget`, `placement`
- Extends/Composes: Floating overlay element.
- Functionality: Shows contextual hover/focus detail.
- States: `hidden`, `visible`
- Acceptance Criteria: Tooltip anchors correctly and never obscures critical controls.
- Failure Behavior: Hide tooltip if anchor is invalid.
- Player Goal: Access precise detail without leaving current screen.
- Description: On-demand contextual helper text.
