# First Pig Kin Demo Roadmap
----

Status: active
Last Updated: 2026-07-30
Owner: Product + Engineering + QA
Depends On: `agent/ISSUES.md`, `agent/MILESTONES.md`, `documentation/07-roadmap/2026-07-25-completion-analysis.md`

## Purpose

Define the next release target after the July 25 implementation push: a formal demo build that ends when the player creates their first Pig Kin through the Wrong Machine.

This document is the current product roadmap for the demo. `agent/ISSUES.md` and `agent/MILESTONES.md` remain the execution source of truth.

## Demo Target

The demo is successful when a fresh player can:

1. Start from a clean account.
2. Learn the basic loop through Mystic Cave and Farm.
3. Progress through Mountains and Swamps.
4. Recover the Wrong Machine.
5. Return to the appropriate story surface.
6. Gather or receive the required Pig Kin materials, catalyst, and Raw Chaos.
7. Use the Wrong Machine to create the first Pig Kin.

That first Pig Kin creation is the stopping point for the formal demo release.

## Current Scope

The demo milestone includes only work needed to make that path understandable, stable, and presentable.

Included:

- Required dialogue through first Pig Kin.
- Objective guidance focused on the current next action.
- Chaos node verification and any required demo fixes.
- Hazard completion needed for demo UAT.
- Consumable unlock, inventory, and balance verification.
- Wrong Machine first-reconstruction UI polish.
- Academy, promotion, warband, unit, guide, codex, and run-node UI polish needed for the demo path.
- Farm repeat-run generated-map behavior after Wrong Machine unlock, if needed for post-recovery play.
- Demo release hardening, migration checks, and release evidence.

Excluded from this milestone:

- New biomes beyond the current opening arc.
- Additional kin beyond Pig Kin.
- Large affix expansion.
- Full Wrong Machine fabrication or advanced targeting.
- Final Library encounter implementation.

## Post-Demo Direction

After the demo release, the game should expand into a larger campaign with roughly ten biomes before the final Library encounter.

Post-demo work should include:

- more biome regions with distinct enemy families and bosses;
- additional kin lineages with protected material/catalyst paths;
- deeper affix and dice progression;
- broader hazard, shrine, and chaos catalogs;
- stronger kin passives and progression interactions;
- final Library arc and encounter design.

These future lanes are tracked in `agent/MILESTONES_BACKLOG.md` and `agent/ISSUES_BACKLOG.md` until promoted.

## Demo Readiness Principles

- Prefer clarity over breadth.
- Do not expand the campaign before the first Pig Kin path is stable.
- Keep random progression from blocking required demo goals.
- Treat UAT findings as the source for final demo polish.
- Avoid adding systems that do not directly support the demo path.

## Current Execution

The active milestone is `First Pig Kin Demo Release`.

The next work should come from `agent/ISSUES.md`, starting with the highest-priority blocker or highest-signal demo polish item.