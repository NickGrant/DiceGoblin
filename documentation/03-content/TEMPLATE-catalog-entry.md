---
Title: "Catalog Entry Template"
Status: Canonical
Last Updated: 2026-08-01
Owner: Content Design
Depends On:
  - documentation/03-content/00-content-source-map.md
Category: 03-content
Tags:
  - content
  - template
---

# Catalog Entry Template

## Purpose

Use this template when adding canonical content catalogs for unit types, kin, enemies, biomes, items, affixes, encounters, dialogue groups, or codex categories.

A completed catalog defines the intended content for its scope. Implementation data must be maintained to match it.

## Scope

- Content category:
- Player-facing surfaces:
- Related system docs:
- Related lore docs:

## Reading the Catalog

- Define the meaning and order of compact value columns.
- Explain any stable identifier or content-status conventions.
- Point readers to system documents for mechanics that should not be duplicated here.

## Entries

| Key | Display name | Classification | Authored values | Content status | Notes |
| --- | --- | --- | --- | --- | --- |
| `example_key` | Example Name | Example category | Replace with category-specific values. | Active / Planned / Retired | Describe the intended player-facing identity. |

## Open Questions

- List unresolved content-design or narrative decisions.
- Do not use this section to make implementation drift ambiguous; track mismatches as engineering work.

## Maintenance Notes

- Update this catalog before or alongside implementation changes.
- Treat undocumented implementation-only records as non-canonical until a content decision adds them here.
- Keep rules and formulas in system docs.
- Keep APIs, storage details, migrations, and implementation paths in technical docs.
- Keep story implications in lore docs unless the catalog is defining a concrete content label or identity.
