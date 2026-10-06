# Active Execution Issue

## Milestone 9 - Kin and Wrong Machine

### Milestone 9 Package 1 - Authored kin/reconstruction foundation + ownership/read contract

**Status:** In Progress
**Priority:** High

#### Problem

Milestone 8 is complete and passed focused manual UAT on 2026-10-05. Milestone 9 now needs the canonical authored and persistence foundation for Kin restoration and Wrong Machine reconstruction before any reconstruction mutation or Phaser surface is implemented.

#### Accepted baseline

Milestone 8 - Permanent Progression is complete. Technical closure was approved at `6314877766187931124c4d8fa5013da02354adfc`, and focused manual UAT passed on 2026-10-05 with no blocking findings requiring a correction package.

The vNext roadmap defines Milestone 9 as Kin unlock/restoration, Pig/Lizard reconstruction, and first-unlock versus deterministic repeat behavior.

Preserve the accepted vNext architecture:
- authored gameplay definitions belong in canonical content JSON rather than SQL-authored catalogs;
- durable player ownership/state belongs in MySQL;
- permanent ownership and repeatable acquisition are distinct concepts;
- server/API state remains authoritative and the Phaser client must not fabricate progression state;
- prototype Wrong Machine/Kin implementation is behavioral evidence only and is not an API/schema compatibility target.

Do not implement the reconstruction spend/mutation or final player-facing Wrong Machine UI in Package 1.

#### Purpose

Establish one canonical vocabulary and read boundary for Kin and reconstruction so later Milestone 9 packages can implement the transaction and Phaser interaction without inventing parallel state or duplicating authored data.

#### Required authored model

Add the smallest canonical content model needed to describe Milestone 9 reconstruction behavior.

At minimum it must support:
- stable Kin definitions for the Milestone 9 Pig and Lizard families;
- stable reconstruction recipe definitions linked to their resulting Kin/unit output;
- authored ingredient/currency requirements rather than hard-coded controller/service constants;
- explicit distinction between first restoration/unlock behavior and repeat reconstruction behavior;
- stable references to every item, unit type, Kin, unlock, reward/event definition, or other authored dependency used by a recipe;
- validator coverage for missing, duplicate, malformed, cyclic, or incompatible references where applicable.

Do not expose server-only catalog data wholesale to the browser. Extend the client content projection only for fields that are actually needed by the read contract/runtime.

#### Durable ownership/state

Define the minimal MySQL persistence required to answer authoritative Kin ownership/restoration state.

Requirements:
- one canonical durable representation of which Kin a player has restored/unlocked;
- no duplicate boolean/feature state representing the same ownership;
- persistence must survive reload/re-entry and clean database reset/provisioning;
- ownership must be player-scoped and enforce normal cross-player isolation;
- do not persist authored recipe/catalog definitions in SQL;
- do not introduce reconstruction transaction/history tables unless they are required for a concrete accepted invariant in this package.

Prefer reuse of the existing permanent-unlock model if it can represent Kin restoration without semantic ambiguity. If Kin restoration requires distinct durable state, make that boundary explicit and justify it in tests/docs rather than silently adding parallel ownership.

#### Authoritative read contract

Add the backend/application read boundary required for a future Wrong Machine screen.

It must allow an authenticated player to determine, for each currently relevant Milestone 9 recipe:
- recipe identity and authored presentation-safe metadata;
- target Kin/unit output;
- whether the target Kin is already restored/owned;
- first-restoration versus repeat mode;
- required ingredients/currency and the player's authoritative owned amounts needed to render availability;
- whether prerequisites are met;
- whether the recipe is currently reconstructable;
- player revision or equivalent authority needed to reconcile later mutations safely.

The read response must derive availability from authoritative inventory/wallet/unlock state. Do not store or return a second mutable availability flag that can drift from those sources.

#### Pig and Lizard scope

Package 1 must establish valid authored/read coverage for both Pig and Lizard reconstruction families because both are part of the Milestone 9 exit criterion.

This package does not need to make both reconstructable through a mutation yet. It must prove their content graph and read semantics are representable without family-specific schema/controller branches.

Do not add Frog Kin; Swamp/Frog Kin remains Milestone 13.

#### Compatibility and boundaries

Keep green and preserve:
- Milestone 7 inventory, Shop, dice lifecycle, Teeth, and Raw Chaos ownership;
- Milestone 8 Academy/permanent unlocks, unit acquisition, promotion, and shared player revision semantics;
- unit identity/kin/type semantics already used by Warband and combat;
- clean vNext database bootstrap/reset;
- content revision/client projection validation.

Do not:
- revive prototype Angular Wrong Machine pages/services as the runtime architecture;
- introduce SQL-authored recipes/Kin catalogs;
- duplicate wallet or inventory ownership inside Wrong Machine state;
- implement randomized reconstruction if the authored contract calls for deterministic repeat behavior;
- add final reconstruction transaction/idempotency behavior yet;
- begin Milestone 10 encounter-depth work.

#### Verification

Run targeted checks while implementing, then the applicable package gates from `agent/QUALITY_GATES.md`.

At minimum verify:
- production content validation for all added Kin/recipe references;
- persistence and cross-player ownership isolation;
- clean DB provision/reset;
- authoritative read behavior for unowned/restored Kin and first/repeat modes;
- inventory/wallet/prerequisite-derived availability;
- Pig and Lizard families use the same generic model/read path;
- backend auth/ownership/validation negative paths;
- client content projection exposes no unnecessary server-only fields;
- full supported backend suite and frontend/content/docs gates required by the package.

#### Completion evidence

At completion, report:
- implementation SHA;
- schema/persistence choice for Kin ownership and why it is not duplicate state;
- canonical content definitions added and production content revision;
- focused and full verification counts;
- exact read-contract shape/endpoint introduced;
- confirmation that Pig and Lizard both resolve through the same generic foundation;
- confirmation that no reconstruction mutation or Milestone 10 work was introduced.

Leave Package 1 **In Progress** for architectural review. Do not promote Package 2 yourself.
