# Context Router
----

## Purpose
- Route coding and design work to the smallest current vNext context set.
- Avoid loading superseded prototype decisions into implementation context.

## Default Load
- `AGENTS.md`
- `agent/LLM_CONTEXT.md`
- `agent/ISSUES.md`
- `agent/MILESTONES.md`
- this file

## Primary vNext Route
For architecture, implementation planning, APIs, storage, authored content, progression, economy, rewards, or Phaser structure:
- `documentation/07-development-path/vnext-game-overhaul.md`
- then the relevant `documentation/07-development-path/vnext-*.md` accepted decision document.

## Topic Routes
- Game overview and loop:
  - `documentation/00-overview/00-project-overview.md`
  - `documentation/00-overview/01-core-gameplay-loop.md`
  - `documentation/00-overview/02-glossary.md`
- Lore and character tone:
  - `documentation/01-lore/00-world-and-lore.md`
  - `documentation/01-lore/02-character-profiles.md`
- Gameplay systems and combat behavior:
  - `documentation/02-systems/README.md`
  - then the narrow relevant system document.
- Authored content design references:
  - `documentation/03-content/README.md`
  - then the relevant retained catalog.
  - During vNext these Markdown catalogs are migration/design references; canonical runtime authored definitions move to JSON as their milestone is implemented.
- UX and visual direction:
  - `documentation/04-ux/01-visual-design-guide.md`
  - `documentation/07-development-path/vnext-phaser-client-architecture.md`
- Technical architecture:
  - `documentation/05-technical/00-tech-stack.md`
  - `documentation/05-technical/01-authentication-and-sessions.md`
  - relevant accepted vNext architecture/contract document under `07-development-path`.
- Testing and verification philosophy:
  - `documentation/06-testing-release/00-testing-strategy.md`
  - `agent/QUALITY_GATES.md` for current commands.
- Approved future base-game biome roster:
  - `documentation/07-development-path/01-base-game-content-roster.md`
- Documentation and engineering standards:
  - `documentation/08-operations/00-engineering-standards.md`
  - `documentation/08-operations/02-documentation-style-guide.md`

## Retrieval Rules
- Prefer accepted vNext decisions over prototype implementation shape.
- Do not search Git history for design guidance unless the user explicitly asks for history, rationale, or recovery of a removed idea.
- Do not infer current requirements from deleted paths referenced by old commits.
- Load implementation source when needed to preserve useful algorithms or measure migration work, not to revive superseded architecture.
- Prefer the smallest authoritative context set that can prove an implementation decision correct or incorrect.
