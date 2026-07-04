# Dice Goblins - Visual Style Guide
----

Status: active
Last Updated: 2026-07-04
Owner: UX + Art + Frontend
Depends On: `documentation/03-ux/00-ux-and-debug-scope.md`, `documentation/03-ux/08-page-layout-zones.md`, `frontend/src/scenes/`

## Purpose
- Define the single canonical visual language for Dice Goblins UI and UI-supporting art.
- Keep UI implementation, asset production, and review criteria aligned.

## Canonical Style Direction
Dice Goblins uses a bright, saturated fantasy-adventure UI language with cartoon readability, JRPG framing, and modern roguelite clarity.

The target mood is cheerful, adventurous, mischievous, and tactile on the surface, with a subtle undercurrent that the goblins are trouble. The world should feel colorful and inviting even when the player is participating in something dangerous.

## Visual Tone Keywords
- bright fantasy adventure
- cartoon tactical
- handcrafted JRPG
- modern roguelite clarity
- goblin patchwork charm
- mischievous but dangerous
- saturated and readable
- playful materials
- heroic menu framing
- chaos peeking through polish

## Materials and Surface Rules
Required physical cues:
- painted wood planks and signboard surfaces
- parchment, stitched cloth, rope, brass corners, and banner trims
- crisp illustrated frames with hand-painted highlights and shadows
- patchwork repairs and playful wear that suggest goblin reuse rather than military decay
- occasional scuffs, drips, cracks, and stitched seams used as accent texture
- soft sky, map, and adventure-book textures where large surfaces need atmosphere

Surface constraints:
- Texture should feel handcrafted and painterly, never photoreal or sterile.
- Wear should support personality and patchwork charm, not communicate collapse or oppression.
- Primary labels, controls, and status signals must stay crisp and readable.
- Avoid flat mobile-app minimalism and avoid grim, dirty, low-energy surfaces.

## Locked Color Palette
Use these as the canonical UI palette tokens.

Primary tokens:
- Parchment Cream: `#F5E8C8`
- Adventure Green: `#8DB341`
- Banner Blue: `#5C8FD8`
- Sun Gold: `#F2C14E`
- Quest Red: `#D65A43`

Supporting accents:
- Wood Brown: `#8A5A34`
- Brass Trim: `#C9972B`
- Stone Gray: `#7A746B`
- Cloud Glass: `#DCEEFF`
- Shadow Ink: `#3A2A1A`

Color usage rules:
- Use warm cream and saturated adventure colors as the main identity.
- Green, blue, gold, and red can all appear prominently in the same screen when hierarchy is still clear.
- Brightness is intentional; avoid muddy desaturation as the default.
- Danger, corruption, and chaos accents can become darker or sharper, but the base world should remain colorful.

## Typography System
Primary type recommendations:
- Display / headers / major navigation: expressive fantasy-adventure lettering with a playful JRPG silhouette
- Body / secondary / utility copy: clean sans or rounded serif companion that stays readable at small sizes

Typography behavior:
- Headers should feel bold, adventurous, and stylized without becoming hard to scan.
- Body copy must remain clean and game-legible on desktop and mobile.
- Decorative fonts belong in headers, banners, and key labels, not dense information panels.
- Avoid bureaucratic stencil language, oppressive command-strip typography, and militarized UI labeling.

## UI Language and Components
All components should read as playful fantasy-adventure tools built from durable goblin-era materials and modern game readability.

Core component families:
- framed parchment panels and wood-backed information mats
- banner tabs, adventure cards, and brass-cornered slots
- chunky buttons with clear game-state color coding
- collectible-looking badges, chips, and tags
- map and encounter panels that feel like readable quest UI
- tactical widgets that stay fun to look at without hiding rules clarity

Interaction language:
- confirm actions should feel satisfying and game-like, not bureaucratic
- buttons should feel tactile, bright, and immediate
- status changes should still communicate tactical stakes through strong framing and color
- locked or forbidden states can feel stern, but not authoritarian by default

## Shared Shell Treatment
- Global page backgrounds should draw from parchment, painted wood, sky-glass, map, and soft pattern families.
- Major section headers should feel like banners, signs, or framed quest boards.
- The authenticated shell should feel like a persistent game interface, not a website dashboard.
- Navigation and resource surfaces should resemble adventure HUD elements and collectible UI pieces.
- Reuse the bright UI kit language from `raw-assets/ui-sheets/` as the canonical asset family.

## Iconography Direction
- Icons should read cleanly at small sizes with bold fantasy-game silhouettes.
- Keep forms simple, friendly, and readable first.
- Encounter node icons should remain visually consistent as one family across combat, loot, rest, boss, and exit states.
- Resource icon families should remain internally consistent across sizes and HUD contexts.
- Chaos-leaning motifs can add teeth, drips, scratches, and asymmetry as secondary accents.

## Asset Pipeline Rules
- Source art lane: `raw-assets/`
- Runtime UI lane: `frontend/public/assets/ui/`
- Runtime logic should bind only to runtime asset paths and registered keys, not raw source paths.
- Reuse shared textures, frames, and icon families before introducing one-off motif assets.

## Negative Constraints
Do not introduce:
- grim propaganda-board presentation
- fascist or militarized bureaucratic motifs
- muddy low-saturation palettes as the default screen mood
- sterile SaaS-style cards and generic app minimalism
- sci-fi holograms or neon-tech interfaces
- photorealistic rendering or hyper-detailed realism
- childlike nursery-book softness that removes goblin danger
- tonal drift that makes goblins read as harmless mascots

## Practical Do / Don't
### Do
- Keep silhouettes strong, readable, and collectible-looking.
- Prioritize tactical readability over decorative excess.
- Let surfaces feel bright, painted, and intentionally handcrafted.
- Use patchwork, rope, cloth, brass, and painted wood as recurring material anchors.
- Preserve a tension between cheerful presentation and dangerous goblin intent.

### Don't
- Mix unrelated visual themes between screens.
- Over-texture controls until labels become hard to read.
- Slide into dark grimdark framing just to communicate stakes.
- Make goblins read as purely cuddly mascots with no edge.
- Depend on ad hoc assets when an existing shared family can cover the need.

## Scene/Application Guidance
- Preserve strong hierarchy and practical readability over decoration.
- Keep elements visually modular and production-asset ready.
- Let traversal, collection, and squad-building screens feel adventurous and inviting.
- Let combat-result and danger states become sharper without abandoning the bright world baseline.

## Versioning
- This file is the single canonical style source.
- Replace superseded visual direction text in dependent docs instead of creating parallel style variants.
