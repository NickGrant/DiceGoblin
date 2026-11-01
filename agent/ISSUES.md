# Active Execution Issue

## Milestone 13 - Swamp and Parity Audit

### Milestone 13 Package 1 - Canonical Swamp and Frogmen authored-content foundation

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 12 is complete after accepted fresh-player onboarding UAT and its focused corrections. The next vNext proof is the Swamps: the canonical third biome, with Frogmen as its native enemy family and Frog Kin as its associated lineage. Farm and Mountains already prove the generic region/run/combat architecture at two regions, while Milestones 7-12 added economy, permanent progression, Kin/Wrong Machine, branch-capable encounter depth, Codex/dialogue/objectives, and onboarding. Before implementing the complete Swamp run, Package 1 must establish a current canonical authored-content foundation that composes those accepted systems without reviving prototype-specific architecture or prematurely implementing later package behavior.

#### Accepted baseline
- Milestone 12 UAT was explicitly accepted by the user on 2026-10-31 after corrections through `c40959b37fe2d08a4b01fc8673b103a5ab8fe2b9`.
- `documentation/07-development-path/01-base-game-content-roster.md` canonically assigns Swamps -> Frogmen -> Frog Kin.
- Farm and Mountains use the accepted generic authored region/run/combat/event/reward architecture. Swamp must not introduce a region-specific backend or client architecture.
- Milestone 10 established branch-capable run graphs and the node vocabulary needed for deeper encounters. Shrine and Chaos remained test-only pending real Swamp content.
- Milestone 11 established Codex/dialogue/objective authority. Use it where Swamp content needs knowledge or conversation; do not add generic story/history flags.
- Milestone 9 established the Kin/Wrong Machine reconstruction architecture.
- Mystic Cave onboarding knowledge does not grant `capability.wrong_machine_access`, physical machine possession, reconstruction authority, or Swamp progression.
- **Canonical Swamp progression decision:** the first successful defeat of the Swamp boss grants `capability.wrong_machine_access`. This is the point at which the Wrong Machine becomes unlocked/recovered for normal reconstruction gameplay. Repeat boss defeats must not duplicate the permanent capability. Package 3 owns the actual authoritative boss reward application; Package 1 may author the required reward/capability reference but must not grant it early.
- Package 4 owns Frog Kin reconstruction integration using the capability earned from the Swamp boss; it must not invent another Wrong Machine access path.
- Backend/MySQL remains authoritative; Phaser is the gameplay client; Angular is the host/auth/account shell; authored definitions belong in canonical JSON.

#### Purpose
Establish the minimum canonical Swamp/Frogmen content model needed for later Milestone 13 packages: region identity and availability relationship, Frogmen enemy/combat roster, boss identity, safe authored references, and foundational rewards/knowledge/progression identities required before the run graph and boss lifecycle can be authored. This package should make the content registry understand the third region and its combatants without pretending the full Swamp lifecycle is already playable.

#### Canonical Package 1 semantics
1. **Canonical allocation.** Swamps are the third ordinary region; Frogmen are its native enemy family; Frog Kin is its associated lineage. Preserve those identities consistently across authored content and player-safe projections.
2. **Generic region architecture.** Express Swamp through the same region/content/enemy/combat schemas used by Farm and Mountains. No Swamp-specific controller/service/repository/state table or Phaser ownership.
3. **Frogmen combat foundation.** Author a coherent minimum Frogmen roster and boss through the existing enemy/combat definitions, including deterministic abilities/stats/formations required for later Combat/Boss nodes. Prefer the approved/current content vocabulary and validate all references.
4. **Not playable yet.** Package 1 must not expose Swamp as a startable complete region until the run graph, encounters, terminal lifecycle, and progression consequences are implemented in later packages. If a region definition must exist now, gate availability through existing authoritative unlock/content semantics rather than client hiding.
5. **Progression identities, not premature mechanics.** Author only reward/unlock/Codex/dialogue/capability identities that later Swamp packages concretely require. In particular, Package 1 may establish the authored reward/reference that Package 3 will use to grant `capability.wrong_machine_access` on the first successful boss defeat, but Package 1 must not grant it.
6. **Wrong Machine grant point.** The canonical access/recovery event is the first successful Swamp boss defeat. Do not grant `capability.wrong_machine_access`, reconstruction, or physical machine functionality from ordinary encounters, dialogue, onboarding knowledge, region entry, or Package 1 content loading. Do not create a parallel custody/access state; the existing permanent capability is the durable result.
7. **Frog Kin boundary.** Frog Kin identity/reference may be authored where required for validation, but reconstruction recipes, first/repeat reconstruction behavior, and player-facing machine integration belong to Package 4, after the boss capability grant implemented in Package 3.
8. **Encounter-depth boundary.** Shrine/Chaos/Hazard/dialogue node semantics and the complete Swamp graph belong to Package 2. Package 1 may provide referenced content primitives only when required by the authored-content schema.
9. **Safe projection.** Browser-facing content must reveal only the same class of player-safe region/enemy/combat information already accepted for Farm/Mountains. Do not expose hidden rewards, future graph topology, private progression prerequisites, or the boss's Wrong Machine capability reward before gameplay reveals it unless existing safe reward projection semantics explicitly require it.
10. **No prototype architecture authority.** Prototype source/content may be inspected as behavioral/design evidence only. Retained Frogmen roster, boss, naming, tuning, or flavor must be reconciled with current canonical docs and authored JSON rather than copied because it existed before. The first-boss Wrong Machine unlock described above is a current canonical decision and is not optional historical behavior.
11. **Visual scope discipline.** Reuse existing placeholder/accepted asset fallbacks as necessary. Final Frogmen art and the broad visual overhaul are not Package 1 blockers unless current content validation requires an asset identity.
12. **Test value discipline.** Add structural/semantic and deterministic combat tests that prove reusable behavior and reference integrity. Avoid content-literal assertion sprawl that duplicates JSON catalogs; production structural gates should own production content validity.

#### Required implementation inventory
Before changing content, inspect current Farm and Mountains region definitions, enemy/combat authored schemas, boss definitions, unlock/availability relationships, safe content projection, deterministic combat adapters, production content validation, existing Wrong Machine capability/reward definitions, and any existing Frogmen/Frog Kin placeholders. Use `agent/CONTEXT_ROUTER.md` to load only the canonical architecture/system documents needed for decisions.

#### Minimum proof
1. Production content validates with a canonical Swamp region identity and Frogmen family references.
2. Frogmen combatants and the Swamp boss resolve through existing enemy/combat schemas without special-case backend code.
3. Representative Frogmen encounters can be deterministically adapted/simulated through the existing combat engine using fixture or focused authored definitions.
4. Swamp is not prematurely startable as a complete region from normal player state.
5. Package 1 may author the future first-boss Wrong Machine capability reward/reference, but no Package 1 path actually grants Frog Kin, `capability.wrong_machine_access`, reconstruction, physical machine functionality, or later Swamp terminal rewards.
6. Player-safe projections do not leak future Swamp graph topology, hidden rewards/prerequisites, or deferred machine mechanics.
7. Farm and Mountains content/combat validation remains unchanged/regression-safe.
8. Production structural/content gates, focused backend tests, frontend contract tests if affected, and the full applicable package verification pass.

#### Verification
Run focused authored-content validation and combat/reference tests for the new Swamp/Frogmen foundation. Add negative validation cases for malformed references/roles/formations where the existing schema does not already cover them. Verify any authored Wrong Machine reward reference resolves through the existing permanent capability/unlock model without being applied in Package 1. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`. If browser contracts change, include strict frontend parser/contract coverage and confirm no private authored fields are projected.

#### Scope exclusions
- complete Swamp run graph or startable Swamp lifecycle;
- production Shrine/Chaos/Hazard node behavior beyond primitives required by content validation;
- actual Swamp boss reward application, including the first-defeat `capability.wrong_machine_access` grant (Package 3);
- Frog Kin reconstruction recipe or first/repeat reconstruction behavior (Package 4);
- any second/alternate Wrong Machine access grant path;
- Phaser Swamp routing/presentation work beyond contract changes strictly required by the foundation;
- broad visual redesign or final Frogmen asset production;
- parity audit itself;
- Milestone 14 hardening/cutover.

#### Completion evidence required
Record implementation SHA; canonical content files/identities added or changed; Farm/Mountains patterns reused; Frogmen roster/boss and deterministic combat proof; region availability/non-playability proof; any authored future Wrong Machine boss-reward reference and proof it is not granted yet; safe-projection result; focused test counts; production content revision if applicable; and full package-gate results.

Leave Package 1 **In Progress** for architectural review. Do not activate Package 2 automatically from the coding agent.
