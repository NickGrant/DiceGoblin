# Gameplay Systems Roadmap

----

Status: planning  
Last Updated: 2026-07-24  
Owner: Product + Systems Design  
Depends On: `documentation/00-overview/00-project-overview.md`, `documentation/00-overview/01-core-gameplay-loop.md`, `documentation/02-systems-mvp/00-combat-system.md`, `documentation/02-systems-mvp/01-dice-system.md`, `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/03-encounter-scope.md`, `agent/MILESTONES.md`

## Purpose

- Capture proposed gameplay functionality that is not part of the current implemented baseline.
- Distinguish current systems, near-term extensions, and deliberately deferred ideas.
- Define the player value and minimum viable boundaries of each proposed feature before implementation issues are created.
- Provide a dependency-aware order for expanding the game without treating graphics work as gameplay progress.

This document is directional planning rather than an implementation contract. Detailed system documents should be created or revised before each milestone enters active development.

## Current Implemented Baseline

The roadmap assumes the following functionality already exists:

- authenticated player sessions and a persistent command HUD
- a home hub and region-selection flow
- one active squad with a 3x3 formation
- persistent player-owned units and dice
- ordered unit ability loadouts with dice assigned to authored ability slots
- backend-authoritative combat resolution and battle logs
- unit XP, leveling, class promotion, capstones, and inherited abilities
- the shop, daily deals, feature unlocks, and the academy
- run maps with combat, loot, rest, boss, exit, and dialogue nodes
- reward preview and claim flow for teeth, units, dice, XP, and region items
- the codex for unlocked features, units, affixes, enemies, and discovered dialogue
- sequential region progression

### Current Region Order

1. **Mystic Cave**
   - The initial unlocked region.
   - Costs no energy.
   - Contains the introductory conversation with The Whim followed by an exit.
   - Completing it unlocks The Farm.
   - It is an onboarding and narrative region, not a future combat biome.
2. **The Farm**
   - Fixed introductory combat route.
   - Completing it unlocks the Mountains.
3. **Mountains**
   - Procedural kobold region.
   - Completing it unlocks the Swamps.
4. **Swamps**
   - Procedural frogman region.

## Roadmap Principles

### Gameplay depth before presentation breadth

New art, animation, and Phaser playback can improve readability and personality, but they should not substitute for new decisions, build diversity, progression goals, or run variety.

### Extend existing systems before adding parallel systems

New features should build on current units, abilities, dice, rewards, regions, unlocks, and codex records. Avoid creating separate progression tracks that do not interact with the existing loop.

### Backend authority remains mandatory

Bounty progress, random encounter results, crafting outcomes, unit variants, new currencies, and combat modifiers must be generated and persisted by the backend. Refreshing or reconnecting must not alter an already generated result.

### Randomness must create decisions rather than hide outcomes

Random rewards and encounters should expose their rules, preserve deterministic state once generated, and offer limited player agency where appropriate.

## Delivery Order

1. Finish the active backend structural cleanup milestone.
2. Expand the universal combat stat model.
3. Add persistent goblin DNA splice variants.
4. Improve progression guidance and the home dashboard.
5. Add the bounty board.
6. Expand the academy and feature-unlock tree.
7. Add the Wrong Machine and Raw Chaos economy.
8. Expand run encounter vocabulary.
9. Add slot-machine-style random encounters.
10. Complete Tier III progression and late-game class coverage.
11. Continue battle-playback and presentation improvements as a parallel, non-blocking track.

## Milestone 0: Backend Structural Cleanup

### Goal

Create stable service boundaries before adding systems that grant rewards, mutate units, update progression, or end runs.

### Required outcomes

- one canonical run-lifecycle path for success, defeat, abandon, exit, and cleanup
- shared unit and dice grant services across rewards, shops, starter grants, and debug tools
- centralized mutation guards for active-run unit and squad restrictions
- shop and academy domain logic extracted from controller transport logic
- narrow synchronous events only where they make post-action behavior easier to trace

### Why it comes first

Bounties, crafting, variants, and random encounters all need to award or mutate persistent state. Adding them before the existing lifecycle and grant paths are consolidated would multiply duplicated orchestration.

## Milestone 1: Expanded Combat Stats

### Goal

Increase unit differentiation and create a broader vocabulary for abilities, dice affixes, statuses, variants, and future enemies.

### Universal stat model

- **Max HP:** how much damage the unit can sustain
- **Attack:** physical and authored offensive output
- **Defense:** mitigation against ordinary damage
- **Precision:** offensive reliability and a limited contribution to critical chance
- **Resolve:** resistance to control effects and harmful statuses

### Explicit exclusion

Do not add a universal Speed stat. Combat timing remains governed by the existing tick scheduler, ability speed costs, and authored effects that advance, delay, interrupt, or otherwise modify action timing.

### Precision v1 boundaries

- influences whether eligible attacks or status applications connect
- supports a restrained baseline critical-hit model
- must not create excessive miss streaks
- should provide value to support and control units through effect reliability, not only damage
- can be modified by abilities, dice affixes, statuses, and encounter rules

### Resolve v1 boundaries

- influences resistance to Poison, Bleeding, Sleep, and future control effects
- may reduce application chance, duration, or potency according to the authored effect
- does not replace Defense or directly reduce ordinary physical damage
- must be readable in battle logs when it changes an outcome

### Required supporting work

- versioned unit and enemy stat schemas
- authored growth values for all existing player units and enemies
- updated deterministic combat formulas and fixtures
- battle-log language for misses, critical hits, resisted effects, and reduced durations
- unit-detail and comparison surfaces
- dice-affix and ability review
- complete balance pass across all current regions

### Definition of done

Units with similar Attack, Defense, and HP can still occupy meaningfully different tactical roles through Precision, Resolve, abilities, formation, and dice configuration.

## Milestone 2: Goblin DNA Splice Variants

### Goal

Make individual goblins meaningfully distinct from other units of the same class and level while reinforcing the setting's animal-spliced goblin identity.

### Core model

A unit has two independent identities:

- **class progression:** Bruiser, Guardian, Marksman, Bannerbearer, Saboteur, and later promotions
- **DNA splice:** Basic goblin or one persistent animal-derived variant

The splice remains attached to the unit through leveling, mastery, sideways promotion, and tier promotion.

### Version-one scope

- Basic goblin plus a small launch set of splice families
- one splice per unit
- one modest stat tendency per splice
- one authored passive or conditional behavior per splice
- splice displayed on unit details, recruitment, rewards, filters, and codex entries
- existing units migrate to the Basic lineage unless a deliberate migration rule is authored

### Design constraints

- a splice should alter decisions rather than only provide a flat damage bonus
- variants must remain useful across more than one class family
- no splice should be the universally correct choice
- visual variants should follow the persisted gameplay variant, not determine it client-side

### Acquisition v1

Variants may be rolled when a unit is recruited, purchased, or granted as a reward. Later academy research can provide greater visibility or limited control over variant acquisition.

### Explicit deferrals

- breeding
- combining multiple splice families
- dominant and recessive gene simulation
- splice-specific class trees
- unrestricted mutation rerolls

### Definition of done

Two units of the same type, tier, and level can support different builds because their persistent splice affects combat or progression behavior.

## Milestone 3: Progression Guidance and Home Dashboard

### Goal

Ensure that returning players can quickly understand what happened, what matters next, and which actions will advance their warband.

### Home dashboard additions

- active squad summary and formation status
- current run or last-run summary
- next recommended progression action
- progress toward the next region or feature unlock
- new codex discoveries
- promotion-ready or mastery-ready units
- active objectives and accepted bounties
- Wrong Machine status after that feature is unlocked

### Objective system

Objectives are passive guidance tasks that teach or reinforce normal play. They are separate from accepted bounty contracts.

Examples:

- complete a run
- equip a die to an empty ability slot
- level a unit
- promote a unit
- apply a status effect
- clear a newly unlocked region
- inspect a new codex entry

### Definition of done

The home screen communicates at least one meaningful next action without requiring the player to inspect every management screen.

## Milestone 4: Bounty Board

### Goal

Provide player-selected contracts that encourage different units, abilities, regions, formations, and play patterns in exchange for visible rewards.

### Difference between objectives and bounties

- **Objectives** are passive progression guidance and onboarding.
- **Bounties** are deliberately accepted contracts with limited active slots, explicit requirements, and defined rewards.

The acceptance limit is important. Without it, bounties become passive bonus payouts for actions the player was already taking.

### Bounty categories

#### Hunting bounties

- defeat a number of enemies from a faction
- defeat a specific enemy type
- defeat a biome boss multiple times
- complete a boss fight with a survival condition

#### Region bounties

- complete a number of runs in a biome
- clear a biome without using a rest node
- visit optional branches
- complete a run with a squad-size limit

#### Ability and status bounties

- use a named ability or ability family a number of times
- apply Poison, Bleeding, Sleep, or Bolstered a number of times
- defeat enemies with ranged, melee, support, or control abilities
- trigger effects using a specified die size or rarity

#### Formation and roster bounties

- complete a run using several class families
- avoid or occupy specified formation cells
- complete a run using only Tier I units
- include one or more specified splice variants

#### Collection and progression bounties

- acquire dice of a minimum rarity
- master or promote a unit
- discover codex entries
- salvage or fabricate dice after the Wrong Machine unlocks

### Board structure v1

- three standard rotating contracts
- one more difficult premium contract
- one faction- or region-focused contract
- limited accepted-contract slots
- visible progress and reward before acceptance
- explicit expiration and refresh timing
- duplicate prevention within the same rotation

### Reward types

- teeth
- Raw Chaos after that currency exists
- dice with a known minimum quality
- recruitment opportunities
- region items
- temporary shop, academy, or recruitment modifiers
- cosmetic rewards only after the core system is established

### Persistence and progress rules

- progress is updated by backend-owned gameplay events
- each bounty definition declares the event, filters, target count, expiration, and reward
- progress must be idempotent across retries and reward claims
- generated bounty boards must remain stable for their rotation period
- expired bounties should preserve enough history for support and duplicate prevention

### Definition of done

The player can select contracts that intentionally change squad preparation or region choice and receive a clearly communicated reward when the contract is completed.

## Milestone 5: Academy and Feature-Unlock Expansion

### Goal

Turn the academy and feature catalog into a broader medium-term progression tree rather than a mostly linear set of purchases.

### Candidate unlocks

- **Scouting Table:** preview additional information about run paths or encounter danger
- **Recruiter Ledger:** improve recruitment selection, visibility, or refresh options
- **Dice Appraisal:** reveal stronger affix, valuation, or probability information
- **Field Rations:** improve rest choices or run recovery
- **Market Expansion:** add daily-deal capacity or specialized inventory
- **Splice Research:** reveal or influence DNA variant recruitment
- **Bounty Office:** unlock additional bounty slots, categories, or rerolls
- **Wrong Machine:** unlock dice salvage and fabrication

### Requirement types

Unlocks should not all be teeth purchases. Candidate requirements include:

- cleared regions
- mastered unit families
- discovered enemy or lore records
- owned die sizes or rarities
- discovered affixes
- recovered region items
- completed bounty categories

### Definition of done

The academy presents meaningful medium-term choices and connects collection, mastery, exploration, and economy progression.

## Milestone 6: Wrong Machine and Raw Chaos

### Goal

Give unwanted dice a useful destination and create a second currency with a distinct source and purpose.

### Currency roles

- **Teeth:** recruitment, supplies, market purchases, and ordinary goblin commerce
- **Raw Chaos:** dice salvage, dice fabrication, unusual research, and advanced chaos systems

Raw Chaos should not simply be a rarer version of teeth.

### Wrong Machine v1

- salvage unwanted dice into Raw Chaos
- calculate salvage value from die size, rarity, and affixes
- fabricate a selected die size
- allow limited quality targeting through additional cost or catalysts
- generate valid affixes randomly within authored rules
- use selected region items as optional catalysts or recipe requirements
- support bulk salvage
- prevent equipped or locked dice from being salvaged without explicit action

### Balance constraints

- naturally found high-quality dice remain exciting
- crafting reduces bad-luck frustration but does not guarantee perfect items
- salvage values must not create profitable purchase-and-salvage loops
- fabrication results are generated and persisted by the backend before reveal

### Explicit deferrals

- perfect affix selection
- unlimited reroll loops
- unrestricted rarity upgrading
- legendary-die fusion
- deterministic best-in-slot recipes

### Definition of done

Every unwanted die has a meaningful use, Raw Chaos has clear sources and sinks, and the reward loop remains valuable.

## Milestone 7: Expanded Run Encounters

### Goal

Increase run variety by adding new decisions and risk profiles rather than only new enemy art or larger statistics.

### Candidate encounter families

- **Elite combat:** stronger authored enemy groups with improved rewards
- **Narrative event:** dialogue with choices, lore, costs, or persistent discoveries
- **Hazard:** formation, HP, Resolve, resource, or sacrifice challenges
- **Traveling merchant:** limited in-run purchases or exchanges
- **Chaos encounter:** unstable rules tied to dice, variants, Raw Chaos, or The Whim

### Biome identity rule

Each combat biome should eventually introduce at least one mechanical emphasis:

- The Farm teaches the baseline loop.
- Mountains emphasize kobold traps, preparation, and dangerous formations.
- Swamps emphasize attrition, statuses, and recovery pressure.
- Future combat regions should add similarly legible rules rather than only higher numbers.

Mystic Cave remains the introductory narrative region unless a later design explicitly expands it. It should not be described as an unimplemented future combat biome.

### Definition of done

Region choice changes expected preparation and decisions, not only enemy appearance and difficulty.

## Milestone 8: Slot-Machine-Style Random Encounters

### Goal

Create a signature chaos encounter where a slot-machine-style result generates the enemy composition, encounter rules, and reward multiplier.

The machine should determine what the player faces. It should not operate as a conventional real-money or premium-currency gambling system.

### Core flow

1. Enter a dedicated chaos encounter node.
2. Display three reels with authored symbol pools.
3. Generate and persist the complete result on the backend.
4. Reveal the enemy family, encounter shape, modifier, and reward multiplier.
5. Allow one limited manipulation option when unlocked or offered.
6. Confirm the wager and resolve the generated battle.
7. Apply the advertised reward modifier after victory.

### Suggested reel model

- **Reel 1 — enemy family:** pigs, kobolds, frogmen, elite, boss, mixed group, or wildcard
- **Reel 2 — encounter shape:** horde, armored frontline, ranged backline, two elites, ambush, reinforcements, or multi-cell enemy
- **Reel 3 — rule and reward:** enemy Bolstered, player status pressure, volatile dice, formation modifier, altered timing, guaranteed loot, or increased reward multiplier

The exact reel model may change, but each reel should have a readable responsibility.

### Example result

**Kobolds + Reinforcements + Volatile Dice**

- generates a larger kobold encounter
- introduces additional enemies according to an authored reinforcement rule
- allows eligible dice to explode once
- advertises an increased reward multiplier before confirmation

### Player agency v1

Choose one initial manipulation mechanic:

- lock one reel before the final spin
- reroll one reel once
- choose between two generated results
- cash out for a smaller non-combat reward

Later unlocks may allow the player to pay teeth or Raw Chaos for a bounded manipulation. Unlimited rerolls are not allowed.

Foundation implementation note:

- the first backend slice persists three generated reel outputs per run node
- the initial player agency mechanic is one reroll of one reel
- full chaos-node graph placement, encounter finalization, and reward application are follow-up work

### Fairness requirements

- generated encounters remain inside authored difficulty limits for the selected region or run depth
- risk and reward are scored from the same generated result
- unusually dangerous results require explicit confirmation
- probabilities are authored and testable
- appraisal or scouting unlocks may reveal weights, but hidden weights must still remain stable
- refreshing cannot regenerate the result
- the player cannot lose previously claimed run rewards because the machine generated an extreme combination
- reward multipliers must be communicated before combat

### Integration opportunities

- bounties can require wins against machine-generated encounters or specific modifiers
- codex entries can record discovered symbols and combinations
- academy unlocks can reveal probabilities or add limited reel control
- Raw Chaos can support bounded manipulation without becoming mandatory
- The Whim or the Wrong Machine can provide the narrative framing

### Definition of done

The encounter produces surprising but understandable battles, offers at least one meaningful choice, persists its result, and compensates additional danger with visible rewards.

## Milestone 9: Complete Tier III Progression

### Goal

Complete late-game class coverage after the expanded stats and variant systems establish the final build vocabulary.

### Required work

- Tier III destinations for every major Tier I family
- complete capstone coverage
- meaningful chain-versus-sideways promotion choices
- inherited-passive review
- advanced promotion requirements using regions, research, mastery, or region items where appropriate
- balance rules preventing one route from becoming universally dominant

### Why it follows the earlier systems

Tier III designs created before Precision, Resolve, and splice variants would likely require immediate rework once those systems become part of every unit build.

## Parallel Track: Battle Playback and Phaser

Phaser battle playback and animation improvements remain valuable, but they are a presentation track rather than a prerequisite for the gameplay systems in this roadmap.

Guidelines:

- Angular remains responsible for routes, API orchestration, controls, and accessibility.
- The backend remains the only combat authority.
- Phaser consumes immutable battle-playback snapshots.
- Gameplay milestones should continue to support a readable non-Phaser fallback.

## Deliberately Deferred Features

The following are not part of this roadmap's near-term delivery order:

- direct PvP
- full breeding and genetic inheritance simulation
- multiple simultaneous splice families per unit
- unrestricted item rerolling or perfect-item crafting
- monetized gambling or purchasable slot-machine spins
- replacing Angular management screens with Phaser
- expanding Mystic Cave into a combat biome without a separate design decision

## Roadmap Exit Criteria

This roadmap has succeeded when the game offers:

- deeper combat differentiation than Attack, Defense, and HP alone
- persistent individuality among goblins of the same class
- understandable goals between runs
- player-selected bounties that alter preparation and region choice
- a useful second-currency and dice-salvage loop
- broader encounter decisions and region identity
- a signature random encounter that is chaotic without being opaque or unfair
- complete late-game class destinations

Implementation issues should be created milestone by milestone rather than treating this entire document as one delivery scope.
