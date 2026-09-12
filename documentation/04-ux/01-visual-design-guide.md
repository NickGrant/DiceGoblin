---
Title: "Dice Goblins - Visual Style Guide"
Status: Canonical
Last Updated: 2026-09-12
Owner: Product + UX
Depends On:
  - documentation/07-development-path/vnext-phaser-client-architecture.md
Category: 04-ux
Tags: [ux, visual-design]
---

# Dice Goblins - Visual Style Guide

## Direction
Dice Goblins uses a bright, saturated fantasy-adventure language with cartoon readability, JRPG framing, and modern roguelite clarity. It should feel cheerful, adventurous, mischievous, tactile, and handcrafted while preserving the sense that goblins are dangerous troublemakers.

## Recurring Materials
Use painted wood, parchment, stitched cloth, rope, brass, illustrated frames, patchwork repairs, and playful wear. Texture should feel painterly rather than photoreal or sterile. Primary controls and tactical information remain crisp.

## Palette
- Parchment Cream `#F5E8C8`
- Adventure Green `#8DB341`
- Banner Blue `#5C8FD8`
- Sun Gold `#F2C14E`
- Quest Red `#D65A43`
- Wood Brown `#8A5A34`
- Brass Trim `#C9972B`
- Stone Gray `#7A746B`
- Cloud Glass `#DCEEFF`
- Shadow Ink `#3A2A1A`

## Typography and Components
Major labels may use expressive fantasy-adventure lettering; dense information uses a highly readable companion face. Controls should feel tactile and game-like, not like generic SaaS cards. Panels, tabs, slots, badges, and navigation should read as parts of one illustrated game UI family.

## Art and Sprite Direction
Sprites use simplified hand-painted fantasy forms, chunky JRPG proportions, crisp dark outlines, and warm cel-shaded/painterly color. Strong silhouettes and readability matter more than detail.

## Negative Constraints
Avoid grim propaganda/militarized framing, muddy default palettes, sterile app minimalism, sci-fi holograms/neon tech, photorealism, or childlike softness that removes goblin danger.

## vNext Layout
The visual system is implemented inside Phaser gameplay using the accepted 1600x900 reference design space, landscape-only mobile policy, and responsive layout regions. Do not revive prototype Angular page chrome merely to preserve an old screenshot.

Reuse established runtime art families where they fit this guide; new vNext screens may recompose them to feel like a cohesive game rather than web pages.

## vNext Implementation Posture
The current vNext Phaser presentation is a functional implementation baseline, not the final visual target. Milestone 1 UAT found no functional blocker but confirmed that the game will need a substantial cross-cutting visual/UI overhaul before final presentation quality is reached.

Do not perform that overhaul one isolated screen at a time while the main gameplay surfaces are still being established. During functional milestones, new screens should prioritize:
- clear information hierarchy and interactions;
- responsive correctness and safe-region behavior;
- basic consistency with this guide and neighboring Phaser screens;
- reusable presentation boundaries where they arise naturally;
- avoiding throwaway visual complexity that will be replaced in the later visual pass.

Once enough of Camp, Warband, run navigation, battle presentation, rewards, economy, progression, and related gameplay surfaces exist, revisit the game UI as one system. That larger pass should establish the final shared component language, navigation treatment, panel/resource treatments, typography, motion, transitions, spacing, and art integration across the game.

Deferring final visual fidelity does not permit unusable placeholder UI. Functional milestones should still remain readable, game-like, responsive, and coherent enough for continued UAT and feature development.
