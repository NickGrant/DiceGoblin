---
Title: "vNext Content Source Map"
Status: Transitional vNext Reference
Last Updated: 2026-09-12
Owner: Content Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-phaser-client-architecture.md
Category: 03-content
Tags: [content, source-map, vnext]
---

# vNext Content Source Map

## Authority
Canonical vNext authored gameplay data lives in Git-tracked JSON and is loaded into the server ContentRegistry. Static Markdown catalogs in this folder are design references during migration, not runtime sources of truth.

MySQL stores mutable player/generated state and stable IDs referencing authored definitions; it does not maintain a required duplicate authored catalog.

The implemented Warband catalog is split by domain under `backend/content/kin`, `backend/content/units`, `backend/content/abilities`, and `backend/content/dice`. Prototype PHP constants, SQL seed rows, and Angular catalogs remain migration evidence only and do not override those definitions.

## Client Exposure
Canonical JSON is not shipped wholesale to Phaser. Build/runtime projection uses an allowlist: new canonical fields are server-private by default. Public presentation definitions may ship in the client projection; player-specific discoveries/reveals are authorized through PHP; hidden probabilities, pools, encounter composition, prerequisites, and internal tuning remain server-only.

## Migration Rule
When a content domain enters its vNext milestone:
1. inspect retained Markdown and current implementation for useful game-design behavior;
2. author the intended canonical JSON using durable stable IDs;
3. validate structural and semantic references in CI;
4. define the safe client projection for fields Phaser needs;
5. remove or reduce obsolete Markdown so two authored sources do not remain.

Do not generate canonical content by bulk-copying prototype SQL rows or service constants without reconciling them against accepted vNext decisions.
