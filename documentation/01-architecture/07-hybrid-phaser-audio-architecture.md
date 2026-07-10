# Hybrid Angular, Phaser, and Audio Architecture

Status: active  
Last Updated: 2026-07-09  
Owner: Frontend  
Depends On: `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`

## Purpose

This document defines the target hybrid client architecture for Dice Goblins.

Dice Goblins remains an Angular application for routing, application state, authenticated shell flow, management UI, and API orchestration. Phaser is reintroduced only for bounded scene rendering where canvas meaningfully improves the experience, and application audio is owned by a dedicated audio service rather than by Angular pages or Phaser scenes directly.

The core rule is:

> Angular decides what is happening. Phaser shows it happening. AudioDirector makes it sound like it is happening.

## Ownership Boundary

### Angular owns

- login, auth flow, route guards, and shell bootstrap
- session, profile, and API orchestration
- page layout, responsive structure, HUD, drawers, dialogs, and forms
- warband, units, dice, shop, academy, guide, run summary, and debug surfaces
- run creation, abandon, exit, rest actions, node resolution requests, and reward claim actions
- reduced-motion, accessibility, and focus behavior
- audio settings UI and shell-level sound controls

### Phaser owns

- bounded canvas scenes only
- battle playback first
- optional future run-map rendering if SVG stops being sufficient
- scene-local animation timelines, particles, and camera presentation
- deterministic fixture-driven screenshot surfaces when canvas capture is needed

### AudioDirector owns

- music, ambience, UI sounds, combat sounds, and reward sounds
- audio unlock after user gesture
- mute and volume settings
- route-based music selection
- page visibility handling
- shared semantic sound intents from Angular and Phaser

### Backend owns

- authoritative session, profile, progression, run, combat, rewards, shop, academy, and debug state

Phaser must not call the backend directly and must not become a second gameplay engine.

## Target Stack

### Keep

- Angular 20
- TypeScript
- RxJS
- Bootstrap utilities
- PHP backend
- MySQL
- existing Angular unit test stack

### Add

- Howler.js for application-level audio
- Phaser 3 only when the battle playback host is implemented
- Playwright scene and viewport coverage when Phaser playback lands

## Route Recommendation

Angular remains the owner of all existing routes.

Phaser is reserved for:

- `/run/node/:nodeId` battle playback host first
- optional future `/run/map` canvas migration if animation and camera needs justify it

Everything else should remain DOM-driven Angular UI.

## Integration Model

Recommended Angular-owned integration points:

- `PhaserCanvasHostComponent`
- `CombatPlaybackHostComponent`
- `PhaserGameFactoryService`
- `PhaserEventBridgeService`
- `BattlePlaybackBridgeService`
- `AudioDirectorService`

Phaser instances must be created by Angular hosts, destroyed on Angular teardown, and fed immutable input snapshots from Angular services or adapters.

## Audio Architecture

Audio is application-level infrastructure, not scene-local infrastructure.

Required rules:

- use Howler.js as the browser audio runtime
- keep global audio outside Phaser
- use a central audio manifest
- drive sounds through semantic keys such as `ui.click`, `music.home`, and `music.battle.normal`
- keep route-driven music selection in Angular
- treat browser audio unlock as an intentional product flow

The initial implementation should deliver:

- shell-level audio initialization
- local mute and volume preference storage
- audio unlock handling after meaningful user interaction
- route-based music intents
- UI click and confirmation intent plumbing

## Mobile Rule

Angular owns layout on mobile. Phaser draws only inside a bounded host region.

Do not let Phaser take over the full screen while Angular overlays float on top by default. Keep critical controls in Angular, disable Phaser input when Angular overlays are active, and size canvases from host containers rather than from raw viewport dimensions.

## Migration Order

### Phase 0

- add this architecture decision
- update Angular and component inventory docs to point to it
- add feature flags for future Phaser battle playback and app-level audio

### Phase 1

- add `AudioDirectorService`
- add the audio manifest
- add shell-level sound unlock and mute controls
- add route-driven music intents

### Phase 2

- extract a renderer-facing battle playback snapshot adapter from the run-node route
- keep Angular fallback battle presentation intact

### Phase 3

- add Phaser dependency
- mount Phaser only through Angular hosts
- implement minimal battle playback rendering

## Initial Definition of Done

The first successful hybrid checkpoint is reached when:

- Angular still owns routes, API orchestration, run mutations, and reward claim
- audio is managed by a shared `AudioDirectorService`
- route-level music intents are available
- shell-level sound unlock and mute behavior exist
- Phaser remains optional and feature-flagged for future battle playback
- the ownership boundary is explicit in docs and code structure
