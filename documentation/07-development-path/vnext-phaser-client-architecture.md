---
Title: "vNext Phaser Client Architecture"
Status: Accepted
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-api-contract-model.md
  - documentation/07-development-path/vnext-endpoint-inventory.md
  - documentation/07-development-path/vnext-authored-content-model.md
Category: 07-development-path
Tags:
  - vnext
  - frontend
  - phaser
  - angular
  - client-state
  - responsive
  - content-projection
---

# vNext Phaser Client Architecture

## Decision

vNext treats Phaser as the actual game client rather than as a bounded renderer embedded inside Angular gameplay pages.

Angular remains responsible for the public website, authentication/account shell, and the `/game` host route. Once the game is mounted, Phaser owns gameplay presentation, navigation, API interaction, client-side game state/cache, audio, assets, and input until the player leaves the game route.

Conceptually:

```text
Browser
|
+- Angular
|  +- public website
|  +- authentication
|  +- account/settings
|  `- /game
|     `- GameHostComponent
|
`- Phaser
   `- all gameplay presentation
```

The Angular host creates and destroys the Phaser runtime but does not orchestrate gameplay APIs or act as a state intermediary.

## Persistent Game Runtime

A single long-lived Phaser application runtime survives ordinary scene and screen transitions.

Conceptually the runtime owns:

- API client
- game state/cache
- client content registry
- navigation
- asset loading/cache
- audio director
- input/controller abstraction
- runtime configuration
- responsive/orientation management

Gameplay screens may come and go without destroying these application-level services.

## Scene and Screen Model

Do not translate every former Angular gameplay page into a Phaser Scene.

Scenes represent major rendering/lifecycle modes. Lighter screens/views handle navigation inside those modes.

vNext uses three major gameplay scenes:

```text
Boot / Loading

GameScene
- Camp
- Warband
- Unit Detail
- Dice Inventory
- Squads
- Academy
- Shop
- Wrong Machine
- Regions
- Codex
- Objectives

RunScene
- Run Map
- Dialogue
- Rest
- Chaos encounters
- Rewards
- Run Summary

BattleScene
- authoritative battle playback
```

`GameScene`, `RunScene`, and `BattleScene` are accepted architectural boundaries rather than provisional candidates.

`GameScene` owns ordinary between-run play and Camp-facing management experiences. Destinations such as Academy, Shop, Warband, and Unit Detail are screens/views inside that scene rather than independent Phaser scenes.

`RunScene` owns the active-run experience: map navigation, node presentation, run-specific dialogue/interactions, reward presentation, and run completion/failure presentation. Run screens share a scene because they operate against the same active-run context and benefit from shared map/run assets and transitions.

`BattleScene` owns battle playback because combat has a distinct actor, timeline, camera, animation, effects, and cleanup lifecycle. Battle is presentation of an already-authoritative server result and does not become the owner of gameplay resolution.

Boot and Loading remain lifecycle/setup scenes as needed, but they are not additional gameplay modes.

The persistent GameRuntime survives transitions between all three gameplay scenes. Scene changes must not recreate application-level API, state/cache, content, audio, input, or configuration services.

Typical transitions are:

```text
GameScene -> RunScene
- player successfully starts or resumes a run

RunScene -> BattleScene
- a resolved node produces battle playback

BattleScene -> RunScene
- playback completes or is recovered/skipped

RunScene -> GameScene
- run reaches a terminal state and the player returns to Camp
```

UI destinations should not become separate scenes merely because they were separate Angular pages. A new scene should be introduced only when a feature has a genuinely distinct rendering/lifecycle mode comparable to active-run play or battle playback.

## Phaser Navigation

Phaser owns logical gameplay navigation independently of Angular routing.

Logical destinations may include concepts such as:

- camp
- warband
- unit detail
- dice
- squads
- academy
- shop
- wrong machine
- regions
- codex
- objectives
- run map
- run node
- run summary

The client should maintain its own navigation history so Back/Escape/controller-back can return naturally through gameplay screens. Browser URL synchronization is optional and must not become the authority for in-game navigation.

Scene transitions and screen navigation are distinct concepts. Screen history applies within a scene; transitions between `GameScene`, `RunScene`, and `BattleScene` are driven by gameplay lifecycle rather than by treating scenes as ordinary navigation pages.

## Client State Is a Cache

The PHP/MySQL backend remains authoritative. Phaser's state store is explicitly a cache of the latest authoritative state received from the server.

Bootstrap provides enough state to enter Camp without eagerly loading the entire account collection. Lazy domain data is loaded only when needed.

Examples:

- bootstrap: user state, unlocks, active squad, active-run summary, player revision
- opening Warband: load unit summaries
- opening an individual unit: load unit detail
- opening Dice: load owned dice if not already loaded/fresh

Cached domains should support states equivalent to:

- not loaded
- loading
- fresh
- stale
- error

Durable player mutations are not considered committed locally until the authoritative server response succeeds. Temporary local drafts are appropriate for configuration editing such as squad or loadout changes.

Mutation responses update only affected cache slices. If a mutation affects a domain that has never been loaded, the client may mark that domain stale instead of loading it immediately.

## Player Revision

The runtime stores the latest server-provided `player_revision`.

It is initially a lightweight stale-cache signal rather than an optimistic-locking requirement. Unexpected revision changes may trigger bootstrap-level refresh and lazy-domain invalidation rather than complex client-side merging.

## Client Authored Content Boundary

The canonical authored JSON catalog is not shipped wholesale to the browser.

Anything delivered to the browser must be assumed inspectable by a player. Obfuscation, minification, or client-side encryption are not treated as secrecy controls.

vNext therefore uses an allowlisted client projection model:

```text
Canonical authored JSON
        |
        +--> Server ContentRegistry (complete content)
        |
        `--> Client projection build
               `--> Phaser ClientContentRegistry
```

The generated client projection is derived from the same canonical authored source. It is not a separately maintained gameplay catalog.

New authored fields are server-private by default unless explicitly included in a client projection.

### Exposure classes

Authored information should be treated as one of three conceptual exposure classes:

1. Public content
   - safe for the packaged client to possess
   - examples: display names, portraits/sprites, visible ability text, ordinary item descriptions, public presentation metadata

2. Player-visible content
   - safe only after this player is authorized to know it
   - examples: discovered Codex knowledge, unlocked/revealed content, currently valid dialogue options, current objectives, revealed encounter information
   - delivered or authorized through PHP as needed

3. Server-only content
   - never delivered until it resolves into an observable outcome
   - examples: reward probabilities, loot tables, hidden encounter weights, secret dialogue conditions, unrevealed encounter composition, random-selection pools, internal tuning rules

Explicit allowlisted projections are preferred over blacklist filtering. This ensures newly added fields cannot accidentally become client-visible merely because a developer forgot to mark them private.

## Player-Conditioned Content

Static public projection data may ship with and cache alongside the frontend.

Player-specific or secret content is surfaced through PHP only when the player is entitled to know it.

Examples:

- reward tables remain server-only; Phaser receives finalized awarded results
- hidden dialogue branching remains server-only; Phaser receives only currently available dialogue state/options
- unrevealed run-node details remain server-only until reveal/resolution
- Codex/discovery state is authorized per player rather than exposing the complete undiscovered catalog when secrecy matters

Server state and client representation are distinct contracts. The server may persist information that the API intentionally does not expose yet.

## Content Version Compatibility

The backend and packaged client should expose a shared content/build revision or deterministic content manifest hash.

During game bootstrap, Phaser compares its client content revision with the server revision. A mismatch should prevent normal gameplay from proceeding with incompatible stable IDs and should lead to a reload/update flow rather than silently operating on inconsistent catalogs.

## Repository Visibility Caveat

Browser content minimization protects against information leaking through client payloads and artifacts. It does not make data secret if the canonical authored files themselves are publicly readable in source control.

If future hidden mechanics require meaningful secrecy from motivated players, server-only canonical content must ultimately reside in a source/deployment location that is not publicly readable. This is a repository/deployment concern separate from the browser projection boundary.

## Responsive Rendering

Phaser should use a stable logical coordinate system with scaling and responsive layout rules rather than positioning gameplay purely in raw physical pixels.

This enables manual layout tuning against predictable logical dimensions while still adapting to different desktop, tablet, and mobile landscape sizes.

Exact logical base resolution and breakpoint values are implementation details to be selected during the first representative vNext screens.

## Mobile Orientation

Mobile gameplay is landscape-only.

Portrait is not a supported gameplay layout and should not trigger an alternate portrait UI design.

When a mobile device is in portrait orientation:

- normal game interaction is suspended or blocked
- gameplay is obscured by a full-screen rotate-device presentation
- the active game state remains intact
- rotating back to landscape causes Phaser to resize/reflow and gameplay resumes from the same state

The orientation gate belongs at the game host/runtime level rather than in individual gameplay scenes.

Browser/native orientation locking may be attempted as progressive enhancement where supported, but correct behavior must not depend on the browser successfully forcing orientation.

## Asset Loading

Do not preload the complete game's asset catalog before Camp can render.

Assets should be layered conceptually into:

- core/common assets
- domain/screen assets
- region assets
- battle/unit/enemy assets

Phaser may retain loaded assets in its cache as appropriate. Region and battle bundles should be loaded only when required, which also preserves a path toward future mobile memory constraints.

## Audio

Audio is application-level runtime infrastructure rather than scene-local state.

Screens and scenes express desired musical/ambient context while a persistent AudioDirector manages playback, transitions, volume/settings, fades, and continuity. Ordinary screen changes inside a location should not unnecessarily restart application audio.

## Battle Playback Boundary

Battle playback consumes an already-authoritative battle result/playback model.

Battle presentation does not award XP, apply rewards, determine damage, or mutate authoritative gameplay state. Those changes are resolved by PHP before or as part of the API response that launches playback.

When playback finishes, Phaser returns to the appropriate run presentation using state already updated from the authoritative response.

## Visual Testing

Deterministic Phaser screen/scene capture remains an important development capability and should be expanded as Phaser becomes the full game client.

Representative states such as Camp, Academy, Warband, Unit Detail, Run Map, Battle, and Reward presentation should be renderable from deterministic fixtures or debug state without manually playing through the game.

This supports screenshot regression, UX review, art iteration, and future automated development workflows.

## Angular/Phaser Boundary Rule

Use this rule for future feature placement:

> If a feature is part of playing Dice Goblins, it belongs inside Phaser. If it concerns the website, authentication/account management, or platform shell, it belongs in Angular.

Angular gameplay services and page-oriented gameplay routing should not remain as a parallel authority after vNext migration.

## Documentation Lifecycle

This decision document guides the vNext implementation phase.

After the architecture is implemented and stabilized, its durable concepts must be reconciled into canonical technical/frontend documentation. Future feature work should be able to determine from canonical documentation:

- whether a feature belongs in Angular or Phaser
- whether a destination should be a scene or a lighter screen/view
- how gameplay navigation works
- how client cache invalidation and authoritative state work
- what authored content is allowed in the browser
- how player-conditioned content is exposed
- how responsive scaling and landscape-only mobile behavior work
- where API, audio, assets, and battle playback responsibilities belong

The implementation is not considered documentation-complete merely because this transitional vNext decision file exists.
