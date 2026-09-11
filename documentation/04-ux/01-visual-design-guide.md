---
Title: "Dice Goblins - Visual Style Guide"
Status: Canonical
Last Updated: 2026-09-10
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
