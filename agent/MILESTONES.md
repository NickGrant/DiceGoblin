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

## Pattern-Based Run Map Generation

**Status:** Planned

Implement the `pattern-v1` spine-first map-generation contract from `documentation/02-systems-mvp/15-pattern-based-run-map-generation.md` for migrated procedural combat regions while preserving persisted-run compatibility.

Success criteria:

- Authored pattern/profile catalogs are validated before use.
- `pattern-v1` builds valid start, spine, boss/exit, branch, cap, and encounter-binding topology behind a generator version.
- Mountains and Swamps opt in only after deterministic tests, simulation gates, and manual map review pass.
- Story placement requests participate in generation instead of mutating completed graphs.
- Generation provenance, debug inspection, and simulation reporting expose enough detail for support and tuning.

### Related Issues

- Implement pattern-v1 assembler behind generator version
- Opt Mountains into pattern-v1 maps
- Opt Swamps into pattern-v1 maps
- Migrate story placement into generation requests
- Add pattern generation debug and simulation gates

## Pattern-V2 Run Map Generation

**Status:** Planned

Build a database-owned tile/catalogue generator that composes squarer, multi-row run maps from authored grid patterns without keeping a JSON source-of-truth copy.

Success criteria:

- Pattern-v2 definitions, region rules, and profiles are seeded through forward-only migrations.
- Grid patterns support width, height, cost, tags, node cells, connector cells, explicit connections, and perimeter exits.
- Connector cells compile to edge/waypoint metadata only; they do not create runtime run nodes.
- The assembler composes compact maps across multiple rows and fewer columns while preserving reachability, boss gating, and no-crossing validation.
- Mountains opts into pattern-v2 only after preview tooling, deterministic tests, simulation gates, and manual review pass.

### Related Issues

- Seed database-owned pattern-v2 tile catalog
- Validate pattern-v2 grid catalog contracts
- Implement pattern-v2 tile composer
- Add pattern-v2 preview and simulation gates
- Opt Mountains into pattern-v2 maps
