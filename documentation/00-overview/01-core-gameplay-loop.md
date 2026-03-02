# Core Gameplay Loop

Status: active  
Last Updated: 2026-03-02  
Owner: Product  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

The core loop is intentionally short and repeatable.

1. Select region.
2. Select a saved warband (squad).
  - squad is locked during a run
  - squad composition/formation can only be changed between runs and at rest nodes
3. Energy cost is applied.
4. Explore procedurally generated map.
  - 5-8 encounters
  - about 75% combat encounters
  - static node encounters mixed in
5. Boss encounter unlocks after map completion.
6. Loot rewards are applied.
  - includes deterministic XP from combat/boss encounters (awarded to surviving fielded units)
7. Advancement phase.
  - spend XP to level (per unit-type max level caps; no XP accrues at cap)
  - equip dice
  - combine units
8. Repeat.

Energy is a real-world time-gated resource that limits daily progression.
