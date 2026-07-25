# Game Glossary (Milestone 0)

Status: active  
Last Updated: 2026-07-24  
Owner: Product + Engineering  
Depends On: `documentation/00-overview/01-core-gameplay-loop.md`, `documentation/00-overview/03-world-and-lore.md`, `documentation/00-overview/04-story-and-biome-progression.md`, `documentation/README.md`


This document defines the canonical terminology used throughout the game's design, implementation, and documentation.  
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
The repeating gameplay structure that defines moment-to-moment play, typically: Exploration -> Encounter -> Resolution -> Reward.

### Failure State
A condition that ends the current run unsuccessfully and triggers run termination logic.

### Victory State
A condition that ends the current run successfully and triggers completion rewards and unlocks.

---

## 2. World & Exploration Terms

### Map
The full navigable structure of nodes and paths that define a run's spatial progression.

### Node
A single location on the map that may contain encounters, events, rewards, or narrative elements.

### Path
A connection between two nodes that defines possible movement and may include costs, risks, or gating conditions.

### Biome
A thematic and mechanical grouping that influences encounter types, enemies, modifiers, narrative, and exploration capabilities.

### Region / Act
A large-scale grouping of nodes representing a stage of progression with escalating difficulty.

### The Grand Library
The world institution founded and maintained by The Archivist to classify, preserve, and stabilize reality through order. Its records and magic help enforce which forms of existence are considered stable and permitted.

### The Archivist
The champion of light associated with bringing the world into its current ordered state. The Archivist eradicated historical goblinkind through direct extermination and forced magical stabilization.

### The Whim
The closest thing the setting has to a deity of chaos and the source from which goblins manifest.

### Chaos
The metaphysical principle associated with possibility, improvisation, mutation, accidents, unstable creation, and The Whim. Chaos is not synonymous with good.

### Order
The metaphysical principle associated with continuity, safety, classification, predictable rules, and the Grand Library. Order is not synonymous with evil.

### Order Magic
Magic used by the Grand Library to bind, classify, suppress, stabilize, or remove chaotic possibilities and creatures.

### Clerical Templar
A heavily armored magical enforcer of the Grand Library. Clerical templars combine disciplined military organization, institutional authority, and anti-chaos magic.

### The Wrong Machine
The proper name of an ancient goblin or Whim-aligned contraption built to construct things from chaos. It is not named because it is broken or performing an incorrect function.

### Discovery
The act of revealing previously hidden nodes, paths, information, dialogue, or codex content through exploration.

### Exploration Event
A non-combat interaction triggered by entering or interacting with a node.

### Traversal Unlock
A persistent capability that opens routes, nodes, shortcuts, or discoveries that were previously inaccessible. Goblin lineages are a primary source of planned traversal unlocks.

---

## 3. Encounter System Terms

### Encounter
A structured challenge that taxes player resources and must be resolved before progression continues.

### Encounter Type
The category of encounter, such as combat, puzzle, narrative, hazard, or merchant.

### Encounter Difficulty
A relative measure of threat used to scale challenge and rewards.

### Encounter Slots
The required or allowed number of squads participating in an encounter.

### Resolution
The outcome of an encounter, such as success, partial success, or failure.

### Reward
Resources, loot, or progression granted as a result of encounter resolution.

---

## 4. Combat System Terms

### Combat
A turn-based or structured battle mode with its own ruleset and UI.

### Tick
The atomic combat step, 20 ticks per round.

### Round
A complete cycle of exactly 20 ticks.

### Speed
Determines which ticks a unit acts on.

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

---

## 5. Entity & Control Terms

### Unit
A single actor in the game capable of taking actions.

### Goblin
A physical manifestation of The Whim and an incarnation of chaos. Goblins are intelligent humanoid protagonists, not animal-minded beasts.

### Goblin Warchief
The player's narrative role: an insider to goblin culture who commands a warband in service of chaos.

### Goblin Fragment
A dormant or newly manifested portion of The Whim that can take goblin form when cracks appear in the ordered world.

### Goblin Lineage
A persistent goblin subspecies or reconstructed form influenced by traits recovered from a stable creature family. A lineage may provide combat traits, traversal capabilities, or both.

### Kin
The approved player-facing term for inherited goblin forms, written formally as goblin-kin and shortened to kin in UI copy. Legacy code and storage may still use splice-variant names until a focused compatibility migration retires them.

### Primordial Goblin
A late-game goblin lineage closer to unshaped original chaos than the basic goblin form. The player's first conjured appearance foreshadows this lineage.

### Squads (Warband)
A subset of units participating together within an encounter.
 - referred to in UI as "Warband"
 - player defined
 - multiple squads per player
 - exactly one active run

### Formation
A 3x3 placement grid defining starting positions in combat. Stored on squads (persistent) and also copied into run state (run-scoped).

### Run Squad Snapshot
When a run starts, the selected squad's membership + formation are copied into run-scoped state. Combat reads the run snapshot, not the saved squad directly.

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
A discrete step of progression that increases unit capability; each unit type defines its own max level cap.

### Experience (XP)
A per-unit resource used to gain levels. In the current alpha launch, `xp` represents progress within the unit's current level (not lifetime XP) and does not increase once the unit reaches its max level.

### Stat
A numeric attribute that influences unit performance.

### Trait
A permanent characteristic that defines a unit's identity or behavior.

### Talent / Perk
A selectable progression option that modifies gameplay.

### Augment
A modular component that alters or enhances a unit's capabilities.

### Synergy
A positive interaction between multiple mechanics or systems.

### Anti-Synergy
An intentional friction that limits overpowered combinations.

### Build
The aggregate configuration of a unit's traits, talents, stats, augments, lineage, abilities, and dice.

### Lineage Reconstruction
The planned process of combining Raw Chaos and lineage materials in the Wrong Machine to unlock a goblin lineage.

---

## 7. Loot & Economy Terms

### Loot
Any reward obtained during a run, including items and resources.

### Item
A discrete object with mechanical effects.

### Dice
In gameplay terms, modular combat loadout objects assigned to ability slots. In-world, they are physical shards of chaos power sought by goblins.

### Raw Chaos
A persistent resource released from stabilized chaos objects such as salvaged dice. It supports construction, reconstruction, fabrication, and other advanced chaos systems.

### Lineage Material
A creature-family-specific item recovered from enemies and combined with Raw Chaos to unlock a goblin lineage. Exact item names and costs are authored per lineage.

### Teeth
Ordinary monster currency used for recruitment, supplies, market purchases, and other goblin commerce. Teeth do not require a hidden magical function.

### Rarity
A classification indicating item power and drop frequency.

### Affix / Modifier
A property that alters an item's base behavior.

### Currency
A spendable resource used in shops or upgrades.

### Vendor
An encounter or entity that exchanges currency for goods or services.

### Upgrade
A permanent improvement applied to a unit, item, system, or account capability.

---

## 8. Meta-Progression & Persistence Terms

### Meta Progression
Progression systems that persist across runs.

### Unlock
Content that becomes available after meeting specific conditions.

### Feature Unlock
A persistent account-level unlock that opens or expands a game system.

### Region Unlock
Persistent access to a biome or region after satisfying its progression requirement.

### Lineage Unlock
Persistent access to a goblin lineage after its reconstruction requirements are completed.

### Carryover
Resources or effects that persist between runs.

### Reset
The clearing of run-scoped state at run end.

### Save State
A serialized snapshot of the current game state.

### Post-Story State
The persistent game state after the main campaign and final Archivist confrontation are complete. Repeatable runs, bounties, collection, hidden routes, and unfinished unlocks remain available.

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
The player's ability to understand cause, effect, and intent.

### Codex
The persistent player-facing record of discovered units, enemies, affixes, features, dialogue, lore, and other collected knowledge.

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
