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
Load the smallest authoritative set needed for the task. Do not search deleted docs/Git history for design direction unless historical recovery is explicitly required. Source/tests may be inspected to preserve algorithms or behavior, but source does not override accepted vNext architecture.

## Feature Intake
For new feature work, capture behavior, constraints, data/authority implications, UX, error handling, and verification needs. Place the feature within the accepted vNext architecture before implementation. Do not revive prototype patterns merely because they provide a nearby implementation example.
