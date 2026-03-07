# Dice Goblins — Visual Style Guide (v1)

Status: active  
Last Updated: 2026-03-02  
Owner: UX  
Depends On: `documentation/03-ux/00-ux-and-debug-scope.md`, `frontend/src/scenes/`


This document defines the cohesive visual language for **Dice Goblins** across UI, icons, and key illustrative elements. It is intended to be actionable for implementation (Phaser + HTML shell) and consistent enough for future asset production.

---

## 1) Brand and tone

### Brand promise
**“Devious + whimsical.”** The game should *read* approachable at first glance (storybook, paper-craft warmth), but quickly reveal a more mature edge through details: improvised weapons, hazard signage, and “questionable management.”

### Core world metaphor
A goblin-run operation built from **scrap, posters, and field manuals**:
- propaganda placards
- warning labels
- stitched repairs
- rivets and tape
- reclaimed materials

### Primary aesthetic pillars
1. **Salvaged out of a scrap heap**
   - Riveted metal, scuffed paint, patched cloth, frayed edges, cardboard and plywood, taped repairs.
2. **Vicious but adorable little pests**
   - Compact bodies, oversized hands/heads, mischievous expressions, “ready to pounce.”
3. **Propaganda / hazard UI**
   - Diegetic signage, stencils, chevrons, icon tiles, worn paper and print textures.

---

## 2) Art direction: what “on-style” looks like

### “On-style” checklist
- Feels like **printed signage** over a **dirty workshop wall**
- Uses **stencil + label** language rather than fantasy parchment frames
- Has **clear hierarchy** with bold shapes and minimal clutter
- Uses **warm ink browns** (not pure black) with restrained accent colors
- Adds “mature reveal” through **props and pictograms**, not gore

### “Off-style” indicators
- High-fantasy postcard UI (clean parchment frames + ornate fantasy filigree)
- Neon UI colors or glossy sci-fi gradients
- Photoreal textures without “printed” cues (ink bleed, paper fiber, scuffs)
- Uncontrolled distress: everything equally noisy and unreadable

---

## 3) UI direction (final decision)

### Chosen UI style: **Push 3 — Propaganda poster / hazard placard**
UI should appear **constructed and maintained by goblins**:
- torn posters pinned to walls
- riveted placards
- hazard chevrons as dividers
- stenciled icon signage (pictograms)
- tape patches and corner repairs

#### Explicit exclusions (baseline)
- Do **not** use “APPROVED / RESTRICTED” stamps as default decoration.
  - Stamps may exist later as a *situational* effect, not as a constant motif.

---

## 4) Color system

This palette is designed for readability on textured paper/metal, while maintaining the “ink on poster” feel.

### Base neutrals
- **Deep background brown (primary base):** `#1A0E0A`
- **Ink (primary text/lines):** `#2B1B14`
- **Ink (body/secondary):** `#3B2A20`

### Accents: Yellow (navigation/energy)
- **Yellow base (default accent/path):** `#D1A84A`
- **Yellow bright (hover/selected):** `#E7C35A`
- **Yellow dark (outline/pressed):** `#9B6F2C`

### Accents: Red (danger/hostile/critical)
- **Red base (warning/danger):** `#8B2F2B`
- **Red bright (critical highlight):** `#B13A2F`
- **Red dark (blocked/disabled):** `#5E1F1B`

### Usage rules
- Yellow is the **default navigation energy** (paths, selection emphasis).
- Red indicates **danger/hostile/blocked** conditions.
- Maintain **one dominant accent per screen** (yellow OR red). Use the other sparingly.

---

## 5) Typography

### Chosen fonts
- **Display / headings / button labels:** **Saira Stencil One**
- **Body / UI copy:** **Roboto Condensed**

### Typography behaviors
- Buttons and major headings are typically **ALL CAPS**.
- Apply **moderate tracking** to sell “printed label”:
  - Buttons: +0.04em to +0.08em equivalent
  - Headings: slightly more is acceptable, but avoid excessive spacing

### Color use
- Use **ink browns** (`#2B1B14`, `#3B2A20`) instead of black.
- Reserve pure black for rare cases only (e.g., tiny stencils that must pop).

---

## 6) UI components and primitives

These are the building blocks for every screen.

### 6.1 Poster Panel (primary container)
**Look:** torn paper silhouette, subtle grime, optional pinholes/rivets  
**Use:** main content groupings (Home panels, Region cards, dialog panels)

Rules:
- Distress lives mostly on edges and corners.
- Keep the inner content area cleaner for readability.
- Prefer a strong header band with chevrons or label bars.

### 6.2 Placard Buttons
**Look:** weathered sign plates, printed/stenciled labels, scuffed paint  
**Use:** CTAs and navigational actions

Rules:
- Maintain consistent silhouette family (rectangular plates with slightly imperfect edges).
- Avoid overly ornate frames; it’s signage, not jewelry.

### 6.3 Icon Pictogram Tiles
**Look:** thick bordered signage tiles with stenciled pictograms  
**Use:** status effects, small navigation, warnings, resource icons

Rules:
- Pictograms should read at small sizes; use clear negative space cutouts.
- Avoid detailed line illustration inside icons—keep them bold and “stencil.”

### 6.4 Hazard Divider
**Look:** yellow/black chevrons, scuffed  
**Use:** separators, section headers, “route” emphasis

Rules:
- Use as a structural accent, not a decoration everywhere.

### 6.5 Manifest Strip (footer bar)
**Look:** shipping label / packing slip, segmented fields  
**Use:** resources, user identity, run meta

Rules:
- Keep it flatter and more utilitarian than the main poster panels.

---

## 7) Interaction states (implementation-friendly)

### Recommended state strategy: overlay-driven
Instead of authoring many near-identical textures per state, use:
- base texture
- subtle highlight overlay for hover
- pressed offset + darken/tint
- disabled tint + tape overlay + reduced alpha

This keeps assets consistent and reduces texture churn.

### Suggested state mapping
- **Default:** base placard/panel
- **Hover:** base + soft highlight overlay (low opacity)
- **Pressed:** translate down 1–2px + slight darken
- **Disabled:** desaturate/tint toward dark brown + tape overlay + alpha 0.6–0.75

---

## 8) Screen templates (MVP alignment)

### 8.1 HomeScreen / HomeScene (gateway screen)
**Purpose:** authenticated launchpad to start the run loop

Layout pattern:
- Primary poster panel + big CTA: **BEGIN RUN**
- Secondary actions: **WARBAND**, **DICE INVENTORY** (enabled or disabled)
- Bottom manifest strip: identity + resources

### 8.2 Region Select (MVP scope)
**Requirement:** exactly **two** starting regions, one per starting biome:
- **Swamp**
- **Mountains**

Style requirements:
- Region choices should be “posted notices” / “selection placards,” not fantasy postcards.
- Use yellow accents for selection/hover; reserve red for “danger” variants later.

---

## 9) Iconography (MVP and beyond)

### MVP status icons
- **Poison**
- **Bolstered**
- **Sleep**

Icon style rules:
- Stenciled pictograms (bold silhouette, deliberate cutouts)
- Designed to work inside square signage tiles
- Consistent stroke/edge weight across the set

---

## 10) Dice visual system (rarity via material)

Dice should reinforce progression and loot excitement. Materials must be obvious at a glance.

### Rarity → material mapping
- **Common:** cardboard
- **Uncommon:** wood
- **Rare:** bone
- **Epic:** metal
- **Legendary:** gemstone

### Material readability requirements
- **Cardboard:** visible corrugation, frayed edges, tape repairs; markings look like marker/paint
- **Wood:** clear grain, nicks, slight warping; markings look stenciled or burned/painted
- **Bone:** porous ivory texture, scratches; markings inked or etched
- **Metal:** rivets/weld seams, rust/paint chips; markings stamped/engraved
- **Gemstone:** faceted, shiny; markings etched/filled or glowing (sparingly)

### Style note
For visualization and item art, a **cartoonish / paper-craft** approach is acceptable and consistent with the “devious whimsical” goal.

---

## 11) Header logo (HTML above canvas)

### Required text
- Main heading: **Dice Goblins**
- Subheader: **Tactics. Loot. Questionable Management**

### Style direction
- Storybook cutout title + propaganda tagline band
- Must be **detailed, stylistic**, and delivered as a **transparent PNG**
- Designed to sit over the HTML background (not inside the Phaser canvas)

---

## 12) Asset specs and deliverable standards

### Backgrounds (back layer)
- Deliver as **separate images**, not triptychs.
- Target aspect ratio: **16:9**
- Reference production size: **1920×1080**
- Designed to sit behind all UI: workshop walls, corrugated metal, bulletin boards, grime

### Foreground UI assets
- Deliver as **transparent PNG components**:
  - poster panel bases
  - placards
  - hazard dividers
  - icon tile frames
  - tape/rivets/patches
  - manifest strip
- Prefer runtime text using chosen fonts rather than baking text into textures.

---

## 13) Practical Phaser text presets (reference)

Use ink browns and subtle “print offset” shadows rather than black and heavy glow.
(Keep these centralized as exports/constants so the UI stays consistent.)

- Button text: **Saira Stencil One**, ink, light stroke if needed on noisy textures
- Header text: **Saira Stencil One**, larger, slightly more stroke/shadow
- Body text: **Roboto Condensed**, softer ink, minimal stroke

---

## 14) Do / Don’t summary

### Do
- Use signage language: placards, labels, chevrons, stencils
- Keep hierarchy bold and readable
- Use ink browns, not black
- Use yellow for navigation emphasis, red for danger
- Let “mature” come through props and iconography

### Don’t
- Drift into ornate fantasy UI frames
- Over-distress everything equally
- Use neon primaries or sci-fi gradients
- Rely on stamps as the default motif

---

## 15) Versioning
- **v1** reflects decisions made in the current thread.
- Update this document when introducing new biomes, new icon categories, or revised screen flow.
# Dice Goblins — Visual Style Guide (v2 - Constructivist Pivot)

This document replaces all previous style definitions and visual language, moving away from a "junkyard/scrap" aesthetic toward a disciplined **"Medieval-Modern Constructivism"** aesthetic that reflects **"Totalitarian Utility"** and a world of **"The Industrial Mandate"**.

---

## 1) Brand and tone

### Brand promise
**“Devious + whimsical, enforced.”** The approachability remains, but it is now structured by Constructivist rigidity—bold geometric shapes, heavy rectangles, 45-degree angles, and perfect circles stamped by an caring hydraulic press.

### Core world metaphor
A goblin-run operation defined by **"Bureaucratic Over-Engineering"**:
- totalitarian utility
- registry stamps
- mandated order
- state-run, rigid, over-engineered
- print errors, ink-bleed, and registration misalignment

### Primary aesthetic pillars (Reworked)
1. **Totalitarian Utility**
   - Stamped tin, painted wood, heavy paper, replacing "scraps and rivets". Looks over-engineered.
2. **"Ordered Chaos" Pests**
   - The "Goblins" are **"Twitchy Little Generals"**, small chaos gremlins forcing their frantic energy into rigid, ill-fitting, oversized fascist uniforms.
3. **Agitprop / Military Registry UI**
   - Diegetic signage based on 1920s agitprop, using harsh 45-degree angles, deep black bars, and circular "Unit Tokens". Looks like high-speed industrial printing on grainy cardstock.

---

## 2) Art direction: what “on-style” looks like

### “On-style” checklist
- Feels like a **Command Map** or a **Military Field Manual**
- Uses **"Official Notches"** and **"Registration Stamps"** language, not "scrap and tape"
- Has **strict hierarchy** with stark Constructivist geometry
- Uses **Stark Cream** base with high-contrast **"Revolutionary Red"** accents, **"Deep Charcoal"** secondary, and cold **"Bureaucracy Teal"** tertiary.
- Adds "mature reveal" through **props and pictograms**, not gore

### “Off-style” indicators
- Corrugated metal, grime, or rust (old style)
- Taped repairs, fraying, haphazard stitching
- Neon UI colors or glossy sci-fi gradients
- Warm, storybook ink browns
- Uncontrolled distress: everything equally noisy and unreadable

---

## 3) UI direction (Reworked)

### Chosen UI style: **"Bureaucracy of War" / Command Bureau**
UI should appear as a **command deck** or **official military registry**:
- official military register pages
- registry stamps and notches
- red tape and thick black bar dividers
- stenciled, flat geometric pictograms
- high-speed print errors, ink-bleed, color layer misalignment (registration errors)

#### Interaction Feedback: **"Twitchy Interaction"**
When hovered, buttons must **"twitch" or vibrate** slightly, indicating barely-contained chaotic energy. Confirmation slams down like a heavy industrial press, perhaps with a small ink splatter. Crooked stamps (like an "REJECTED" or "APPROVED" stamp that isn't perfectly straight) show the "childish" lack of precision.

---

## 4) Color system (Reworked)

This palette is designed for high-contrast, stark readability.

### Base neutrals
- **Deep background charcoal (primary base):** `#23272A` (replace warm browns)
- **State Intelligence / Bureaucracy Teal:** `#006F7A` (for cold secondary/disabled elements)
- **Bureaucratic Slate Grey:** `#4F5A65` (for stable, secondary elements)

### High-Contrast Stark Base
- **Stark Cream (Official paper base):** `#F3EFE0`

### Accents: Red (danger/emphasis/energy)
- **Revolutionary Red (default accent/danger/critical):** `#B91C1C`
- **Industrial Orange:** `#EA580C` (secondary accent)
- **Caution Yellow (Defensive markings):** `#CA8A04` (specific to Frogmen)

### Usage rules
- Maintain **stark contrast** between cream base and charcoal/red elements.
- Red is the **primary state accent** (for danger, emphasize selection, critical highlights).

---

## 5) Typography (Updated)

### Chosen fonts
- **Display / headings / major labels:** **Saira Stencil One** (or Bebas Neue / Monument Extended)
- **Body / State Intelligence Copy:** **Roboto Condensed** (or modern sans-serif like IBM Plex Sans)

### Typography behaviors
- Apply **moderate tracking** and occasional **"print layer mis-alignment"** effect (slight 1-2px cyan/magenta offset).

---

## 6) UI components and primitives (Reworked)

### 6.1 Military Registry Pages (primary container)
**Look:** high-speed print cardstock silhouette, thick (4pt-8pt) black borders. Distress is limited to subtle **print errors** (ink bleed, misaligned colors).
**Use:** main content groupings

Rules:
- 45-degree angle clipped corners or sharp squares only.
- Inner content area cleaner for readability.

### 6.2 Registry Stamps (Buttons)
**Look:** stamped tin plates, flat geometric symbols (pictograms). When selected/confirmed, a "CONFIDENTIAL," "APPROVED," or "EXECUTE" stamp slams down, slightly crooked or misaligned.
**Use:** CTAs and navigational actions

### 6.3 Icon Pictogram Tiles
**Look:** thick bordered signage tiles with stenciled pictograms. Pictograms are simple black triangles, red circles, and basic shapes.
**Use:** status effects, warnings, resources

### 6.4 Black Bar Divider / Red Tape
**Look:** thick black bars or literal red tape segments.
**Use:** separators, section headers

### 6.5 Command Strip (footer bar)
**Look:** bureaucratic segmented field manifest, flatter and more utilitarian.
**Use:** resources, user identity

---

## 7) Inter states (Implementation-friendly)

### Recommended state strategy: overlay-driven
Continue using overlays, but the states are modified for Constructivism.

### Suggested state mapping
- **Default:** base registry panel/placard
- **Hover:** base + **vibration/"twitch"** animation + soft highlight overlay
- **Pressed:** translate down 1–2px + slight darken
- **Disabled:** desaturate toward bureaucratic teal/grey + Reduced alpha (remove tape overlay)

---

## 8) Faction Visual Direction (Added)

### Goblins (Player Faction): **"Twitchy Little Generals"**
* **Vibe:** Small chaos gremlins forced into uniformity. They are **twitchy, hyperactive, and unstable**, trying to stand at attention.
* **Silhouette:** Sharp, inverted triangles. Wide, stiff, high-collared uniforms that are often too big and pokey.
* **Medieval-Modern:** **"Ballistic Plate Mail"** (plate shaped like modern tactical vests) and **"Tactical Halberds"** (spears with modern rail system silhouettes and bayonet-style blades).

### Kobolds (Enemy Faction): **"Mountain Partisans"**
* **Vibe:** Jagged, asymmetrical mountain revolutionaries.
* **The Diagonal:** Use the sharp 45-degree angle (Constructivist symbol of progress). Banners, spears, and compositions should be dynamic along these diagonals.
* **Medieval-Modern:** Heavy wool furs, dragon-scale bandoliers, traditional **kettle hats** (painted with revolutionary sigils), and traditional scale mail worn *under* modern-style revolutionary trench coats. Weapon is the **"Crossbow-Carbine"** (rough-hewn DIY weapon with stock, held like modern rifle).

### Frogmen (Enemy Faction): **"Bayou Militia"**
* **Vibe:** Heavy, grounded, defensive "swamp-gentry insurgency".
* **Static Heavy Square:** Compositions must be imposing, stable, and rectangular.
* **Medieval-Modern:** Overalls/waterproof waders worn *over* heavy, rusted plate mail. Wide **"cavalier" hats** with swamp camouflage. Weapon is the **"Harpoon-Shotgun"** blunderbuss that fires iron rebar. Carry **"Shield-Barricades"** with "Don't Tread on Me" style iconography (like a coiled turtle or snapping snake).

---

## 9) Dice visual system (Material Progression)

Dice progression via material continues but is integrated into the "mass-produced state equipment" theme.

### Material readability requirements (Updated)
- **Cardboard (Common):** Corrugation, tape (acceptable here); looks like high-speed printed cardboard boxes.
- **Wood (Uncommon):** Clear grain, burned markings; mass-produced wood.
- **Bone (Rare):** Scratched ivory; state-harvested bone.
- **Metal (Epic):** Rust/chips; state-forged steel.
- **Gemstone (Legendary):** Faceted; etched markings.

---

## 10) Do / Don’t summary

### Do
- Use Constructivist language: bold geometry, heavy rectangles, harsh 45-degree angles, perfect circles.
- Keep hierarchy stark, bold, and readable.
- Use ink bleeds and misaligned print layers as acceptable distress (registration errors).
- Use Revolutionary Red for emphasis, Deep Charcoal base, and cold Bureaucracy Teal.
- Let “mature” come through props and iconography.
- Contradict rigid design with twitchy, hyperactive goblin animations.

### Don’t
- Rely on corrugated metal, scrap, tape, or rivets (old style).
- Use haphazard, warm "story-book paper-craft warmth".
- Use neon primaries or sci-fi gradients.
- Rely on stamps as default decoration (stamps are interactions or rare effects).

---

## 11) Versioning
- **v1** obsolete.
- **v2** reflects decision for total Constructivist/Fascist pivot (Industrial Mandate).