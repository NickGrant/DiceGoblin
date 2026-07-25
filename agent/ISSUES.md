# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Wrong Machine and Kin Foundation

### PIF-001: Add generic progression item foundation

**Milestone:** Wrong Machine and Kin Foundation
**Status:** Open
**Priority:** High

#### Problem

The roadmap now calls for lineage materials and boss catalysts to use a generic item foundation instead of extending the older `region_items` progression flow. The current region-item tables are too narrow for machine inputs, future consumables, source metadata, and reward/profile consistency.

#### Acceptance Criteria

- Add a generic item catalog and per-user item ownership table through new migrations only.
- Seed at least one Pig Kin lineage material and one Farm boss catalyst.
- Route new progression reward/profile/debug work through the generic item foundation.
- Do not add new dependencies on `region_items` or `user_region_items`.
- Keep reward claims and item grants idempotent, with non-negative quantities.
- Add focused backend coverage for duplicate grants and profile serialization.

### KRB-001: Canonicalize kin and lineage terminology

**Milestone:** Wrong Machine and Kin Foundation
**Status:** Open
**Priority:** High

#### Problem

The approved language is "goblins and goblin-kin," shortened to "kin," with lineage as the account-level unlock track. Legacy `splice_variant` names remain in storage and API fields, but new player-facing copy and new code concepts should not reinforce that terminology.

#### Acceptance Criteria

- Use kin/lineage language in new documentation, UI copy, and service/API additions.
- Keep old migrations untouched.
- Plan any `splice_variant` storage/API rename as a forward migration with compatibility handling.
- Ensure visible unit, reward, shop, and roster copy renders legacy `*-Spliced` values as `* Kin`.
