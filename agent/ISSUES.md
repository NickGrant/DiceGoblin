# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Establish authored ContentRegistry and client projection

**Status:** Open
**Priority:** High

#### Problem
Establish JSON-authored gameplay content as the single source of static game definitions, with a validated server registry and a deny-by-default client projection derived from that same source. This package must prove the content boundary needed by later bootstrap/Phaser work without prematurely migrating the full game catalog.

#### Required Context
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md` — Client Authored Content Boundary and Content Version Compatibility
- `documentation/07-development-path/vnext-backend-internal-architecture.md` — ContentRegistry / ContentValidator boundaries
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — authored-content/catalog guidance
- Current source/build/test infrastructure touched by the implementation

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Add a canonical Git-tracked JSON content root and a maintainable file organization consistent with the accepted hybrid content model. Do not migrate the entire prototype catalog in this package; include only the minimum real content/configuration needed to prove the architecture and support the walking skeleton.
- Implement a server-side `ContentRegistry` that loads the canonical JSON into one logical registry keyed by stable durable IDs or equivalent typed keys. File paths are authoring organization, not runtime identity.
- Implement automated `ContentValidator` coverage for the content introduced in this package, including malformed JSON/shape errors, invalid or duplicate stable IDs, required fields/ranges, and cross-reference validation where references exist. Validation failures must fail the content/build verification path rather than surface first during gameplay.
- Keep authored JSON declarative. Do not create a generic scripting/rule language, YAML/CSV parallel sources, SQL catalog synchronization, or a second manually maintained content catalog.
- Implement an explicit allowlisted client projection derived from canonical content. New canonical fields are server-private by default; projection code/schema must opt fields into browser-visible output.
- Prove by automated test that representative server-only fields/content cannot appear in the generated client projection unless explicitly allowlisted. Do not use blacklist filtering, minification, obfuscation, or client-side encryption as secrecy controls.
- Keep player-conditioned/revealed content out of the static public projection. This package does not implement player authorization/discovery APIs; it establishes the boundary only.
- Produce a deterministic content revision/manifest hash (or equivalent deterministic revision) shared by server content and the generated client projection. Identical canonical content must produce the same revision; relevant content changes must change it. Bootstrap compatibility enforcement is a later package.
- The client projection is generated/derived build output, not a separately authored gameplay catalog. Do not require a checked-in monolithic server aggregate bundle.
- Move the temporary starting-Energy balance input into canonical authored configuration consumed through `ContentRegistry` by authoritative account creation, then remove `VNEXT_INITIAL_ENERGY` as a runtime/test environment source. The database must remain free of a starting-Energy default and `energy_max` remains derived/unpersisted.
- Preserve the fresh vNext database baseline and registered auth/session/health behavior. Do not re-register prototype gameplay routes or recreate prototype SQL catalogs.
- Add focused automated tests for registry loading, validation failures, duplicate/reference handling as applicable, client projection privacy/allowlisting, deterministic revision behavior, and starting-Energy consumption through account provisioning.
- Add/update the narrow repository verification command(s) needed so content validation/projection integrity runs in normal package verification/CI paths.
- Do not implement `/api/v1/game/bootstrap`, Phaser `GameRuntime`, `ClientContentRegistry` runtime behavior, gameplay screens, units/dice/squads/runs/combat, or broader catalog migration in this package.

#### Completion
Run applicable backend/content/context gates from `agent/QUALITY_GATES.md`. Leave this package active for architectural review; do not promote or begin the bootstrap package in the same change.
