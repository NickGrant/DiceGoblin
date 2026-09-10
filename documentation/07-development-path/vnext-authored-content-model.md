---
Title: "vNext Authored Content Model"
Status: Accepted
Last Updated: 2026-09-09
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-progression-state-model.md
Category: 07-development-path
Tags:
  - vnext
  - authored-content
  - json
  - content-registry
  - validation
---

# vNext Authored Content Model

## Decision

Authored gameplay content for Dice Goblins vNext will live in static JSON files checked into the Git repository and shipped with the application.

Git-tracked JSON is the authoritative source for authored gameplay definitions. MySQL should not be treated as the primary store for this content and should not maintain a required duplicate catalog copy unless a future subsystem demonstrates a concrete need for one.

The guiding rule is:

> Authored game content ships with the code; mutable player and runtime state lives in MySQL.

## Scope

The JSON content model is expected to cover authored definitions such as:

- unit types and enemy definitions
- kin/racial variants
- dice materials and aspects
- regions and node definitions
- event and reward definitions
- unlock definitions
- Codex entries
- dialogue definitions
- Shop offerings
- Academy upgrades
- Wrong Machine recipes
- bounties and objectives
- other static gameplay catalogs and tuning values

The exact set may evolve as vNext systems are reconciled.

## Versioning and Release Behavior

Authored JSON lives in the same repository as the application and follows the same normal development lifecycle:

- Git history
- branches
- pull requests
- code review
- automated validation
- release/deployment versioning
- rollback through normal application release mechanisms

Balance and content changes should therefore be ordinary content diffs rather than SQL migrations.

For example, changing a reward chance or unit stat should modify the relevant JSON definition rather than introducing a new database migration.

## MySQL Boundary

MySQL stores mutable runtime and player state, including concepts such as:

- accounts and identities
- currency balances
- Energy state
- owned units
- owned dice
- inventory
- unlock ownership
- Codex ownership
- bounty/objective progress
- active/generated run state
- battle/runtime state
- finalized rewards and transactional/idempotency records where required

MySQL should not duplicate authored definitions by default.

A relational projection or derived index may be introduced later only if a concrete runtime/query requirement justifies the additional synchronization complexity.

## Stable Content IDs

Every authored definition that may be referenced by another definition or by persisted runtime/player state must have a stable, durable ID.

Examples may include IDs such as:

```text
region.mountains
kin.pig
unit_type.saboteur
codex.whim.arrival
unlock.wrong_machine
material.glass
aspect.sharp
```

The stable ID is the durable identity. Display names and presentation text may change freely without changing identity.

Renaming a durable ID after persisted player/runtime state references it should be treated as a compatibility change requiring an intentional mapping or migration strategy.

## Hybrid File Organization

vNext will use a hybrid content layout rather than one giant file, one mandatory file per domain, or one mandatory file per definition.

The filesystem should be organized for human maintainability and may split content by domain, feature, region, character, or other coherent grouping when useful.

Example shape:

```text
content/
  units/
    goblins.json
    pigs.json
    kobolds.json
    frogmen.json

  kin/
    pig.json
    lizard.json
    frog.json

  dice/
    materials.json
    aspects.json

  regions/
    farm.json
    mountains.json
    swamp.json

  rewards/
    farm.json
    mountains.json
    global.json

  codex/
    farm.json
    characters.json
    lore.json

  dialogue/
    whim/
      arrival.json
      wrong-machine.json

  economy/
    shop.json
    academy.json
    wrong-machine.json

  objectives/
    bounties.json
    tutorials.json
```

This is illustrative rather than a rigid required directory tree.

The organizing principle is:

> Split files when doing so improves conceptual clarity, editing, review, or merge behavior; do not split merely to satisfy an arbitrary one-object-per-file rule.

Small coherent catalogs such as dice materials may reasonably remain together. Large or independently authored areas such as dialogue may be split into smaller files.

## Runtime Registry

Physical file organization is an authoring concern, not a runtime identity concern.

The backend should treat all loaded JSON definitions as one validated logical content registry keyed by stable IDs.

Conceptually:

```text
JSON files
    |
    v
content loader
    |
    v
validated content registry
    |
    +--> region.mountains
    +--> kin.pig
    +--> reward.farm_boss
    +--> codex.whim.arrival
```

A definition may reference another definition by stable ID regardless of which file contains either definition.

Reorganizing files should therefore not change gameplay identity as long as stable IDs remain unchanged.

## Validation

Authored content must be validated automatically rather than relying on MySQL foreign keys or runtime player encounters to reveal broken references.

Validation should include both structural and semantic checks.

### Structural validation

Examples:

- required fields exist
- IDs use valid formats
- probabilities are within valid ranges
- numeric values have valid types and ranges
- definition shapes match their expected schema

### Semantic/cross-reference validation

Examples:

- referenced stable IDs exist
- reward definitions reference valid currencies, items, units, Codex entries, or unlocks
- regions reference valid nodes/enemies/rewards
- dialogue prerequisites reference valid Codex/unlock IDs
- recipes reference valid ingredients and outputs
- handler/config combinations are supported
- duplicate stable IDs do not exist anywhere in the content graph

Validation should run in CI and should fail the build/release when authored content is internally inconsistent.

## Data Versus Behavior

JSON should remain declarative content and configuration rather than becoming a general-purpose scripting language.

Straightforward configuration belongs in JSON, including:

- IDs
- stats
- prices
- costs
- reward chances
- prerequisites
- simple flags
- references to other definitions

Complex executable behavior should remain in backend code and be selected or configured by authored definitions where appropriate.

This avoids creating deeply nested generic rule trees or an accidental programming language inside JSON.

## JSON-Only Authoring

The initial vNext authored-content format is JSON only.

Do not introduce parallel YAML, CSV, Markdown, or other authored gameplay-definition formats merely for editing convenience.

If JSON becomes cumbersome for long-form dialogue, Codex prose, or other content, editing and authoring tooling may be revisited later while preserving JSON as the canonical shipped representation if appropriate.

The current decision explicitly defers solving specialized content-editing ergonomics.

## Generated Bundles

The initial vNext design does not require a checked-in generated aggregate content bundle.

If bundling or compilation later improves runtime performance, any generated aggregate should be treated as a build artifact rather than a second manually maintained source of truth.

## Active Runs Across Releases

The initial architecture does not require full per-run content-version pinning.

Generated or already-finalized runtime state should remain persisted and authoritative. Unresolved behavior may use the currently deployed authored definitions unless later testing shows that deployments materially disrupt active runs.

Finalized rewards must remain finalized and must never reroll merely because authored content changed between releases.

A stronger content-version pinning model may be introduced later if live deployment behavior demonstrates a need.

## Consequences

This decision keeps authored game data reproducible and reviewable with the application release while keeping the database focused on mutable state.

It also intentionally avoids:

- authored-content SQL migrations
- database/editor drift between environments
- mandatory JSON-to-MySQL synchronization
- one giant monolithic content file
- one mandatory file-per-definition convention
- multiple competing authored-data formats
- premature custom editing tooling

The hybrid JSON layout can evolve as the content catalog grows without changing runtime identity because stable IDs, not file paths, are the durable contract.
