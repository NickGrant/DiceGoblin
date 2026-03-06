Status: active
Last Updated: 2026-03-05
Owner: Creative + Product
Depends On: `documentation/06-character-profiles/00-overview.md`

----

# Character Profile Template

## Purpose
- Keep creative profile entries consistent for art generation and UX readability planning.
- Preserve a clean separation between flavor reference and implementation contracts.

## Required Fields
```md
Name: <display name>
Type: <faction/species>
Role: <canonical gameplay role label where possible>
Image: <image filename>

Appearance: <core visual identity and silhouette>
Armor/Clothing: <materials, construction language, notable motifs>
Equipment: <primary/secondary gear and visual hooks>

Gameplay Role Fantasy: <1 sentence player-facing combat identity>
Silhouette and Readability Cues: <2-4 quick cues for at-a-glance recognition>
Ability Telegraph Cues: <how attacks/skills should be visually signaled>
Threat Priority Cue: <why/when player should focus this unit>

Portrait/UI Requirements:
- Portrait crop safe zone: <head/weapon or other focal requirement>
- Icon readability: <must-remain-visible visual markers at small size>
- Contrast notes: <constraints for dark/light backgrounds>

Art Prompt Summary:
- <5-7 concise lines optimized for art-agent prompting>
```

## Rules
- Creative reference only: do not treat these files as gameplay contracts.
- Keep role naming aligned with canonical gameplay terms when possible.
- Keep profile prose expressive, but include short execution-ready summary blocks.

