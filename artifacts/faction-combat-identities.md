# Faction Combat Identities

This document defines the intended gameplay personality of each current biome faction.

The goal is not only to make factions harder or easier, but to make them feel distinct and readable in combat.

## Identity Rules

Each faction should answer:

- What do they do mechanically?
- What does it feel like when you fight them?
- What makes their units recognizable even before you memorize exact stats?

## Pigs

Tone:

- muddy
- close-range
- physical
- messy and controlling

Core fantasy:

- pigs drag the fight into the mud and make clean combat harder

Primary mechanics:

- mud-themed damage
- defense reduction
- accuracy or stability disruption if introduced later
- forced-target pressure through grappling and wrestling

Signature status / mechanic space:

- `mud` or `mired` style debuffs
- temporary defense reduction
- target redirection
- “must target attacker on next attack” control effect

Unit identity direction:

- `mudwrestler`
  - annoying control grunt
  - sticks to a target and forces attention
- `mudslinger`
  - ranged mud pressure
  - softens targets for other pigs
- `mudking`
  - bruiser-controller boss
  - hits hard, wrestles, and makes the player fight on its terms

Specific requested direction:

- melee pig units should gain a `wrestle` ability
- `wrestle` should:
  - deal damage
  - force the target to attack the wrestler with its next attack
  - not stack

What pigs should not feel like:

- elegant tacticians
- delayed combo builders
- status-heavy poison faction

## Kobolds

Tone:

- tinkering
- volatile
- clever
- trap-minded

Core fantasy:

- kobolds win by setting things up and making the battlefield more dangerous over time

Primary mechanics:

- explosives
- countdowns
- delayed triggers
- payoffs that get more dangerous if ignored

Signature status / mechanic space:

- bombs with countdowns
- unstable gadgets
- armed traps
- delayed area or splash effects
- buildup mechanics that pressure the player to respond

Unit identity direction:

- `kobold_shieldbearer`
  - buys time for the device line
  - should help other kobolds survive long enough for setup to matter
- `kobold_skirmisher`
  - light ranged harassment
  - may help arm or finish explosive setups
- `kobold_sharpshooter`
  - higher-threat ranged payoff unit
  - punishes targets softened by gadget pressure
- `kobold_warchief`
  - command-and-chaos boss
  - should feel like the peak of the tinker/explosive theme, not just a bigger archer

What kobolds should not feel like:

- plain ranged attackers with no setup identity
- brute-force melee faction
- generic status spam

## Frogmen

Tone:

- bayou folk
- wet
- rough
- primitive
- slightly redneck

Core fantasy:

- frogmen fight like stubborn swamp dwellers using crude, practical, water-and-muck tactics

Primary mechanics:

- water-themed attacks
- swamp grime
- improvised teamwork
- rough support and rough debuffs

Signature status / mechanic space:

- drenched / soaked / bogged style effects
- crude buffs
- practical sustain or stubborn defense
- dirty debuffs rather than refined control

Unit identity direction:

- `frogman_bruiser`
  - heavy swamp frontliner
  - tough, basic, direct
- `frogman_spearhunter`
  - aggressive primitive striker
  - should feel rougher and more dangerous than disciplined
- `frogman_wardrummer`
  - noisy morale/support piece
  - should feel like a scrappy force multiplier, not a polished buffer
- `frogman_bog_tyrant`
  - swamp boss anchor
  - should feel huge, stubborn, and difficult to push through

What frogmen should not feel like:

- elegant spellcasters
- gadget users
- stealthy precision faction

## Cross-Faction Contrast

Use this shorthand during rebalance:

- `Pigs`: drag you into bad positioning and dirty close combat
- `Kobolds`: build danger over time with devices and delayed payoffs
- `Frogmen`: overwhelm with rough swamp pressure and improvised teamwork

If two factions start sharing the same fight feel, rebalance should push them apart again.

## Design Guardrails

- Each faction should have at least one status family or mechanic family that is mostly theirs.
- Basic attacks alone should not define faction identity.
- Bosses should be the clearest expression of each faction’s theme.
- Grunts should preview the faction’s idea in a simpler form.

## Immediate Rebalance Hooks

Near-term ability concepts to consider:

- `Pigs`
  - `mud toss`
  - `wrestle`
  - `mud stomp`
  - `hog tie`
- `Kobolds`
  - `bomb toss`
  - `arm charge`
  - `unstable device`
  - `fuse lit`
- `Frogmen`
  - `bayou jab`
  - `bog splash`
  - `swamp holler`
  - `reed spear`

These names are placeholders, but the mechanical direction should stay consistent.
