# Unit Rebuild Sheet

This document turns progression targets and faction identity into per-unit rebalance notes.

It is intentionally draft-first. The point is to give each unit a job, a personality, and a rebuild direction before we start tuning numbers.

## How To Use This Sheet

For each unit we want:

- a combat role
- a one-sentence fantasy
- a desired behavior pattern in a 20-tick round
- a kit direction
- explicit rebalance questions

## Pigs

### Mudwrestler

- Role: control grunt
- Fantasy: a nasty close-range pig that latches onto a target and forces a dirty fight
- Current read: too plain if it is only a basic melee attacker
- Desired pattern:
  - low-to-mid damage
  - frequent actions
  - target-control pressure
- Rebuild direction:
  - add `wrestle`
  - possibly add mud-based defense shred or hit softening
- Questions:
  - should `wrestle` replace one basic attack in the cycle or sit beside it?
  - should Mudwrestler be more annoying than dangerous, or dangerous enough to demand respect?

### Mudslinger

- Role: ranged debuff grunt
- Fantasy: throws mud to foul up cleaner attackers
- Current read: plain ranged basic attacker
- Desired pattern:
  - steady ranged harassment
  - enables other pigs
- Rebuild direction:
  - add a mud projectile ability
  - lean into defense reduction or similar muck/debuff pressure
- Questions:
  - should Mudslinger be the pigs’ main defense shredder?
  - should its debuff be lighter but faster than Mudking’s version?

### Mudking

- Role: bruiser-control boss
- Fantasy: a muddy brawler boss who throws weight around and dictates who has to fight it
- Current read: solid boss body, but personality can be much stronger
- Desired pattern:
  - hard hits
  - wrestle pressure
  - visible muddy boss mechanic
- Rebuild direction:
  - add `wrestle`
  - add a stronger mud-themed damage or defense-break ability
  - preserve boss readability over pure complexity
- Questions:
  - should Mudking’s mud effect be stronger than regular pigs, or unique?
  - should the boss use wrestle as its main signature or as supporting texture?

## Kobolds

### Kobold Shieldbearer

- Role: protector / time-buyer
- Fantasy: scrap-armored frontline that stalls for kobold inventions to matter
- Current read: defensive body with limited personality
- Desired pattern:
  - soak attention
  - create time
  - low personal damage
- Rebuild direction:
  - keep sturdy defensive identity
  - consider synergy with countdown or explosive allies
  - possibly add a passive that matters more when allies are setting up
- Questions:
  - should Shieldbearer protect allied devices indirectly or directly?
  - does `shield_up` remain the right expression, or do kobolds need a more gadget-like defense skill?

### Kobold Skirmisher

- Role: light setup harasser
- Fantasy: a quick ranged kobold that keeps the fuse burning
- Current read: basic ranged attacker with aimed shot follow-up
- Desired pattern:
  - quick chip pressure
  - supports setup mechanics
- Rebuild direction:
  - possibly add a small explosive or arming effect
  - keep lower individual threat than Sharpshooter
- Questions:
  - should Skirmisher be the main “countdown starter” unit?
  - how much of kobold gadget identity should appear at grunt tier?

### Kobold Sharpshooter

- Role: payoff ranged elite
- Fantasy: punishes you once the kobolds have set the table
- Current read: stronger ranged version of Skirmisher
- Desired pattern:
  - fewer but scarier ranged payoffs
  - strong follow-through on softened targets
- Rebuild direction:
  - combine precise ranged threat with tinker payoff
  - avoid being just “Skirmisher but bigger”
- Questions:
  - should Sharpshooter detonate marks or exploit armed targets?
  - does it need a cleaner single-target identity than the rest of the faction?

### Kobold Warchief

- Role: setup-payoff boss
- Fantasy: the mastermind behind the mountain warband’s dangerous contraptions
- Current read: boss archer needs more boss-only identity
- Desired pattern:
  - escalating threat
  - delayed danger
  - clear “deal with this now” moments
- Rebuild direction:
  - boss-grade countdown or explosive mechanic
  - stronger battlefield pressure than a simple aimed-shot loop
- Questions:
  - should the Warchief plant danger zones, arm a bomb, or mark a future detonation target?
  - what makes the player feel the countdown pressure clearly?

## Frogmen

### Frogman Bruiser

- Role: tough swamp frontline
- Fantasy: a stubborn bog fighter who just keeps coming through wet, ugly combat
- Current read: durable melee bruiser, but not yet strongly swamp-specific
- Desired pattern:
  - steady frontline pressure
  - rough durability
- Rebuild direction:
  - add swamp/water flavor to core attacks
  - keep it simple and brute-practical
- Questions:
  - should Bruiser apply a mild soaked/bogged effect?
  - how much defense should come from stats versus status identity?

### Frogman Spearhunter

- Role: aggressive primitive striker
- Fantasy: a rough hunter that jabs hard and fast with crude technique
- Current read: melee striker with little thematic distinction
- Desired pattern:
  - more threatening than Bruiser
  - less durable, more aggressive
- Rebuild direction:
  - add a more primitive or swamp-hunter flavored active
  - keep it feeling direct, not tactical-polished
- Questions:
  - should Spearhunter be the faction’s clearest “kill you first” non-boss unit?
  - should it punish exposed or bogged targets?

### Frogman Wardrummer

- Role: scrappy support elite
- Fantasy: a loud bayou morale piece that keeps the swamp mob moving
- Current read: support unit with room for much more personality
- Desired pattern:
  - support first
  - rough morale pressure second
- Rebuild direction:
  - keep crude buff identity
  - possibly add a swamp-holler or rhythm mechanic
- Questions:
  - should Wardrummer feel defensive, offensive, or mixed support?
  - should its support be less polished and more momentum-based than player support kits?

### Frogman Bog Tyrant

- Role: attrition boss anchor
- Fantasy: a huge swamp brute that drags the fight into a slow, miserable slugfest
- Current read: durable melee boss with room for stronger swamp identity
- Desired pattern:
  - hard to push through
  - oppressive over time
  - clear bayou boss presence
- Rebuild direction:
  - add stronger swamp/water mechanic
  - emphasize attrition and ugly staying power
- Questions:
  - should Bog Tyrant drown players in slow pressure, defense pressure, or muddy sustain-style attrition?
  - what makes this boss feel different from “bigger Frogman Bruiser”?

## Rebuild Pass Order

Recommended implementation order:

1. `Mudwrestler`
2. `Mudking`
3. `Mudslinger`
4. `Kobold Shieldbearer`
5. `Kobold Skirmisher`
6. `Kobold Sharpshooter`
7. `Kobold Warchief`
8. `Frogman Bruiser`
9. `Frogman Spearhunter`
10. `Frogman Wardrummer`
11. `Frogman Bog Tyrant`

Reason:

- start with pigs because they are the onboarding faction and the clearest place to define new status/control language
- then lock kobold setup mechanics
- then finish with frogman faction identity once the earlier two factions give us stronger contrast

## Shared Rebalance Questions

- Which units are teaching units, and which are check units?
- Which passives are expressive, and which are only generic stat padding?
- Which units should have one memorable signature ability?
- Which bosses need one clearly visible “this is their thing” mechanic?
- Where are we overusing `basic_attack + burst attack` without enough faction identity?
