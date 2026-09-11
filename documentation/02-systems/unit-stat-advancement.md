---
Title: "Unit Stat Advancement"
Status: Canonical
Last Updated: 2026-09-10
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-storage-model.md
Category: 02-systems
Tags: [systems, units, progression]
---

# Unit Stat Advancement

Owned unit instances persist level and XP. Authored unit types define base/growth values and progression limits. Resolved combat stats are calculated from authored type values, unit level/progression, kin effects, equipped/passive effects, and applicable run/combat modifiers.

The currently established core stats are HP, Attack, Defense, Precision, and Resolve. Do not add a persistent Speed stat merely to represent the combat tick scheduler.

Outside a run, unit presentation may derive current HP from max HP. During a run, current HP is authoritative run-specific state and survives between nodes until healed, changed by effects, or the run terminates.

XP/progression granted by authoritative resolution is applied as part of that command's transaction. It is not deferred to a separate reward-claim operation.

Exact XP curves, growth values, and level formulas must be deliberately recovered/reconciled into canonical authored JSON when unit progression is implemented rather than inferred from prototype services or removed catalogs.
