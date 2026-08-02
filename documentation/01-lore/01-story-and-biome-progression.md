---
Title: "Dice Goblins - Story and Biome Progression"
Status: Needs Review
Last Updated: 2026-08-01
Owner: Narrative Design
Depends On:
  - documentation/01-lore/00-world-and-lore.md
  - documentation/00-overview/01-core-gameplay-loop.md
  - documentation/07-development-path/00-gameplay-systems-roadmap.md
Category: 01-lore
Tags:
  - lore
---

# Dice Goblins - Story and Biome Progression

## Purpose

- Record the agreed narrative direction beyond the currently implemented dialogue.
- Define a chapter-shaped biome progression from the Mystic Cave to the Grand Library.
- Connect goblin lineage unlocks, the Wrong Machine, Raw Chaos, and exploration backtracking to the story.
- Distinguish confirmed setting decisions from biome names, balance values, and chapter details that remain planning-level.

## Narrative Foundation

The story follows a newly manifested goblin fragment as it grows from a single disruptive survivor into the warchief of a renewed goblin people.

The central conflict is existential rather than moral:

- goblins are chaos incarnate and cannot fully exist inside a perfectly ordered world
- The Archivist and the Grand Library are aligned with order rather than simple good or evil
- the Library considers goblins an unacceptable source of instability
- most other peoples benefit from the Library's stability and do not want goblins restored
- the player is rebuilding goblinkind and deliberately weakening the order that replaced it

The Archivist eradicated historical goblinkind through a combination of direct extermination and forced stabilization. Order magic killed some goblins and transformed others into stable, classifiable creature lineages. The player eventually learns to reverse that process without simply turning existing creatures into goblins.

## Campaign Pacing Target

The main narrative should lead a steady player through roughly two-thirds of the game's total unlockable content.

By the time the Archivist is defeated, a typical player should have:

- completed the primary biome sequence
- unlocked the central game systems
- acquired several, but not all, goblin lineages
- opened enough traversal abilities to understand the backtracking loop
- built a mature warband capable of entering the repeatable endgame

The remaining third of the content should include:

- optional goblin lineages
- missed traversal routes and hidden encounters in earlier regions
- advanced unit classes and feature unlocks
- codex and lore discoveries
- difficult bounties and repeatable objectives
- late progression around Raw Chaos, the Wrong Machine, and high-quality dice

Players may pursue much of that optional content before the final confrontation. Defeating the Archivist resolves the campaign but does not end normal play.

## Goblin Lineage Reconstruction

Goblin lineages are both build options and exploration tools.

The intended unlock loop is:

1. Fight a creature family repeatedly.
2. Recover a lineage material associated with that family.
3. Combine enough lineage material with Raw Chaos in the Wrong Machine.
4. Unlock the corresponding goblin lineage for future recruitment or transformation rules.
5. Use that lineage's combat or traversal capability to access new strategies and previously unreachable content.

An illustrative example is collecting pig ears from Farm enemies and combining them with Raw Chaos to unlock Pig Goblins. Exact drop rates, material counts, and Raw Chaos costs are balance values and are not locked by this document.

Materials from the Farm and Mountains may be collected before the Wrong Machine is recovered. They become usable once the machine is reclaimed at the end of the Swamps chapter.

Lineage benefits should be divided across two broad categories:

- **Combat lineages:** primarily alter stats, passives, status interactions, formation use, or ability behavior.
- **Traversal lineages:** provide access to hidden routes, optional nodes, shortcuts, resources, or discoveries in existing biomes.

A lineage may contribute to both categories, but not every lineage needs a traversal key.

## Metroidvania-Style Revisit Structure

Earlier biomes should contain features that are visible, hinted at, or discoverable before the player can use them.

Examples include:

- high ledges that require a flying lineage
- cracked barriers that require a stone-breaking lineage
- narrow or sealed passages that require a shadow lineage
- unstable tunnels that require a burrowing bug lineage
- environmental routes that require heat, cold, poison, or spore resistance

These routes should usually hold optional value rather than block the primary campaign. Suitable rewards include:

- lineage materials
- rare dice and affixes
- alternate encounters
- hidden dialogue and codex entries
- advanced academy requirements
- region items and Wrong Machine catalysts
- optional bosses and bounties

Backtracking should feel like discovering that an earlier region was larger than it first appeared, not merely replaying the same map with larger numbers.

## Proposed Twelve-Chapter Biome Sequence

The first four chapters are grounded in current implementation and agreed story direction. Chapters five through twelve are a planning framework and may be renamed or reordered during content design.

### Chapter 1: Mystic Cave - The First Fragment

**Primary role:** introduction and creation story  
**Enemy family:** none  
**Major unlock:** access to The Farm

Story beats:

- The Whim manifests the player as a primordial-looking fragment.
- The Whim explains that goblinkind was nearly eradicated by The Archivist.
- The player is stabilized into the current basic goblin form.
- The Whim sends the player to create a mess, rebuild goblinkind, and recover The Tooth Collector from the Mudking.
- The larger importance of dice, lineages, and the Wrong Machine remains deliberately incomplete.

Later revisit beat:

- The Whim tells the player to return after finding the Wrong Machine.

### Chapter 2: The Farm - The Tooth Collector

**Primary role:** first combat chapter and economy introduction  
**Enemy family:** pigs  
**Future lineage:** Pig Goblins  
**Major unlocks:** The Tooth Collector, shop access, Mountains

Story beats:

- The player crosses an orderly working farm and confronts the Mudking.
- The Mudking refuses to release The Tooth Collector.
- Dialogue choices establish the player's emerging goblin voice but all paths lead to the boss fight.
- The Mudking is defeated and releases The Tooth Collector.
- The Tooth Collector establishes teeth as ordinary monster currency and becomes a persistent hub character.
- Pig lineage materials begin appearing, although the player cannot yet reconstruct Pig Goblins.

The Mudking should remain available for repeat-visit banter and may become a recurring irritated regional ruler rather than a permanently removed character.

### Chapter 3: Mountains - The Archivist Takes Notice

**Primary role:** reveal the central antagonist and locate the Wrong Machine  
**Enemy family:** kobolds  
**Future lineage:** Lizard Goblins  
**Major unlock:** Swamps

Story beats:

- The Archivist discovers that a living goblin has returned.
- The Archivist treats the player's existence as an error in the record that must be corrected.
- The encounter makes clear that the Library believed goblins were fully eradicated.
- Kobold tinkerers recognize descriptions, components, or old markings associated with the Wrong Machine.
- The player questions, bargains with, or defeats kobold authorities to learn that the machine passed into the Swamps and is now held by the frogmen's ruler.
- The Archivist begins observing the player rather than immediately committing the full force of the Library.

The kobolds should be competent tinkerers who understand machinery but cannot safely reproduce the Wrong Machine's chaos construction.

### Chapter 4: Swamps - Reclaim the Wrong Machine

**Primary role:** complete the opening arc and unlock lineage reconstruction  
**Enemy family:** frogmen  
**Future lineage:** Frog Goblins  
**Major unlocks:** the Wrong Machine, lineage reconstruction, next biome sequence

Story beats:

- The player enters a region whose rulers operate under the Library's broader order.
- Frogmen recognize the machine as dangerous contraband, a trophy, or a tool that must not return to goblin control.
- The player learns that the Wrong Machine is an ancient goblin or Whim-aligned contraption built to construct things from chaos.
- The machine's name is a proper name. It does not mean that the machine is malfunctioning or performing the wrong function.
- The frog boss, currently represented by the Bog Tyrant direction, refuses to surrender it.
- Defeating the boss returns the Wrong Machine to the goblins.
- The player can now combine Raw Chaos with accumulated lineage materials to reconstruct goblin lineages.
- The Whim confirms that rebuilding goblinkind now means recovering the forms that order stripped away.

A guaranteed first reconstruction should teach the system soon after this chapter. Pig, Lizard, or Frog Goblins are all valid tutorial candidates.

### Chapter 5: The Ordered Hive - Break the Queen's Strings

**Primary role:** introduce traversal-focused lineages and show imposed order inside another species  
**Enemy family:** insects, including beetles, ants, and other hive creatures  
**Future lineage:** Bug Goblins  
**Traversal capability:** burrowing, tunnel access, or movement through unstable earth

Story beats:

- The hive appears naturally orderly, but its current perfection is an imposed condition rather than healthy insect behavior.
- The colony responds to a queen, regent, handler, or magical control figure aligned with the Library.
- The boss should be a puppet queen or a sleeping queen whose will is being directed by an order-aligned overseer.
- Defeating the controlling figure causes the rigid hive structure to fracture into unpredictable swarm behavior.
- The outcome is not that the insects become loyal goblin allies; it is that the Library loses another stable system.

This chapter should reveal that the Library's rule often operates through local institutions rather than direct occupation by the Archivist.

### Chapter 6: Haunted Ruins - What the Record Leaves Behind

**Primary role:** reveal the consequences of magical erasure  
**Enemy family:** shadows, wraiths, and recordless remnants  
**Future lineage:** Shadow Goblins  
**Traversal capability:** pass through narrow seals, hidden doors, or partially ordered barriers

Story beats:

- The player finds a place removed or damaged by the Library's attempts to correct reality.
- Wraiths and shadows are remnants of people, possibilities, or histories that no longer fit the official record.
- The player learns that cataloging is not merely documentation: Library records and order magic help determine what reality is permitted to retain.
- The Archivist's response escalates from observation to containment.
- Shadow lineage reconstruction gives the warband access to routes hidden behind incomplete or damaged reality.

### Chapter 7: The Deep Stoneworks - Break the Foundations

**Primary role:** attack the infrastructure that carries order into distant regions  
**Enemy family:** golems, gargoyles, stone guardians, and quarry constructs  
**Future lineage:** Stone Goblins  
**Traversal capability:** break cracked walls, move heavy obstacles, or withstand crushing hazards

Story beats:

- The Library uses the Stoneworks to create roads, fortifications, seals, and enforcement constructs.
- The player discovers that order is maintained through logistics and infrastructure as much as ideology.
- Local workers or creatures may prefer the safety created by the Stoneworks even while suffering under its control.
- The chapter ends with the destruction or corruption of a major stabilizing engine, opening routes to regions previously sealed by Library construction.

### Chapter 8: Cloudbreak Aerie - Above the Watchtowers

**Primary role:** break the Library's surveillance and communication network  
**Enemy family:** bats, harpies, birdfolk, or other aerial creatures  
**Future lineage:** Bat Goblins  
**Traversal capability:** flight or access to high and separated paths

Story beats:

- The Library's aerial network watches roads and carries records, orders, and warnings between regions.
- The player must reach an elevated command site that ordinary goblins cannot approach directly.
- Bat Goblins provide the clearest traversal unlock in the campaign by opening vertical routes in both new and earlier biomes.
- Destroying the network prevents the Library from containing the goblin resurgence quietly.
- The Archivist shifts from regional containment to open mobilization.

### Chapter 9: Sunscorched Expanse - The Buried Version of History

**Primary role:** uncover evidence of the transformation of goblin lineages  
**Enemy family:** sand wraiths, dune beasts, and desert-aligned peoples  
**Future lineage:** Dust or Dune Goblins  
**Traversal capability:** cross sand hazards, survive extreme heat, or uncover buried routes

Story beats:

- A buried archive, battlefield, or settlement preserves evidence from before the Library completed its official record.
- The player finds proof that eradication included forced stabilization and transformation, not only killing.
- Other peoples may acknowledge former alliances with goblins while insisting that the ordered world is still preferable.
- The player gains a clearer understanding that restoring chaos will not be welcomed by most of the world.

### Chapter 10: Frozen Tundra - The Containment Line

**Primary role:** break a major military or magical boundary protecting the Library's inner territories  
**Enemy family:** yetis, frost beasts, and cold-adapted guardians  
**Future lineage:** Frost or Yeti Goblins  
**Traversal capability:** survive freezing routes, cross ice, or move through blizzard hazards

Story beats:

- The Library maintains an old containment line where chaotic artifacts, creatures, or histories were frozen and isolated.
- The player disrupts supply routes and releases stored chaos pressure.
- A senior clerical-templar antagonist demonstrates the Library's heavy armor, disciplined magic, and specialized anti-chaos techniques.
- The Archivist addresses the player as a genuine rival rather than an error that can be quietly corrected.

### Chapter 11: Fungal Wilds - The Uncatalogued Network

**Primary role:** secure the final route into the Grand Library and prepare the endgame  
**Enemy family:** fungal creatures, spore colonies, and symbiotic forest entities  
**Future lineage:** Spore Goblins  
**Traversal capability:** resist spores and poison, grow temporary paths, or interact with living networks

Story beats:

- The fungal network represents a form of distributed life that resists clean classification without necessarily being aligned with goblins.
- The player seeks knowledge, a living route, or a final catalyst capable of bypassing the Library's outer defenses.
- The region reveals that the ordered world is beginning to destabilize because of the player's campaign.
- The Whim celebrates the cracks while other characters confront the real costs of uncontrolled change.
- The chapter ends with the route to the Grand Library exposed.

### Chapter 12: The Grand Library - Correct the Correction

**Primary role:** campaign climax  
**Enemy family:** clerical templars, order mages, record-keepers, and magical constructs  
**Final lineage unlock:** Primordial Goblins  
**Major unlock:** post-story endgame state

Story beats:

- The player assaults the institution that classified goblinkind out of existence.
- The Library's defenders should appear as heavily armored clerical templars using disciplined anti-chaos magic.
- The final chapter should demonstrate why many inhabitants trust the Library while also showing the coercive police-state structure required to sustain perfect order.
- The Archivist argues that goblins cannot be allowed to exist because chaos incarnate makes lasting safety impossible.
- The player defeats the Archivist and breaks the Library's ability to define goblins as an error in reality.
- Primordial Goblins become available as the final campaign lineage, completing the arc that began with the player's primordial manifestation.
- The story resolves, but the world does not become instantly or uniformly chaotic.
- Repeatable runs, bounties, optional routes, missing lineages, advanced classes, and collection systems remain available after the campaign.

## Recurring Character Arcs

### The Player Warchief

The player's progression should read as:

1. confused newly manifested fragment
2. local raider and nuisance
3. rebuilder of goblinkind
4. wielder of the Wrong Machine
5. destabilizing regional power
6. existential rival to The Archivist
7. victorious warchief with an unfinished world to explore

Player dialogue may become more confident over time, but choices should continue to support multiple shades of goblin personality: direct, sarcastic, threatening, impulsive, and occasionally diplomatic for selfish reasons.

### The Whim

The Whim should remain:

- the source of goblin manifestation
- supportive of disruption and experimentation
- knowledgeable but evasive
- pleased by mistakes and unintended consequences
- unwilling or unable to explain every cost of restored chaos

The Whim should not become a conventional mission dispatcher who provides complete plans.

### The Archivist

The Archivist's escalation should read as:

1. certainty that goblins are extinct
2. surprise and detached observation
3. an attempt to contain and correctly classify the player
4. regional enforcement through Library-aligned rulers and agents
5. open mobilization after the player's repeated successes
6. direct ideological confrontation in the Grand Library

The Archivist remains aligned with order, not personal cruelty. The threat comes from the conclusion that goblins must be eradicated for the ordered world to remain stable.

### The Tooth Collector

The Tooth Collector anchors the ordinary goblin economy and provides a persistent connection to the opening chapters.

Teeth are monster currency. They do not require a hidden cosmological function. The Collector may be unsettling, knowledgeable, and opportunistic without making every transaction metaphysically significant.

## Other Peoples and the Library

Most organized peoples are currently under the rule or influence of the Grand Library.

Their relationships to goblins may include:

- former alliance before the triumph of order
- descent from stabilized or transformed goblin lineages
- historical conflict with goblins
- pragmatic acceptance of Library rule
- fear that renewed goblinkind will destroy the safety they now possess

Defeating a biome ruler should not automatically make that faction grateful or allied. In many chapters, the player is intentionally breaking a system that the local population believes is preferable to goblin chaos.

## Post-Story State

Defeating The Archivist ends the main narrative but preserves the core game loop.

The post-story experience should support:

- repeatable biome runs
- rotating and advanced bounties
- remaining lineage reconstruction
- traversal-based revisits to earlier regions
- optional bosses and hidden encounters
- advanced academy and unit progression
- dice salvage, fabrication, and collection
- codex completion
- additional threats created by the collapse of central order

The world after the Archivist should be less controlled, not narratively finished.

## Open Design Decisions

The following remain intentionally unresolved:

- final names and exact order of Chapters 5 through 11
- which lineages are required for campaign progression versus optional discovery
- whether the first Wrong Machine reconstruction is Pig, Lizard, Frog, or player-selected
- exact material names, drop rates, costs, and unlock ownership rules
- whether every enemy family maps to one lineage
- the identity of the Hive's order-aligned controller
- the specific local motives of each biome boss
- the exact aftermath of defeating The Archivist
- whether later content introduces surviving independent goblins in addition to newly manifested fragments

These decisions should be resolved through chapter briefs and system contracts before implementation.
