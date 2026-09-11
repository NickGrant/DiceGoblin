# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Establish responsive landscape game-host behavior

**Status:** Open
**Priority:** High

#### Problem
Complete the accepted vNext gameplay presentation boundary by making the persistent Phaser runtime and current Camp composition respond correctly to supported landscape viewports. Establish the 1600x900 logical reference model, calculated viewport/layout regions, Compact/Standard/Wide landscape modes, device safe-inset handling, and a mobile-portrait rotate-device gate that preserves the mounted runtime and authoritative state. This package proves responsive host/runtime behavior; it does not add gameplay domains or perform Milestone 1 closure/UAT.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — Responsive Rendering, Variable Landscape Viewport, Anchoring and Layout Regions, Responsive Layout Modes, Game Scale versus Layout Scale, Device Safe Insets, Responsive Verification, Mobile Orientation
- `documentation/04-ux/01-visual-design-guide.md` — canonical visual direction
- `documentation/07-development-path/vnext-game-overhaul.md` — Milestone 1 walking-skeleton exit criteria
- Current `frontend/src/app/game/` runtime, host, scenes, Camp screen, startup/store, and tests
- Current deterministic capture tooling and `agent/QUALITY_GATES.md`
- Existing Angular `ViewportOrientationService` only as prototype/reuse evidence; it is not the vNext `/game` responsive authority

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Treat `1600 x 900` as the primary logical reference composition. Physical display pixels must not become screen-specific positioning coordinates, and individual objects must not be stretched independently to fill arbitrary aspect ratios.
- Establish a small runtime-owned responsive/viewport boundary that derives the current effective logical viewport, responsive layout class, and safe usable regions/insets. It belongs to the persistent game runtime and survives scene/screen changes; do not make Angular gameplay services the responsive authority.
- Support the accepted landscape layout classes: Compact, Standard, and Wide. Now that Camp exists, choose/document deterministic breakpoint thresholds based on representative viewport behavior rather than user-agent/device-name branches. Breakpoints must cause meaningful composition changes, not create independently maintained desktop/mobile screens.
- Preserve a central 16:9/reference-safe composition while allowing wider landscape displays to expose useful peripheral horizontal space. Critical information must remain inside the safe gameplay region and must not require ultrawide space.
- Update Camp to consume calculated layout regions/anchors rather than relying on its current 720px minimum panel and one-shot absolute composition. Camp must remain the same screen/information architecture across modes while margins, decoration, plaque arrangement/sizing, and other presentation may adapt where needed.
- Resizing a landscape viewport must reflow/recompose the active Camp without recreating `GameRuntime`, `RuntimeStartup`, `GameStore`, `ClientContentRegistry`, or refetching bootstrap/client content. The authoritative player state and active screen remain intact across resize.
- Critical player-facing Camp content — identity, Teeth, Raw Chaos, Energy current/normal maximum, including overcap such as `57 / 50` — must remain readable and unobscured in representative Compact, Standard, and Wide landscape viewports.
- Respect device safe insets for critical/interactable UI so landscape cutouts, rounded corners, and gesture regions cannot obscure status/control regions. Decorative backgrounds may extend outside safe regions. Use a framework-neutral browser/runtime measurement boundary appropriate to Phaser; do not create an Angular gameplay-state bridge.
- Mobile gameplay is landscape-only. On a phone-class/mobile-coarse-pointer portrait viewport, obscure/block normal game interaction with a full-screen rotate-device presentation owned by the game host/runtime boundary. Do not design a portrait gameplay layout.
- The portrait gate must preserve the mounted runtime, startup/cache state, active scene/screen, and authoritative Camp values. Rotating back to landscape must resize/reflow and resume the same gameplay state without rebootstrap, content reload, or route navigation.
- Do not indiscriminately block ordinary desktop portrait/narrow browser windows as though every portrait viewport were a phone. Use a deterministic capability/viewport heuristic and cover it with tests; exact implementation may adapt useful behavior from the prototype orientation service without making that Angular service authoritative for `/game`.
- Browser/native orientation locking may be attempted only as progressive enhancement; correct behavior must not depend on a successful lock.
- Preserve the current Angular `/game` ownership boundary: Angular authenticates/routes and mounts/destroys the runtime. Do not restore prototype Angular command controls, status cards, orientation UI, gameplay APIs, or page-level gameplay state over the Phaser surface.
- Preserve startup/content compatibility behavior and the approved Camp authoritative-state boundary. Responsive/orientation changes must not alter bootstrap contracts, content exposure, Energy rules, or introduce new API calls.
- Do not add Warband, regions/run creation, squads/units/dice, Shop, Academy, Wrong Machine, Codex/objectives, gameplay navigation framework, battle/run behavior, final audio migration, or other Milestone 2+ systems.
- Add deterministic frontend coverage for viewport classification, logical/reference-space calculation, safe-region calculation, Camp reflow across modes, resize without runtime/cache replacement or startup refetch, mobile portrait gating, state preservation while gated, and recovery to landscape.
- Produce deterministic Camp visual captures for representative Compact landscape, the 1600x900 Standard reference, and Wide landscape. Also verify the mobile portrait rotate-device presentation. Use the existing capture tooling where applicable rather than inventing a separate screenshot framework.
- If this package resolves previously deferred responsive breakpoint/viewport rules into durable implementation decisions, reconcile those concrete values/rules into the accepted Phaser client architecture documentation rather than leaving source code as the only authority.

#### Completion
Run applicable frontend/layout/context/build gates from `agent/QUALITY_GATES.md`: frontend tests, production build, deterministic visual captures/review for Compact/1600x900/Wide, portrait-gate verification, and relevant context/docs checks. Run bundle check if runtime/bundle composition changes. Leave this package active for architectural/UX review; do not promote or begin the walking-skeleton closure package in the same change.

Manual user UAT is intentionally deferred until Milestone 1 is technically complete. Do not treat manual UAT as an acceptance criterion for this package.
