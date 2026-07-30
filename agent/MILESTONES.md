# MILESTONES FILE
----
Active milestones only. Move completed entries to `agent/MILESTONES_ARCHIVE.md`.

## Critical Path UAT

**Status:** Planned

Continue validating the completed July 25 roadmap through the player-facing critical path and turn observed failures into severity-ranked issues.

Success criteria:

- Fresh-account UAT covers Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- Repeat-run behavior is checked for story, stolen pages, and unlock messaging.
- Encounter variety and consumable pacing are sampled across multiple regions and seeds.
- Any UAT failures are logged with reproduction notes and player-facing severity.

### Related Issues

- Continue fresh-account July roadmap UAT
- Validate encounter and consumable feel

## UAT Polish Backlog

**Status:** Planned

Confirm release hygiene and hold lower-priority UAT polish that should not block the first fix round.

Success criteria:

- Low-severity improvements are deferred without blocking release hardening.
- `main`, roadmap analysis, active tracker, and generated-artifact policy are reconciled.
- Final validation expectations are documented before release hardening completes.

### Related Issues

- Confirm release merge and generated-artifact hygiene

## Shrine Expansion

**Status:** Planned

Expand shrine nodes from simple teeth grants into generated quality-weighted shrine encounters with visible effects, optional costs, and UAT-ready tuning hooks.

Success criteria:

- Shrine quality changes the generated option pool and weights.
- Shrine effects can grant teeth, heal, apply run-state changes, affect routing, and prepare future combat modifiers.
- Costly shrine offers can be declined before effects are applied.
- Shrine outcomes are generated at encounter time and persisted as results, not preprogrammed into node metadata.
- Tests and docs cover effect generation, application, idempotency, and known follow-up behavior.

### Related Issues

- UAT and tune shrine effect weights

## Hazard Expansion

**Status:** Active

Expand hazard nodes from metadata-only obstacles into generated severity-weighted downside encounters with visible immediate effects, optional choices, delayed run modifiers, and UAT-ready tuning hooks.

Success criteria:

- Hazard severity changes the generated downside pool and weights.
- Hazard outcomes are generated at encounter time and persisted as results, not preselected as exact effect metadata on map nodes.
- At least ten enabled hazard options exist in the initial generated batch.
- Immediate effects, choice-based costs, and delayed next-combat effects apply exactly once.
- Player-facing screens show what happened, what choice is available, and what delayed effects are active.
- Tests, docs, and sampling evidence cover generation, application, idempotency, and balance distribution.

### Related Issues

- Define generated hazard severity catalog
- Implement hazard choice and mitigation flow
- Apply immediate and delayed hazard downsides
- Add hazard tuning sampler and evidence packet
- UAT and tune hazard severity weights

