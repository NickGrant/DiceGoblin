# Dice Goblins - Visual Style Guide
----

Status: active
Last Updated: 2026-03-08
Owner: UX + Art + Frontend
Depends On: `documentation/03-ux/00-ux-and-debug-scope.md`, `documentation/03-ux/08-page-layout-zones.md`, `frontend/src/scenes/`

## Purpose
- Define the single canonical visual language for Dice Goblins UI and UI-supporting art.
- Keep UI implementation, asset production, and review criteria aligned.

## Canonical Style Direction
Dice Goblins uses a harsh handmade propaganda diorama aesthetic: a militarized paper-craft interface for goblin bureaucracy.

The target mood is grim, tactical, improvised, and slightly absurd, but not childish. Surfaces should feel physically assembled from distressed analog materials while maintaining strict hierarchy and readability.

## Visual Tone Keywords
- industrial diorama
- wartime paper-craft
- propaganda bulletin board
- goblin logistics office
- fascist cardboard bureaucracy
- distressed handmade UI
- tactical and oppressive
- analog military admin
- crude but functional
- layered 2.5D cut-paper interface

## Materials and Surface Rules
Required physical cues:
- visible corrugated cardboard fluting on exposed edges
- thick cut paper layers with slight misalignment
- frayed masking tape, yellowed and wrinkled
- tarnished brass fasteners or rivet-like corner pins
- rubbed ink, stencil overspray, stamp bleed, dry-brush crayon fill
- subtle grime, fingerprints, adhesive residue, crushed corners, scuffed fibers

Surface constraints:
- Wear must feel tactile and physical, never digital.
- Distress should concentrate on edges/corners, not over primary labels and controls.
- Avoid clean, sterile, or glossy surfaces.

## Locked Color Palette
Use these as the canonical UI palette tokens.

Primary tokens:
- Stark Cream: `#F3EFE0`
- Revolutionary Red: `#B91C1C`
- Cold Slate: `#4F5A65`
- Dirty Teal: `#006F7A`
- Deep Charcoal: `#23272A`

Optional muted military accents:
- Drab Olive: `#5E6B3C`
- Dirty Brass: `#8A6D3B`
- Smoked Gray: `#6B7075`

Color usage rules:
- Prioritize cream, red, slate/teal, and charcoal.
- Keep saturation restrained and tactical.
- Red is the strongest state and action emphasis color.
- Avoid playful/high-chroma color mixes.

## Typography System
Primary type recommendations:
- Display / headers / command labels: `Big Shoulders Stencil Text` (fallback: `Saira Stencil One`)
- Body / secondary / utility copy: `IBM Plex Sans Condensed` (fallback: `Roboto Condensed`)

Typography behavior:
- Use uppercase for command labels and major headings.
- Apply moderate tracking for stamped/official tone.
- Allow subtle print imperfection: slight registration offset, minor ink spread, worn edges.
- Avoid friendly handwritten, bubbly, or storybook typography.

## UI Language and Components
All components should read as modular bureaucratic assets.

Core component families:
- registry panels and sub-panels
- list rows and sortable registry strips
- command buttons and destructive/confirm stamps
- segmented utilitarian gauges
- status banners and report strips
- tactical node/map pieces

Interaction language:
- confirm and destructive actions should feel like stamped orders
- buttons should feel mechanically decisive (pressed/slammed)
- status changes should feel procedural and official

## Negative Constraints
Do not introduce:
- cute characters, cartoon faces, mascots
- playful children's-book energy
- glossy UI, neon glow, sci-fi holograms
- smooth mobile-app gradients
- polished fantasy ornamentation
- soft toy-like proportions
- scrapbook whimsy
- photorealistic 3D rendered look

## Scene/Application Guidance
- Preserve strong hierarchy and practical readability over decoration.
- Keep elements visually modular and production-asset ready.
- Favor tactical severity over whimsical polish.

## Versioning
- This file is the single canonical style source.
- Replace superseded visual direction text in dependent docs instead of creating parallel style variants.
