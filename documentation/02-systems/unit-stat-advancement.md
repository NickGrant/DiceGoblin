---
Title: "Unit Stat Advancement"
Status: Canonical
Last Updated: 2026-09-17
Owner: Systems Design + Engineering
Depends On:
  - documentation/07-development-path/vnext-authored-content-model.md
  - documentation/07-development-path/vnext-storage-model.md
Category: 02-systems
Tags: [systems, units, progression]
---

# Unit Stat Advancement

Owned unit instances persist level and XP. The canonical checked-in unit types define base stats and growth per level. For HP, Attack, Defense, Precision, and Resolve, the base level stat is `base stat + growth_per_level × (level - 1)`, using the owned instance's persisted level (at least 1). Level 1 equals the authored base stat exactly. Resolved HP is max HP for this base rule. Future kin, passive, dice, run, and combat modifiers are separate layers; they do not silently alter the base level rule.

The currently established core stats are HP, Attack, Defense, Precision, and Resolve. Do not add a persistent Speed stat merely to represent the combat tick scheduler.

Outside a run, unit presentation may derive current HP from max HP. A newly created run initializes each participating unit's mutable current HP to its resolved max HP. During a run, current HP is authoritative run-specific state and survives between nodes until healed, changed by effects, or the run terminates.

XP/progression granted by authoritative resolution is applied as part of that command's transaction. It is not deferred to a separate reward-claim operation.

`unit_instances.xp` is progress within the unit's current level, not lifetime cumulative XP. At level `L`, advancing to the next level requires `100 * L` XP. An XP grant adds to the current remainder, repeatedly subtracts the current level's threshold and increments the level while enough XP remains, then persists the final level and remainder. A single grant can therefore advance multiple levels. Unit type and tier do not affect the threshold, promotion does not reset level or XP, and no maximum-level cap is currently enforced. Arithmetic or persistence overflow rejects the owning transaction rather than clamping.

All exact participating units targeted by a participating-unit XP reward receive the authored amount individually, including units defeated during combat. Level changes apply immediately to later stat resolution, but they do not heal or proportionally adjust `run_unit_state.current_hp`; existing numeric run HP remains until a legitimate healing or other effect changes it.
