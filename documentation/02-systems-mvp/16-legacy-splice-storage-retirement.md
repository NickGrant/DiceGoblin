# Legacy Splice Storage Retirement
----

Status: active
Last Updated: 2026-07-25
Owner: Engineering
Depends On: `documentation/02-systems-mvp/13-wrong-machine-and-kin.md`, `documentation/01-architecture/03-backend-api-contracts.md`, `documentation/01-architecture/04-data-model.md`

## Purpose

- Inventory current legacy `splice_variant` storage and API compatibility usage.
- Define the safe forward path for renaming durable kin identity storage.
- Keep player-facing terminology on goblins, goblin-kin, kin, and lineages while old clients remain compatible.

## Current Inventory

Durable storage:

| Surface | Current legacy name | Future canonical name | Notes |
| --- | --- | --- | --- |
| Unit instance kin column | `unit_instances.splice_variant_slug` | `unit_instances.kin_slug` | Current source of truth for a unit's kin identity and combat modifiers. |
| Kin modifier catalog | `splice_variants` | `kin_variants` or `kin_definitions` | Stores slug, display copy, stat modifiers, enabled state, and grant weight. |
| Seed migrations | `59_unit_splice_variant_foundation.sql`, `63_seed_splice_variants.sql`, `74_seed_pig_kin_variant.sql` | New forward migrations only | Existing migrations must remain immutable. |

Backend code:

| Surface | Current usage | Migration note |
| --- | --- | --- |
| `SpliceVariantService` | Catalog lookup, weighted random rolls, metadata description. | Rename or wrap behind `KinVariantService` after DB dual-read support lands. |
| `OwnedUnitGrantService` and `UserAssetGrantService` | Accept optional explicit `splice_variant_slug` and return legacy fields. | Add `kin_slug` parameters and responses first, then keep legacy aliases until clients migrate. |
| `UnitRepository`, `RunSummaryBuilder`, `DeterministicRunNodeResolver` | Join `splice_variants` and read `unit_instances.splice_variant_slug` for stats and reward display. | Switch reads to canonical columns behind a compatibility helper. |
| Controllers and DTO comments | Some response docs still list `splice_variant_*`. | New response docs should list `kin_*` first and mark legacy fields as compatibility-only. |

Frontend code:

| Surface | Current usage | Migration note |
| --- | --- | --- |
| API models | `kin_*` aliases and legacy `splice_variant_*` fields coexist. | Keep legacy fields optional until backend removal is scheduled. |
| Unit/reward/shop display | Formatters prefer `kin_*` and normalize `*-Spliced` copy to `* Kin`. | Keep tests proving visible labels remain kin terminology. |
| Internal CSS, IDs, and variable names | Some names still include splice. | Rename opportunistically only when touching those components; avoid churn-only UI diffs. |

## Compatibility Plan

Phase 1: Add canonical read aliases.

- Add `kin_slug` storage read aliases in repositories and service return types while continuing to populate `splice_variant_*` compatibility fields.
- Let incoming explicit grants accept `kin_slug` first and fall back to `splice_variant_slug`.
- Keep `splice_variants` as the physical catalog table during this phase.

Phase 2: Add forward DB migration.

- Add `unit_instances.kin_slug` with a default of `basic_goblin`.
- Backfill `kin_slug` from `splice_variant_slug`.
- Add an index for `user_id, kin_slug` if query patterns need it.
- Keep both columns in sync at write boundaries until all backend readers move to `kin_slug`.

Phase 3: Rename service/API ownership.

- Introduce `KinVariantService` as the canonical service name and keep `SpliceVariantService` as a thin compatibility wrapper.
- Update backend docs, DTO comments, and tests to assert `kin_*` fields as primary.
- Update frontend model and component code to stop depending on legacy fields except as optional fallback.

Phase 4: Remove compatibility after a release window.

- Stop writing legacy `splice_variant_slug`.
- Remove `splice_variant_*` response fields only after frontend and any debug tools no longer read them.
- Rename or replace `splice_variants` through a final migration after no code reads it directly.

## Test Plan For The Rename

Backend tests required before Phase 2 ships:

- Profile payload includes `kin_*` canonical fields and legacy `splice_variant_*` aliases with identical values.
- Existing accounts with only `splice_variant_slug` backfill to `kin_slug`.
- Unit grants accept `kin_slug`, preserve explicit authored kin grants, and continue accepting legacy `splice_variant_slug`.
- Random kin rewards still use Basic Goblin plus owned lineages only.
- Combat stat modifiers are identical before and after the storage switch.
- Run summaries and reward payloads expose kin metadata for newly granted and existing units.

Frontend tests required before Phase 3 ships:

- Unit details, warband, shop, run loot, reward summary, and unit grid surfaces render `kin_*` data without visible splice terminology.
- Legacy fallback data still renders as kin labels while compatibility fields remain available.
- Debug/profile lineage surfaces continue to show owned lineages clearly.

## Current Decision

Do not rename the physical table or column in the current 7/25 follow-up stack. The current work should keep player-facing copy aligned on kin terminology, add `kin_*` aliases for new API work, and leave durable storage renaming to the dedicated migration phases above.
