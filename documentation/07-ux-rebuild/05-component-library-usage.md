# UX Rebuild - Component Library Usage Examples
----

Status: active  
Last Updated: 2026-03-09  
Owner: Frontend
Depends On: `documentation/07-ux-rebuild/03-component-specifications.md`

## Purpose
- Provide concise integration examples for Milestone 15 reusable components.

## Core Layout Shell
```ts
const section = new ContentAreaFrame({
  scene: this,
  x: layout.content.x,
  y: layout.content.y,
  width: layout.content.width,
  height: layout.content.height,
  title: "Start Run",
  bodyImageKey: "ux_start_run",
  useImageEdgeToEdge: true,
});
```

## Home Navigation Panel
```ts
new HomeNavigationPanel({
  scene: this,
  areaRect: layout.buttons,
  title: "Manage Warband",
  targetSceneKey: "WarbandManagementScene",
  bodyColor: 0x00f6ff,
});
```

## Shared List Framework
```ts
new ListContainer({
  scene: this,
  x: 40,
  y: 240,
  width: 560,
  height: 620,
  items: squads,
  loadState: "ready",
  pageSize: 6,
  onSelect: (squad) => this.scene.start("SquadDetailsScene", { squadId: squad.id }),
  renderItems: ({ scene, parent, items, contentX, contentY, contentWidth, onSelect }) =>
    renderNameLinkList({
      scene,
      parent,
      x: contentX,
      y: contentY,
      width: contentWidth,
      items: items.map((squad) => ({ item: squad, label: squad.name })),
      onSelect,
    }),
});
```

## Action Variants
```ts
new ActionButtonList({
  scene: this,
  x: layout.buttons.x,
  y: layout.buttons.y + 80,
  buttons: [
    { label: "Save", buttonType: "default", onClick: () => this.save() },
    { label: "Accept", buttonType: "accept", onClick: () => this.accept() },
    { label: "Cancel", buttonType: "reject", onClick: () => this.cancel() },
  ],
});
```

## HUD + Feedback
```ts
mountBottomCommandStrip(this);

new ConfirmationDialog({
  scene: this,
  title: "Abandon Run?",
  message: "You will lose this run immediately.",
  acceptLabel: "Abandon",
  rejectLabel: "Keep Run",
  onAccept: () => this.abandonRun(),
  onReject: () => undefined,
});

new ToastMessage({
  scene: this,
  x: 24,
  y: this.scale.height - 72,
  message: "Run updated",
  severity: "success",
  durationMs: 1500,
});
```
