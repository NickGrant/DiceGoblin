---
Title: "Unit Promotion"
Status: Canonical Boundary
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-storage-model.md
  - documentation/02-systems/ability-loadouts-and-dice-binding.md
Category: 02-systems
Tags: [systems, units, promotion]
---

# Unit Promotion

Promotion remains part of the intended unit progression loop, but detailed eligibility/cost tuning is deferred to the permanent-progression milestone rather than copied from prototype implementation.

## vNext Durable Rules
- Promotion changes the surviving unit instance's current unit type/promotion state rather than replacing its identity.
- Promotion history is persisted.
- Permanently unlocked abilities are durable unit truth across progression.
- A capstone is an ability. vNext does not maintain a separate capstone state/table or capstone mutation endpoint.
- Promotion/configuration that affects a unit participating in an active run is locked until the run terminates.
- Any consumed/retired secondary assets use the normal lifecycle/cleanup model and must be detached from invalid references transactionally.

Exact required unit count, level threshold, branch eligibility, and costs should be explicitly re-approved when Milestone 8 implements promotion. Do not recover those values implicitly from deleted prototype promotion documents.
