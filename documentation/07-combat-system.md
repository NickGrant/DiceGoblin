# Combat System

## Grid
- Each side has it's own fixed 3x3 grid; including neutral forces if applicable
- Soft screening (no hard blocking)
- "occupancy grid" is derivable from unit positions

## Positioning Effects
- Front row:
  - Increased melee damage dealt
  - Increased damage received
- Back row:
  - Reduced melee damage taken
  - Ranged/special bonuses possible

## Round / Tick / Speed 
- Speed range: 1-20
- A round is exactly 20 ticks
- Each ability and some status effects have a speed value
- Actions trigger when tick % speed === 0
  - Speed 4 → triggers 5 times per round
  - Speed 11 → triggers once
- lower speed equates to more frequent actions
- multiple actions in the same tick all execute
- If a unit dies, it performs no further actions, including any actions later in the same tick

## Timeline
- round: 1..N
- tick: 1..20

## Tick Processing Order
- Player Status Phase
- Enemy Status Phase
- Neutral Status Phase
- Player Action Phase
- Enemy Action Phase
- Neutral Action Phase

## Unit Action Order
- Units should act in ascending unitId order
- Actions occuring on the same tick should execute based on their order property

## Automation
- No player-controlled movement during combat
- Units decide actions based on internal logic
- Combat is deterministic aside from dice rolls
