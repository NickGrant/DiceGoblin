# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish Warband authored content and client projection

**Status:** In Progress
**Priority:** High

#### Problem
Warband persistence now stores stable authored IDs, but the canonical ContentRegistry only knows gameplay configuration and regions. Milestone 2 needs the reconciled authored definitions required to understand owned units, abilities, kin, and dice profiles without reviving SQL catalogs or shipping the entire server catalog to the browser. Establish those content domains, semantic validation, registry access, and the explicit safe client projection before Warband APIs depend on them.

#### Required Context
- `documentation/07-development-path/vnext-authored-content-model.md` — canonical JSON, semantic validation, stable IDs, server/private/client exposure rules
- `documentation/03-content/00-content-source-map.md` — migration/reconciliation rule for content domains
- `documentation/02-systems/dice-profiles-and-aspects.md` — dice profile/material/aspect identity and size eligibility
- `documentation/02-systems/ability-loadouts-and-dice-binding.md` — ability/loadout/dice-binding presentation needs
- `documentation/02-systems/unit-stat-advancement.md` — authored unit stats/growth boundary; do not invent Speed
- `documentation/02-systems/unit-promotion.md` — promotion-path authored relationships where needed for unit identity, not implementation of promotion transactions
- `documentation/02-systems/warband-and-formation.md` — Warband/squad terminology and formation context
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — prototype catalog/services are evidence, not target architecture
- Current `backend/content/`, `ContentRegistry`, `ContentValidator`, `ClientContentProjector`, content tests, and generated `frontend/public/game-content.json`

Inspect retained prototype source/tests/assets and current canonical system docs for the actual existing Dice Goblins unit types, kin, abilities, dice materials/aspects/profiles, display names, art references, and mechanics. Reconcile deliberately; do not bulk-convert prototype SQL rows or service constants.

Load other decision docs only if reconciliation reaches their domain.

#### Acceptance Criteria
- Keep Git-tracked JSON under `backend/content/` as the single authored runtime authority. Do not introduce SQL gameplay catalogs, YAML/CSV parallel sources, or hand-maintained client copies.
- Add only the authored Warband domains needed by Milestone 2: kin, player unit types, player abilities, dice materials, dice aspects, and dice profiles. Do not author enemy/region encounter catalogs or later progression/economy content merely for completeness.
- Use durable namespace-qualified stable IDs consistent with the accepted ID contract. Existing canonical IDs already persisted/tested in Package 1 examples do not by themselves require those exact examples to become product content; select/reconcile real product IDs deliberately and keep fixtures aligned later.
- Unit types must provide the static identity/presentation and base combat data Warband/detail surfaces require, including the established core stat family HP, Attack, Defense, Precision, and Resolve. Do not add Speed. Keep level/XP instance state in MySQL rather than authored unit definitions.
- Kin definitions provide their authored static identity/presentation and references needed by unit presentation/mechanics. Do not implement kin unlock/restoration state here; that remains player state/later Milestone 9 behavior.
- Ability definitions provide static identity/presentation plus enough declarative configuration for the existing loadout/dice-slot contract and future authoritative combat adaptation. Reuse existing complex PHP combat handlers/algorithms rather than attempting to encode executable behavior or a scripting language in JSON.
- Dice materials, aspects, and profiles follow the accepted material + aspect profile model. Material and rarity remain independent. Profiles reference material/aspect IDs and declare valid sizes; individual dice instances continue storing only size + profile ID.
- Structural validation rejects malformed definitions, invalid required fields/types/ranges, invalid stable-ID namespaces, impossible dice sizes, duplicate IDs, and other malformed Warband definitions appropriate to each type.
- Semantic validation rejects missing/wrong-type references across Warband authored content, including at minimum unit-type -> kin/ability relationships where the reconciled model uses them and dice-profile -> material/aspect relationships.
- Validate dice profile allowed sizes against the size restrictions of the referenced material and every referenced aspect. An impossible material/aspect/profile size intersection must fail content validation before runtime.
- Keep ContentRegistry as the logical unified catalog keyed by stable ID. Add focused typed access/helpers only where they clarify callers; do not create a second per-domain registry architecture.
- Define the browser exposure explicitly with `ClientContentProjector` allowlists. Phaser will eventually need public presentation/mechanics information for owned units/dice and configuration screens, but new fields are server-private by default.
- Do not send hidden server-only tuning merely because it exists in canonical JSON. Treat anything placed in `game-content.json` as readable by players.
- Public projection should be sufficient to resolve presentation of authorized owned-instance IDs returned later by the API without shipping mutable ownership state. It may expose safe static definitions for public Warband catalogs when appropriate.
- Preserve deterministic content revisioning over the canonical server registry. Changes to any canonical Warband definition must affect the revision used for client/server compatibility, whether or not every field is projected.
- Generated `frontend/public/game-content.json` remains a deterministic build artifact derived from canonical content, not a separately maintained source.
- Update the framework-neutral frontend client-content validation/indexing layer to accept and index the new projected Warband domains while retaining strict contract validation and safe startup failure on malformed projection.
- Existing Milestone 1 region/config projection behavior and exact revision compatibility must remain intact.
- Add focused validator/registry/projector/client tests for valid representative content and meaningful structural/semantic failures, including private-field non-exposure and dice size-compatibility failures.
- Follow the content-source migration rule: when a retained Markdown/prototype catalog would otherwise remain a competing authored authority for a migrated Warband domain, reduce/update it so canonical JSON is clearly authoritative. Keep conceptual system docs; remove duplicate static catalogs rather than maintaining two sources.
- Do not create owned units/dice/squads, fixture endpoints, Warband read APIs, squad commands, unit commands, or Phaser Warband screens in this package.
- Do not implement promotion transactions, XP curves/growth formulas not already deliberately accepted, Shop/economy behavior, Wrong Machine reconstruction, starter provisioning, run locks, or combat migration.

#### Completion
Run content validation/build and the focused backend/frontend content tests, then the applicable context/docs/frontend build gates needed to prove the generated client projection remains valid. Report the resulting canonical content revision and the exact public domains/fields added to `game-content.json`. Leave this package **In Progress** for architectural review. Do not promote or begin package 3 in the same coding-agent change.
