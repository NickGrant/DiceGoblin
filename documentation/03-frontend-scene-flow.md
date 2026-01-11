# Frontend Scene Flow

## Scene List (MVP)

1. BootScene
2. LandingScene (unauthenticated only)
3. HomeScene
4. RegionSelectScene
5. MapExplorationScene
6. CombatScene
7. LootScene
8. RestScene
9. BossScene
10. WarbandScene
11. DiceInventoryScene
12. DiceDetailsScene
13. UnitDetailsScene

## BootScene Responsibilities
- Fetch `/api/v1/session`
- Determine authentication state
- Route user:
  - Authenticated → HomeScene
  - Unauthenticated → LandingScene

BootScene is the single source of truth for auth.

## LandingScene
- Title screen
- Login with Discord button
- No guest play
- No game state decisions

## HomeScene (MVP)
- Displays user identity
- Logout button
- Placeholder navigation for future systems

## RegionSelectScene
- Allows users to start a run 

## MapExplorationScene
- Allows users to see the nodes in a run
- Allows users to select from any unlocked node

## CombatScene
- Allow players to assign teams
- Initiates server-side combat generation
- Shows outcome of the server-resolved combat and supports replay via the combat log

## BossScene
- Specialized combat only available after the rest of the nodes have been cleared
- Upon completion, ends the run

## LootScene
- Node that presents players with rewards

## RestScene
- Node to allow units to recharge.

## WarbandScene
- Allows player to view and interact with their collected units
- Serves as navigation hub for unit & dice management

## DiceInventoryScene
- Show all dice players own
- Allow navigation to individual dice

## UnitDetailsScene
- Show details about a specific unit instance
- Allows for user to promote unit
- Allows for user to equip dice to unit

## DiceDetailsScene
- Information about specific dice instances