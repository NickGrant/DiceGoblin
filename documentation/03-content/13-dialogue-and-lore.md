---
Title: "Dialogue and Lore Catalog"
Status: Canonical
Last Updated: 2026-08-01
Owner: Narrative Design + Content Design
Depends On:
  - documentation/01-lore/00-world-and-lore.md
  - documentation/03-content/06-biomes-and-regions.md
  - documentation/03-content/10-items-and-consumables.md
  - documentation/03-content/11-loot-and-reward-profiles.md
Category: 03-content
Tags:
  - content
  - dialogue
  - lore
  - narrative
---

# Dialogue and Lore Catalog

## Purpose

Define the canonical run dialogue currently available in Dice Goblins. This document owns each script's identity, title, narrative purpose, participants, region, placement, eligibility, repeatability, choice structure, completion rewards, and Lore Codex classification.

Dialogue-node placement algorithms, seen-state persistence, route unlocking, script loading, presentation defaults, and completion transactions belong in system or technical documentation.

## Catalog Rules

- **Participants** lists characters who have spoken lines in the script. Characters mentioned in conversation are not participants unless they speak.
- **One-time** dialogue stops appearing after the player completes that dialogue id once.
- **Recurring** dialogue may appear on every eligible run.
- **Conditional recurring** dialogue repeats only while its stated progression condition remains true.
- **Self-disabling one-shot** dialogue is not marked one-time, but completion permanently changes its eligibility so it cannot normally recur.
- Completing any dialogue records that dialogue id as seen. Seen state only suppresses entries identified as one-time unless another eligibility rule uses the seen id.
- Only scripts explicitly classified as **Lore** in this catalog are canonical Lore Codex entries. Dialogue-only scripts remain valid dialogue content without becoming Lore pages.

## Current Scope

The current dialogue set contains:

- **11** run-placement definitions
- **12** script ids
- **5** explicitly one-time scripts
- **6** recurring or conditionally recurring scripts
- **1** self-disabling one-shot script
- **7** Lore Codex scripts
- **5** dialogue-only scripts

The script count is one greater than the placement-definition count because the Farm boss introduction selects one of two scripts based on whether the Shop is unlocked.

## Mystic Cave Dialogue

| Key | Title | Placement | Participants | Repeatability | Eligibility | Player choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | Start | Player Goblin, The Whim | One-time | First unresolved Mystic Cave introduction | 3 | Establishes `start-run-kickoff` as seen. | Lore |
| `mystic-cave-wrong-machine-reminder` | A Marvelous Defect | Start | Player Goblin, The Whim | Conditional recurring | Requires `start-run-kickoff`; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | Start | Player Goblin, The Whim | Conditional recurring | Requires `start-run-kickoff` and the Wrong Machine unlock | 3 | None beyond seen-state recording. | Lore |

### Narrative Role

- **The Whim's First Fragment** introduces the player, goblin extinction, The Archivist, the Farm, the Mudking, and the Tooth Collector. It also transforms the player's presentation from primordial fragment to Basic Goblin.
- **A Marvelous Defect** reminds the player to recover the Wrong Machine without advancing permanent progression.
- **The Machine Comes Home** explains Raw Chaos, restored goblin possibilities, and the Wrong Machine's role after its recovery.

## Farm Dialogue

| Key | Title | Placement | Participants | Repeatability | Eligibility | Player choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `farm-boss-intro` | Farm Boss Intro | Before boss | Player Goblin, Mudking | Conditional recurring | Used while the Shop is locked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-boss-intro-shop-unlocked` | Farm Boss Intro Shop Unlocked | Before boss | Player Goblin, Mudking | Recurring | Replaces `farm-boss-intro` after the Shop is unlocked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-shop-unlock` | The Tooth Collector Freed | Before exit | The Tooth Collector, Mudking | One-time | Available until completed once | 0 | Introduces the Shop and the Tooth Collector; the Shop unlock itself is awarded by Farm progression rather than this dialogue completion. | Lore |

### Farm Boss Variant Rule

The Farm has one recurring boss-introduction placement with two canonical scripts:

- before Shop ownership, use `farm-boss-intro`
- after Shop ownership, use `farm-boss-intro-shop-unlocked`

Both scripts depict a confrontation with the Mudking, but the second assumes the Tooth Collector has already been freed and frames the encounter as a rematch.

### Narrative Role

- **Farm Boss Intro** establishes the Mudking's claim over the Farm and the imprisoned Tooth Collector.
- **Farm Boss Intro Shop Unlocked** acknowledges repeat visits and the consequences of the first Farm victory.
- **The Tooth Collector Freed** introduces the Tooth Collector's exchange of monster teeth for useful goods. The player is present contextually but has no spoken lines in the current script.

## Mountains Dialogue

| Key | Title | Placement | Participants | Repeatability | Eligibility | Player choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | Start | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Establishes first contact and enables the repeat search dialogue. | Lore |
| `mountains-wrong-machine-search-repeat` | The High Pass Search | Start | Player Goblin, Kobold Scout | Conditional recurring | Requires Archivist first contact; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mountains-kobold-machine-trail` | Kobold Evidence | Before boss | Player Goblin, Kobold Sentry | One-time | Available while the Wrong Machine is locked and until completed once | 0 | Establishes the machine trail toward the Swamps. | Lore |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | Before boss | Player Goblin, Kobold Sentry | Conditional recurring | Available after the Wrong Machine is unlocked | 3 | None beyond seen-state recording. | Lore |
| `mountains-swamps-lead` | Toward the Swamps | Before exit | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Establishes the Swamps and Bog Tyrant as the next Wrong Machine lead. | Lore |
| `mountains-traveler-consumable-gifts` | Far Gifts | Random route position | Player Goblin, Far Traveler | Self-disabling one-shot | Available while the `consumables` progression flag is absent | 0 | Grants Field Poultice ×1, Travel Ration ×1, and records the `consumables` progression flag. | Dialogue only |

### Narrative Role

- **The Archivist Takes Notice** establishes The Archivist's awareness of the player and frames the goblin as an error in the official record.
- **The High Pass Search** provides a recurring reminder that the Wrong Machine lies beyond the Mountains.
- **Kobold Evidence** identifies goblin construction marks and confirms the machine was taken toward the Swamps.
- **The Recovered Contraption** reacts to the player returning after the Wrong Machine has already been recovered.
- **Toward the Swamps** escalates The Archivist's threat and names the Bog Tyrant as the machine's guardian.
- **Far Gifts** introduces healing and Energy consumables through a lost traveler and supplies the player's first examples.

## Participant Index

| Participant | Role in current dialogue | Current scripts |
| --- | --- | --- |
| Player Goblin | Player viewpoint and selectable voice | All current scripts except `farm-shop-unlock` |
| The Whim | Creator, guide, and chaos-aligned patron | `start-run-kickoff`, `mystic-cave-wrong-machine-reminder`, `mystic-cave-wrong-machine-recovered` |
| Mudking | Farm ruler, boss, and recurring antagonist | `farm-boss-intro`, `farm-boss-intro-shop-unlocked`, `farm-shop-unlock` |
| The Tooth Collector | Economy character freed from the Mudking | `farm-shop-unlock` |
| The Archivist | Central order-aligned antagonist | `mountains-archivist-first-contact`, `mountains-swamps-lead` |
| Kobold Scout | Recurring mountain route informant | `mountains-wrong-machine-search-repeat` |
| Kobold Sentry | Technical witness to the Wrong Machine trail | `mountains-kobold-machine-trail`, `mountains-kobold-machine-recovered` |
| Far Traveler | Neutral visitor who introduces consumables | `mountains-traveler-consumable-gifts` |

The Bog Tyrant and the Wrong Machine are discussed but do not currently speak in any canonical dialogue.

## Choice Structure

Seven scripts contain a three-option player response:

- `start-run-kickoff`
- `mystic-cave-wrong-machine-recovered`
- `farm-boss-intro`
- `farm-boss-intro-shop-unlocked`
- `mountains-archivist-first-contact`
- `mountains-kobold-machine-recovered`
- `mountains-swamps-lead`

Current choices express different shades of goblin personality—usually violence, greed, or goblin logic—but reconverge before the script ends. They do not currently change rewards, future eligibility, relationships, or persistent narrative state.

The remaining five scripts are linear.

## Lore Codex Entries

The following seven dialogue scripts are canonical Lore Codex entries:

| Dialogue key | Lore title | Discovery condition |
| --- | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | Complete the dialogue. |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | Complete the dialogue after recovering the Wrong Machine. |
| `farm-shop-unlock` | The Tooth Collector Freed | Complete the Farm exit dialogue. |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | Complete the first-contact dialogue. |
| `mountains-kobold-machine-trail` | Kobold Evidence | Complete the one-time machine-trail dialogue. |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | Complete the dialogue after recovering the Wrong Machine. |
| `mountains-swamps-lead` | Toward the Swamps | Complete the Mountains exit dialogue. |

The other five script ids are valid dialogue content but are not Lore pages. Completing them may still create seen-state records used by dialogue progression.

## Completion Rewards

Only one current dialogue directly grants assets:

| Dialogue | Permanent progression | Items |
| --- | --- | --- |
| `mountains-traveler-consumable-gifts` | Records the `consumables` progression flag, preventing the encounter from being offered again | Field Poultice ×1; Travel Ration ×1 |

The `consumables` key is a dialogue-progression flag rather than a canonical player-facing feature. It should not appear as a Shop feature or Codex feature entry unless a separate content decision gives it an independent feature identity.

## Open Questions

- **No Swamps dialogue exists.** The current story direction calls for the Bog Tyrant confrontation and recovery of the Wrong Machine in the Swamps, but no current Swamps script covers those events.
- **Farm boss titles are presentation fallbacks.** `farm-boss-intro` and `farm-boss-intro-shop-unlocked` have no authored titles or summaries beyond their generated id-based presentation.
- **Recurring dialogue has no variation pool.** Every eligible revisit repeats the same full script. Future recurring content may need alternate lines, weighted variants, or escalating states.
- **Choices are non-persistent.** Current response branches establish tone only. A future system should explicitly decide whether choices remain expressive or begin affecting relationships, rewards, or later scripts.
- **Lore ownership must respect content classification.** Seen-state records for dialogue-only scripts must not automatically become Lore Codex pages.
- **The `consumables` progression flag is stored like a feature.** Its canonical role is only to record completion of Far Gifts and suppress repeat placement; generic feature presentation should not expose it as a normal feature.
- **The Farm Shop scene omits the player as a speaker.** This may be intentional staging, but the scene should be reviewed if the player is expected to acknowledge the Tooth Collector directly.

## Maintenance Notes

- Add a dialogue entry here before or alongside adding its script or run placement.
- Every entry must identify its spoken participants and effective repeatability.
- Keep placement, eligibility, completion rewards, and Lore classification synchronized with related content catalogs.
- Do not infer Lore status from the existence of a seen-state key; Lore classification must be explicit.
- Keep route insertion, persistence, API behavior, script materialization, and presentation defaults in system or technical documentation.
- Planning-only story beats do not become current dialogue until they receive a complete catalog entry and script.
