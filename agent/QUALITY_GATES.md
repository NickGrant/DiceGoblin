# Quality Gates
----

## Purpose
Centralize verification and documentation-hygiene rules for the active vNext implementation.

## Verification Matrix
- Frontend/Phaser behavior changes:
  - run relevant frontend tests/build;
  - perform a brief UX sanity check;
  - use deterministic Phaser scene/screen capture when visual evidence helps;
  - verify Compact, 1600x900 reference, and Wide landscape behavior when layout is affected;
  - verify the portrait rotate-device gate when game-host/orientation behavior is affected.
- Backend/PHP API changes:
  - run targeted command/query/endpoint tests;
  - verify auth, CSRF, ownership, validation, and transaction failure paths where applicable.
- Data/schema changes:
  - prove a fresh vNext database can be created from the current clean baseline;
  - do not require prototype migration history or SQL-authored gameplay catalogs.
- Authored-content changes:
  - validate JSON structure and cross-references;
  - validate stable IDs;
  - verify client projections contain only allowlisted fields and no known server-only information.
- Spending/random/durable/gameplay commands:
  - verify idempotent retries do not double-spend, duplicate assets, or reroll finalized results.
- Documentation-only changes:
  - run the repository documentation/context check when available;
  - review references for deleted/superseded paths and conflicting authority.

## Prototype Reuse Gate
Before replacing a substantive prototype subsystem, inspect the implementation that currently provides the behavior and classify relevant source, tests, assets, and infrastructure as:

- **Keep** — compatible with vNext boundaries and safe to retain substantially as-is.
- **Adapt** — valuable implementation/algorithm, but it must move behind or conform to a vNext boundary.
- **Rebuild** — behavior remains useful but the implementation shape conflicts with accepted vNext architecture strongly enough that reuse would preserve the wrong abstraction.
- **Retire** — behavior/implementation is obsolete and should be deleted once its replacement no longer depends on it.

Accepted vNext decisions determine architecture; prototype source does not override them. Conversely, a rewrite must not discard working algorithms or tests merely because they originated in the prototype.

Do not maintain a permanent legacy-source archive inside the active branch. Git history is the archive. Keep still-needed prototype code in its existing location until the replacing slice is proven; then delete obsolete code in the same or immediately following scoped work.

## Minimum Mixed-Change Verification
Use the current commands/scripts in the repository. At minimum, mixed frontend/backend work should run the applicable backend tests, frontend tests/build, and documentation/context checks. If a command has changed, update this file or `agent/QUALITY_GATES.md` references rather than preserving an obsolete command for documentation compatibility.

## Failure Reporting
If verification fails, report the failing check and actionable error. Distinguish known pre-existing failures from failures introduced by the current work when that can be established.

## Documentation Hygiene
- The active branch contains current guidance, not an in-tree historical archive.
- Delete or rewrite superseded documentation when a vNext contract replaces it.
- Git history is the archive.
- Broken references to deleted documentation are defects.
- Accepted vNext decision documents override prototype source shape.

## Context Guardrails
Load the smallest authoritative set needed for the task. Do not search deleted docs/Git history for design direction unless historical recovery is explicitly required. Current source/tests should be inspected when replacing a subsystem so useful algorithms or behavior are not lost, but source does not override accepted vNext architecture.

## Feature Intake
For new feature work, capture behavior, constraints, data/authority implications, UX, error handling, and verification needs. Place the feature within the accepted vNext architecture before implementation. Do not revive prototype patterns merely because they provide a nearby implementation example.
