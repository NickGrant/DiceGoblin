# Core Gameplay Loop

Status: active  
Last Updated: 2026-03-04  
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
6. Exit node is always visible on the run map but is only reachable from the boss node path.
7. Loot rewards are applied.
  - includes deterministic XP from combat/boss encounters (awarded to surviving fielded units)
8. Advancement phase.
  - level-up math is backend-authoritative and auto-applied at rest finalization and run cleanup
  - equip/unequip dice (between runs; during runs only at rest workflow)
  - promote units manually between runs or during an open rest workflow (primary unit persists, secondary units are consumed)
9. Repeat.

Energy is a real-world time-gated resource that limits daily progression.
