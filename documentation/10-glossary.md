# Game Glossary (Milestone 0)

This document defines the canonical terminology used throughout the game’s design, implementation, and documentation.  
Any term used in code, UI, or design discussions must appear in this glossary.

---

## 1. Structural & Game Loop Terms

### Game
The complete interactive system, including rules, content, UI, audio, and meta systems.

### Run
A single playthrough attempt beginning from a defined starting state and ending in either victory or failure. Most progression and resources are scoped to a single run unless explicitly marked as persistent.

### Game Mode
A high-level operational state of the game that determines available interactions and rules (e.g., Exploration Mode, Combat Mode).

### Phase
A discrete step within a game mode that structures player and system actions.

### Core Loop
The repeating gameplay structure that defines moment-to-moment play, typically: Exploration → Encounter → Resolution → Reward.

### Failure State
A condition that ends the current run unsuccessfully and triggers run termination logic.

### Victory State
A condition that ends the current run successfully and triggers completion rewards and unlocks.

---

## 2. World & Exploration Terms

### Map
The full navigable structure of nodes and paths that define a run’s spatial progression.

### Node
A single location on the map that may contain encounters, events, rewards, or narrative elements.

### Path
A connection between two nodes that defines possible movement and may include costs, risks, or gating conditions.

### Biome
A thematic and mechanical grouping that influences encounter types, enemies, and modifiers.

### Region / Act
A large-scale grouping of nodes representing a stage of progression with escalating difficulty.

### Discovery
The act of revealing previously hidden nodes, paths, or information through exploration.

### Exploration Event
A non-combat interaction triggered by entering or interacting with a node.

---

## 3. Encounter System Terms

### Encounter
A structured challenge that taxes player resources and must be resolved before progression continues.

### Encounter Type
The category of encounter, such as combat, puzzle, narrative, hazard, or merchant.

### Encounter Difficulty
A relative measure of threat used to scale challenge and rewards.

### Encounter Slots
The required or allowed number of teams participating in an encounter.

### Resolution
The outcome of an encounter, such as success, partial success, or failure.

### Reward
Resources, loot, or progression granted as a result of encounter resolution.

---

## 4. Combat System Terms

### Combat
A turn-based or structured battle mode with its own ruleset and UI.

### Tick
The atomic combat step, 20 ticks per round

### Round
A complete cycle of exactly 20 ticks.

### Speed
Determines which ticks a unit acts on

### Action
A primary activity a unit may perform on its turn.

### Reaction
An out-of-turn response triggered by specific conditions.

### Target
The unit, area, or object an action is applied to.

### Range
The distance or constraint governing whether an action can affect a target.

### Damage
A reduction of health or durability caused by an action.

### Status Effect
A temporary or persistent modifier that alters unit behavior or stats.

### Death / Defeat
The state in which a unit can no longer participate in combat.

### Retreat
A voluntary disengagement from combat with defined consequences.

---

## 5. Entity & Control Terms

### Unit
A single actor in the game capable of taking actions.

### Warband
A collection of units managed together by the player.

### Team
A subset of units participating together within an encounter.

### Player-Controlled Unit
A unit directly controlled by the player.

### Allied Unit
A non-player unit that assists the player.

### Enemy Unit
A hostile unit controlled by the game.

### Neutral Unit
A unit that is neither allied nor hostile by default.

### AI Behavior
The logic governing decision-making for non-player units.

---

## 6. Progression & Build Terms

### Level
A discrete step of progression that increases unit capability.

### Experience (XP)
A resource used to gain levels or progression milestones.

### Stat
A numeric attribute that influences unit performance.

### Trait
A permanent characteristic that defines a unit’s identity or behavior.

### Talent / Perk
A selectable progression option that modifies gameplay.

### Augment
A modular component that alters or enhances a unit’s capabilities.

### Synergy
A positive interaction between multiple mechanics or systems.

### Anti-Synergy
An intentional friction that limits overpowered combinations.

### Build
The aggregate configuration of a unit’s traits, talents, stats, and augments.

---

## 7. Loot & Economy Terms

### Loot
Any reward obtained during a run, including items and resources.

### Item
A discrete object with mechanical effects.

### Rarity
A classification indicating item power and drop frequency.

### Affix / Modifier
A property that alters an item’s base behavior.

### Currency
A spendable resource used in shops or upgrades.

### Vendor
An encounter or entity that exchanges currency for goods or services.

### Upgrade
A permanent improvement applied to a unit or item.

---

## 8. Meta-Progression & Persistence Terms

### Meta Progression
Progression systems that persist across runs.

### Unlock
Content that becomes available after meeting specific conditions.

### Carryover
Resources or effects that persist between runs.

### Reset
The clearing of run-scoped state at run end.

### Save State
A serialized snapshot of the current game state.

---

## 9. UX & Information Terms

### UI
The interactive interface through which the player interacts with the game.

### HUD
On-screen elements displaying real-time information during gameplay.

### Tooltip
Contextual information displayed on hover or focus.

### Feedback
Audio or visual response that communicates the result of actions.

### Clarity
The player’s ability to understand cause, effect, and intent.

---

## 10. Technical & System Health Terms

### State
The complete set of variables defining the current game condition.

### State Transition
A controlled change from one state or mode to another.

### Edge Case
A valid but uncommon scenario that must be handled safely.

### Soft Lock
A game state where progress is impossible without restarting.

### Determinism
The degree to which outcomes are repeatable given identical inputs.

---
