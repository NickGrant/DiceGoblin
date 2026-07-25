# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Wrong Machine and Kin Foundation

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
