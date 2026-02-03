# Core Gameplay Loop

The core loop is intentionally short and repeatable.

1. Select Region
2. Select a saved Warband
   - team is locked during a run
   - team composition/formation can only be changed between runs and at rest nodes
3. Energy cost applied
4. Explore Procedurally Generated Map
   - 5–8 encounters
   - ~75% combat encounters
   - Static "node" encounters mixed in
5. Boss Encounter (unlocked after map completion)
6. Loot Rewards
   - includes deterministic XP from combat/boss encounters (awarded to surviving fielded units)
7. Advancement Phase
   - Automatically spend XP to level (per unit-type max level caps; no XP accrues at cap)
   - Equip dice
   - Combine units
8. Repeat

Energy is a real-world time-gated resource that limits daily progression.
