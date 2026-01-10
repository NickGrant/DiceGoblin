# Dice System

Dice are the primary progression and customization system.

## Dice Properties
- Die size: d4 → d6 → d8 → d10
- Bonuses:
  - always active
  - rarity determines number of bonus slots
    - Common : 0
    - Uncommon : 1
    - Rare : 2

## Dice Pools
- Each unit has a limited dice pool
- Dice are consumed when an ability with cost > 0 is executed
- Dice are consumed from largest → smallest
- Some things may consume multiple dice based on cost
- When the smallest die is used, the pool immediately refreshes
- When multiple dice are requested and there are not enough dice in the pool, take the maximum number of dice from the pool, refresh the pool, and then take however many remaining dice are needed
- Abilities may trigger refreshes