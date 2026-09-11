# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Render minimal authoritative Camp in `GameScene`

**Status:** Open
**Priority:** High

#### Problem
Replace the successful-startup `GameScene` placeholder with the first real vNext gameplay screen: a minimal Phaser Camp rendered entirely from the already-compatible runtime `GameStore` and client-safe content boundary. This package proves that authoritative bootstrap state can drive a game-like Phaser screen without Angular gameplay UI, prototype `/profile` state, new backend queries, or premature Warband/run systems.

#### Required Context
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — Scene and Screen Model, Client State Is a Cache, Angular/Phaser Boundary Rule
- `documentation/04-ux/01-visual-design-guide.md` — canonical visual direction and negative constraints
- `documentation/07-development-path/vnext-game-overhaul.md` — Milestone 1 walking-skeleton outcome
- `documentation/07-development-path/vnext-api-contract-model.md` — approved bootstrap/player revision semantics
- Current `frontend/src/app/game/` runtime/startup/store/scenes/tests and relevant existing visual assets
- `frontend/src/app/pages/home-page/` only as prototype migration evidence; it is not the vNext Camp specification

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- On compatible successful startup, `GameScene` renders a real Camp screen/view instead of the current lifecycle placeholder. Camp remains a screen/view owned by `GameScene`; do not create a `CampScene`.
- Establish a small screen/view composition boundary inside `GameScene` appropriate for future GameScene destinations. Camp rendering/lifecycle should not turn `GameScene` into a monolithic implementation, but do not build a generalized UI/navigation framework before it is needed.
- Camp reads authoritative state from the existing runtime-owned `GameStore` populated by bootstrap. It must not fetch `/profile`, refetch bootstrap, call Angular services, create fallback gameplay state, or duplicate balances in local constants.
- Render the Milestone 1 player-facing state that actually exists: account/display identity, Teeth, Raw Chaos, and Energy current/normal maximum. Preserve legitimate Energy overcap values exactly (for example `57 / 50`); do not clamp presentation to the normal maximum.
- Use only bootstrap/content state that is actually authorized and available. `player_revision`, CSRF metadata, server time, and content revision may remain runtime/internal concerns rather than being exposed as ordinary Camp UI.
- Do not invent squad, unit, dice, run, objective, Codex, Shop, Academy, Wrong Machine, reward, unlock, region-availability, or progression state to make the screen appear fuller. Bootstrap's null active squad/run and empty progression are not permission to build placeholder versions of those systems.
- Do not infer that a region is unlocked/startable merely because its public definition exists in `ClientContentRegistry`. Region/run availability belongs to later authoritative progression/run work.
- Camp presentation should follow the canonical visual guide: bright/saturated fantasy-adventure, cartoon/JRPG readability, tactile illustrated-game framing, and crisp status information. Avoid generic SaaS/dashboard cards and do not port prototype Angular page chrome one-for-one.
- Prefer reuse of suitable existing game assets where they fit the accepted visual direction. Do not require new authored gameplay content or expose server-private content for this screen.
- Camp must remain fully Phaser-owned. Angular `/game` host behavior stays mount/destroy only; do not reintroduce Angular command controls, status cards, router-driven gameplay UI, or Angular gameplay state into the game surface.
- Preserve the approved startup gate. `GameScene`/Camp must remain unreachable until client content is valid, bootstrap is valid, and content revisions match. Camp must consume the already-hydrated runtime state rather than initiating startup itself.
- Preserve application-lifetime `GameStore`, `ClientContentRegistry`, and API client across Camp creation/recreation within the mounted runtime. Rendering Camp must not trigger additional client-content or bootstrap requests.
- Do not implement navigation to Warband or other future GameScene destinations in this package unless a tiny inert screen-boundary stub is required to prove Camp composition. Do not route back into prototype Angular gameplay pages from Phaser.
- Do not add backend endpoints, schema, authored balance/content, player provisioning, or gameplay mutations for Camp. If Camp appears to need information not present in the approved bootstrap, keep that feature out of this minimal package rather than expanding the contract opportunistically.
- Do not implement run creation/region selection, squad management, units/dice, Shop, Academy, Wrong Machine, Codex/objectives, battle/run flow, final audio migration, asset-bundle architecture, responsive Compact/Standard/Wide modes, safe insets, or the mobile portrait gate in this package.
- Use enough logical layout structure that the screen is composed intentionally in Phaser, but defer final 1600x900/Compact/Wide responsive behavior and mobile orientation handling to the next package. Avoid hard-coding per-device/resolution layouts that conflict with the accepted responsive architecture.
- Add focused deterministic frontend coverage proving Camp derives displayed identity/currency/Energy from `GameStore`, preserves overcap Energy, does not render before ready startup, does not refetch startup resources, and does not depend on Angular/prototype gameplay services.

#### Completion
Run applicable frontend/content/context/build gates from `agent/QUALITY_GATES.md`, including frontend tests and production build. Leave this package active for architectural/UX review; do not promote or begin responsive landscape host behavior in the same change.
