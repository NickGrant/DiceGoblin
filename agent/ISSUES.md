# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 2 - Phaser Codex surface + Camp integration

**Status:** In Progress
**Priority:** High

#### Problem

Package 1 established canonical Codex authored definitions, unique player ownership through the reward pipeline, an authoritative player-safe read contract, and strict lazy client cache support. Players still have no mounted Phaser surface for viewing discovered knowledge.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Package 1 is approved through correction `88ed69f62826977f37d5c5eb365913d9805dcc5f`, with review evidence recorded at `eaea0d2a497d2b4a1cf7300232ff7a5d58f47ec7`.
- Canonical Codex definitions use stable `codex.*` IDs and player-facing `title`/`body` fields.
- `GET /api/v1/codex` is the authoritative player-safe catalog/ownership projection: owned entries expose body text; unowned entries expose `body: null`.
- `GameStore` already owns the strict lazy Codex cache and invalidates it on player revision changes.
- Codex ownership/reward semantics are server authoritative and are not to be redesigned in this package.
- The major game-wide visual/UI overhaul remains deferred.

#### Purpose

Add the smallest complete player-facing Codex experience in Phaser and integrate it into Camp using the accepted runtime/cache architecture.

The surface must let a player discover that the Codex exists, open it from Camp, distinguish discovered from undiscovered authored entries, read owned knowledge, return safely to Camp, and remain usable across the canonical responsive layouts.

Do not add dialogue routing, objective state, or new Codex ownership/grant mechanics.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- current Camp facility/navigation patterns and scene lifecycle;
- existing Phaser collection/list/detail surfaces such as Inventory, Shop, Academy, or Warband where structurally useful;
- `GameStore` Codex lazy cache introduced in Package 1;
- current responsive/orientation helpers and deterministic capture fixtures;
- current player-facing loading/error/retry patterns;
- visual guide constraints relevant to a knowledge/catalog surface.

Reuse established runtime/input/layout/cache boundaries. Do not create a second Codex store or Angular gameplay page.

#### Canonical interaction requirements

##### Camp integration

- Provide a clear Codex entry point from the mounted Phaser Camp experience.
- The Codex is a knowledge/reference surface, not a purchase/progression mutation facility; do not invent a new unlock requirement unless an existing canonical requirement already governs access.
- Opening and closing the Codex must preserve normal Camp/runtime state and navigation behavior.
- Do not replace or globally redesign unrelated Camp facilities in this package.

##### Codex presentation

- Load Codex through the accepted lazy `GameStore` cache/read contract.
- Render authored entries in authoritative server order; do not maintain a client-authored Codex catalog.
- Clearly distinguish owned/discovered entries from unowned/undiscovered entries.
- Owned entries may show their authoritative title/body presentation.
- Unowned entries must not reveal hidden body text or infer it from client content/assets. Their presentation may use the safe title/owned state already supplied by the server.
- Selection/detail state is client presentation state only and must not become durable progression state.
- Do not infer Codex ownership from combat history, unlocks, objectives, local events, or other client state.

##### Loading, errors, and cache reconciliation

- Opening the surface with an unloaded Codex slice must present a usable loading state while the lazy query is in flight.
- A read failure must produce a recoverable player-facing state; retry must use the authoritative query rather than fabricate cached entries.
- If player revision invalidates the Codex cache, the next relevant read/open must reconcile from the server.
- Do not introduce a Codex mutation endpoint or local optimistic ownership changes.

##### Responsive behavior

- Follow the canonical Compact/Standard/Wide layout model and existing portrait rotate-device gate.
- The list/detail presentation must remain legible with reasonable authored title/body lengths at the canonical verification viewports.
- Scrolling or paging is acceptable where necessary; do not truncate owned knowledge in a way that makes it unreadable.
- This package is functional presentation, not final visual polish.

#### Minimum proof

Prove at minimum:

1. Camp exposes a usable Codex entry point without disrupting existing facility navigation.
2. Opening Codex lazily loads the authoritative slice through `GameStore`.
3. Owned and unowned entries are visually distinguishable.
4. Owned entry detail renders the authoritative body.
5. Unowned entry presentation never reveals/fabricates body text.
6. Server order is preserved.
7. Loading state is usable and duplicate opens/reads do not create conflicting local state.
8. Read failure is recoverable through retry.
9. Cache invalidation/reopen reloads authoritative state after a player revision change.
10. Returning to Camp is clean and preserves normal runtime state.
11. Compact, Standard, and Wide deterministic captures are legible; portrait behavior remains correct if affected.

#### Verification requirements

Add focused frontend/runtime tests for:
- Camp -> Codex navigation and return;
- lazy loading and cache reuse;
- authoritative order;
- owned/unowned rendering and body secrecy;
- selection/detail behavior;
- loading/error/retry behavior;
- revision invalidation and authoritative reload;
- repeated open/close lifecycle without duplicate listeners or stale state;
- responsive layout behavior where testable without brittle pixel assertions.

Run deterministic captures for representative Codex states at Compact landscape, 1600x900 Standard, and Wide landscape. Include portrait only if the shared orientation behavior is changed or affected.

Run applicable backend regression only if a shared contract is changed; Package 2 should normally consume the accepted Package 1 API unchanged.

Run full `npm run verify:package` before review.

#### Scope exclusions

Do not add in Package 2:
- dialogue definitions, selection, history, or routing;
- objective/bounty definitions or progress;
- new Codex reward/grant semantics;
- generic story flags or `has_seen_dialogue` state;
- direct Codex mutations;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- broad/global Camp redesign;
- final visual overhaul;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- Camp/scene/runtime inventory and reuse/drop decisions;
- Codex scene/surface structure and navigation lifecycle;
- authoritative lazy-cache loading/reconciliation behavior;
- owned/unowned secrecy and presentation behavior;
- loading/error/retry behavior;
- responsive layout/capture evidence;
- focused frontend/runtime test results;
- backend regression results if shared contracts changed;
- full package-gate results;
- confirmation that dialogue/objectives/M12+ were not introduced.

Leave Package 2 **In Progress** for review. Do not promote Package 3.

#### Package 2 implementation evidence (review candidate)

- Implementation commit: `890ae10` (Phaser Codex screen and Camp destination). Package remains **In Progress** for architectural review.
- Inventory/reuse: Camp's existing destination row and GameScene navigator own entry/return; the Package 1 `GameStore` Codex slice and strict `GET /api/v1/codex` parser own authority; neighboring progression screens supplied background/button conventions and read retry patterns; `RuntimeViewport` supplies Compact/Standard/Wide safe bounds and the existing portrait gate. The visual guide's parchment/green/brass palette is used at functional fidelity. The unrouted prototype Angular Codex page was removed after replacement; no second catalog or store was kept.
- Screen lifecycle: Camp's always available CODEX destination opens `CodexScreen` inside the persistent GameScene. Opening subscribes to the lazy shared cache and reads only when needed; Return/Escape destroy the screen subscription and restore Camp without restarting runtime. The screen keeps only selected entry and list/body page numbers locally.
- Authority/secrecy: the server ordered entries are rendered without client sorting or authored fallback. Discovered and undiscovered rows have distinct labels/colors. Detail body is rendered only for `owned` entries with an authoritative non-null body; undiscovered detail contains only a fixed discovery message. Long list titles are abbreviated in the row while the complete server title remains in detail; body paging retains every source character.
- Read states: first open shows Loading, failures show a retry action using `GameStore.retryCodex`, and stale revision changes reload immediately while the screen is open or on the next open. No client ownership or reward mutation was added.
- Focused Chrome suites: 59/59 tests passed (Codex screen, Camp navigation, GameStore). This includes lazy/fresh reuse, owned/unowned secrecy, selection, error/retry, revision invalidation, subscription cleanup, and responsive bounds/body pagination.
- Deterministic captures completed and visually reviewed: `artifacts/screenshots/m11-p2-codex-compact.png` (844x390 mobile Compact), `m11-p2-codex-standard.png` (1600x900 Standard), `m11-p2-codex-wide.png` (2560x1080 Wide), `m11-p2-codex-error.png` (read failure), and `m11-p2-camp-compact.png` (six Camp destinations). The shared portrait/orientation path was unchanged.
- Full `npm run verify:package`: **PASS**, 7m 5s. Backend 772 tests/5,373 assertions; frontend 508 tests; PHP front-controller syntax, content validation, context/docs lint, production build, bundle budget, and diff check passed. No backend contract changed; the backend aggregate was run by the full gate.
- Scope: no dialogue, objectives, Codex grants/rewards, M12, or other gameplay mechanics were introduced.
