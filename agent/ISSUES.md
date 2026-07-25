# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Wrong Machine and Kin Foundation

### KRB-002: Add account-level lineage unlock state

**Milestone:** Wrong Machine and Kin Foundation
**Status:** Open
**Priority:** High

#### Problem

The Wrong Machine loop needs account-level lineage ownership before Pig Kin reconstruction can grant a durable unlock. Basic Goblin should stay available to every account without extra storage, while new kin unlocks need a shared service/API surface.

#### Acceptance Criteria

- Store explicit lineage unlocks in the existing `user_unlocks` table under the `lineage` namespace.
- Treat Basic Goblin as the implicit default lineage for every account.
- Expose owned lineages in the profile payload.
- Expose the lineage catalog and owned lineages in debug/dev catalog surfaces.
- Keep old migrations untouched.
- Do not add new `region_items` dependencies.
