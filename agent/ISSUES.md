# Active Execution Issue

## Milestone 13 - Swamp and Parity Audit

### Milestone 13 Package 1 - Canonical Swamp and Frogmen authored-content foundation

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 12 is complete after accepted fresh-player onboarding UAT and its focused corrections. The next vNext proof is the Swamps: the canonical third biome, with Frogmen as its native enemy family and Frog Kin as its associated lineage. Farm and Mountains already prove the generic region/run/combat architecture at two regions, while Milestones 7-12 added economy, permanent progression, Kin/Wrong Machine, branch-capable encounter depth, Codex/dialogue/objectives, and onboarding. Before implementing the complete Swamp run, Package 1 must establish a current canonical authored-content foundation that composes those accepted systems without reviving prototype-specific architecture or prematurely fixing later package behavior.

#### Accepted baseline
- Milestone 12 UAT was explicitly accepted by the user on 2026-10-31 after corrections through `c40959b37fe2d08a4b01fc8673b103a5ab8fe2b9`.
- `documentation/07-development-path/01-base-game-content-roster.md` canonically assigns Swamps -> Frogmen -> Frog Kin.
- `documentation/03-content/06-biomes-and-regions.md` is transitional design reference: it identifies Swamps as the third parity/repeatability region but explicitly says prototype tuning, unlock scenes, dialogue-seen state, and historical Wrong Machine custody/recovery logic are not canonical merely because older material contained them.
- Farm and Mountains use the accepted generic authored region/run/combat/event/reward architecture. Swamp must not introduce a region-specific backend or client architecture.
- Milestone 10 established branch-capable run graphs and the node vocabulary needed for deeper encounters. Shrine and Chaos remained test-only pending real Swamp content.
- Milestone 11 established Codex/dialogue/objective authority. Use it where Swamp content needs knowledge or conversation; do not add generic story/history flags.
- Milestone 9 established the Kin/Wrong Machine reconstruction architecture. Package 4 of this milestone owns Frog Kin and any current Swamp/Wrong Machine integration; Package 1 may author references/identities needed for that later work but must not implement reconstruction or machine access early.
- Mystic Cave onboarding knowledge does not grant `capability.wrong_machine_access`, physical machine possession, reconstruction authority, or Swamp progression.
- Backend/MySQL remains authoritative; Phaser is the gameplay client; Angular is the host/auth/account shell; authored definitions belong in canonical JSON.

#### Purpose
Establish the minimum canonical Swamp/Frogmen content model needed for later Milestone 13 packages: region identity and availability relationship, Frogmen enemy/combat roster, boss identity, safe authored references, and any foundational rewards/knowledge/progression identities that must exist before the run graph can be authored. This package should make the content registry understand the third region and its combatants without pretending the full Swamp lifecycle is already playable.

#### Canonical Package 1 semantics
1. **Canonical allocation.** Swamps are the third ordinary region; Frogmen are its native enemy family; Frog Kin is its associated lineage. Preserve those identities consistently across authored content and player-safe projections.
2. **Generic region architecture.** Express Swamp through the same region/content/enemy/combat schemas used by Farm and Mountains. No Swamp-specific controller/service/repository/state table or Phaser ownership.
3. **Frogmen combat foundation.** Author a coherent minimum Frogmen roster and boss through the existing enemy/combat definitions, including deterministic abilities/stats/formations required for later Combat/Boss nodes. Prefer the approved/current content vocabulary and validate all references.
4. **Not playable yet.** Package 1 must not expose Swamp as a startable complete region until the run graph, encounters, terminal lifecycle, and progression consequences are implemented in later packages. If a region definition must exist now, gate availability through existing authoritative unlock/content semantics rather than client hiding.
5. **Progression identities, not premature mechanics.** Author only reward/unlock/Codex/dialogue/capability identities that later Swamp packages concretely require. Do not grant them or invent a new progression layer in Package 1.
6. **Wrong Machine boundary.** Do not make historical prototype custody/recovery behavior canonical by accident. Do not grant `capability.wrong_machine_access`, reconstruction, physical possession, or machine recovery here. If later Swamp gameplay requires a durable custody/access decision, Package 4 will resolve and record it against current architecture.
7. **Frog Kin boundary.** Frog Kin identity/reference may be authored where required for validation, but reconstruction recipes, first-unlock behavior, deterministic repeats, and player-facing machine integration belong to Package 4.
8. **Encounter-depth boundary.** Shrine/Chaos/Hazard/dialogue node semantics and the complete Swamp graph belong to Package 2. Package 1 may provide referenced content primitives only when required by the authored-content schema.
9. **Safe projection.** Browser-facing content must reveal only the same class of player-safe region/enemy/combat information already accepted for Farm/Mountains. Do not expose hidden rewards, future graph topology, private progression prerequisites, or deferred Wrong Machine mechanics.
10. **No prototype authority.** Prototype source/content may be inspected as behavioral/design evidence only. Any retained Frogmen roster, boss, naming, tuning, or flavor must be reconciled with current canonical docs and authored JSON rather than copied because it existed before.
11. **Visual scope discipline.** Reuse existing placeholder/accepted asset fallbacks as necessary. Final Frogmen art and the broad visual overhaul are not Package 1 blockers unless current content validation requires an asset identity.
12. **Test value discipline.** Add structural/semantic and deterministic combat tests that prove reusable behavior and reference integrity. Avoid content-literal assertion sprawl that duplicates JSON catalogs; production structural gates should own production content validity.

#### Required implementation inventory
Before changing content, inspect current Farm and Mountains region definitions, enemy/combat authored schemas, boss definitions, unlock/availability relationships, safe content projection, deterministic combat adapters, production content validation, and any existing Frogmen/Frog Kin placeholders. Use `agent/CONTEXT_ROUTER.md` to load only the canonical architecture/system documents needed for decisions.

#### Minimum proof
1. Production content validates with a canonical Swamp region identity and Frogmen family references.
2. Frogmen combatants and the Swamp boss resolve through existing enemy/combat schemas without special-case backend code.
3. Representative Frogmen encounters can be deterministically adapted/simulated through the existing combat engine using fixture or focused authored definitions.
4. Swamp is not prematurely startable as a complete region from normal player state.
5. No Package 1 path grants Frog Kin, Wrong Machine access/reconstruction, physical machine state, or later Swamp terminal rewards.
6. Player-safe projections do not leak future Swamp graph topology, hidden rewards/prerequisites, or deferred machine mechanics.
7. Farm and Mountains content/combat validation remains unchanged/regression-safe.
8. Production structural/content gates, focused backend tests, frontend contract tests if affected, and the full applicable package verification pass.

#### Verification
Run focused authored-content validation and combat/reference tests for the new Swamp/Frogmen foundation. Add negative validation cases for malformed references/roles/formations where the existing schema does not already cover them. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`. If browser contracts change, include strict frontend parser/contract coverage and confirm no private authored fields are projected.

#### Scope exclusions
- complete Swamp run graph or startable Swamp lifecycle;
- production Shrine/Chaos/Hazard node behavior beyond primitives required by content validation;
- Swamp boss/Exit terminal reward application and next-region consequences;
- Frog Kin reconstruction recipe or first/repeat reconstruction behavior;
- Wrong Machine physical custody/access/recovery/reconstruction changes;
- Phaser Swamp routing/presentation work beyond contract changes strictly required by the foundation;
- broad visual redesign or final Frogmen asset production;
- parity audit itself;
- Milestone 14 hardening/cutover.

#### Completion evidence required
Record implementation SHA; canonical content files/identities added or changed; Farm/Mountains patterns reused; Frogmen roster/boss and deterministic combat proof; region availability/non-playability proof; safe-projection result; explicit confirmation that Frog Kin and Wrong Machine mechanics were not advanced; focused test counts; production content revision if applicable; and full package-gate results.

Leave Package 1 **In Progress** for architectural review. Do not activate Package 2 automatically from the coding agent.
