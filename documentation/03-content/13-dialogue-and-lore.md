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

Define the canonical run dialogue for the current four-region campaign. This document owns every dialogue identity, authored title and summary, narrative purpose, spoken participants, region, placement, eligibility, repeatability, choice structure, completion reward, recurring-variation requirement, and Lore Codex classification.

Dialogue-node insertion algorithms, seen-state persistence, route unlocking, deterministic variant selection, script loading, presentation defaults, and completion transactions belong in system or technical documentation.

This catalog defines intended content. Any current runtime or script data that does not yet match it is implementation drift to be corrected.

## Catalog Rules

- **Participants** are characters with spoken lines. Mentioned characters, silent figures, machines, and environmental effects are not participants.
- Every script must have an authored title and summary. Humanized identifier fallbacks are not valid final presentation.
- **One-time** dialogue stops appearing after its dialogue id is completed once.
- **Conditional recurring** dialogue repeats only while its stated progression condition remains true.
- **Recurring** dialogue remains eligible on later runs after its prerequisite story state is reached.
- One-time milestone reactions must not recur merely because their prerequisite remains true.
- Completing any dialogue records that dialogue id as seen. Seen state suppresses one-time entries and may gate later entries.
- Only scripts explicitly classified as **Lore** here are canonical Lore Codex entries.
- Current player choices are **voice choices**. They express personality but do not alter rewards, relationships, eligibility, combat state, or future dialogue.
- Recurring dialogue must use a variation pool and must not replay the same full exchange on every eligible run.

## Canonical Scope

The complete current-campaign dialogue plan contains:

- **15** run-placement definitions
- **16** script ids
- **10** one-time scripts
- **4** conditional recurring scripts
- **2** recurring scripts
- **9** Lore Codex scripts
- **7** dialogue-only scripts
- **9** spoken participant identities

The script count is one greater than the placement-definition count because the Farm boss placement selects one of two scripts based on Shop ownership.

## Repeatability Vocabulary

| Type | Content meaning | Current count |
| --- | --- | ---: |
| One-time | A story, tutorial, reward, or milestone scene that is permanently suppressed after completion. | 10 |
| Conditional recurring | A reminder or unresolved-conflict scene that remains eligible only while a progression condition is true. | 4 |
| Recurring | A revisit scene that remains eligible after its story state is established. | 2 |

## Mystic Cave Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | The Whim gives its newly restored goblin a shape, a purpose, and a first destination. | Start | Player Goblin, The Whim | One-time | First unresolved Mystic Cave introduction | 3 | Records the introduction as complete. | Lore |
| `mystic-cave-wrong-machine-reminder` | A Marvelous Defect | The Whim points its fragment toward the Wrong Machine and offers exactly enough warning to make it irresistible. | Start | Player Goblin, The Whim | Conditional recurring | Requires `start-run-kickoff`; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | The Whim reacts to the recovered Wrong Machine and explains the reconstruction work it makes possible. | Start | Player Goblin, The Whim | One-time | Requires `start-run-kickoff` and the Wrong Machine unlock; unavailable after completion | 3 | Records The Whim's recovery reaction as complete. | Lore |

### Mystic Cave Narrative Requirements

- **The Whim's First Fragment** introduces goblin extinction, The Archivist, the Farm, the Mudking, and the Tooth Collector. It transforms the player's presentation from primordial fragment to Basic Goblin.
- **A Marvelous Defect** is a short reminder, not a repeated exposition scene. Its variants should emphasize urgency, unsafe machine behavior, or The Whim's delight at the machine's impossibility.
- **The Machine Comes Home** is a milestone reaction and therefore occurs once. It explains Raw Chaos, restored goblin possibilities, and why the machine's return escalates the conflict with The Archivist.

## Farm Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `farm-boss-intro` | Mud in the Way | The Mudking claims the Farm, its mud, and the imprisoned Tooth Collector before the first confrontation. | Before boss | Player Goblin, Mudking | Conditional recurring | Used while the Shop is locked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-boss-intro-shop-unlocked` | Back Into the Pen | The Mudking recognizes the returning goblin and turns another Farm visit into a personal rematch. | Before boss | Player Goblin, Mudking | Recurring | Replaces `farm-boss-intro` after the Shop is unlocked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-shop-unlock` | The Tooth Collector Freed | The defeated Mudking releases the Tooth Collector, who establishes the exchange of teeth for useful goods. | Before exit | Player Goblin, The Tooth Collector, Mudking | One-time | Available until completed once after the first Mudking victory | 0 | Introduces the Shop and records the rescue scene. Shop ownership remains part of Farm progression. | Lore |

### Farm Boss Variant Rule

The Farm has one boss-dialogue placement with two canonical script states:

- while Shop ownership is absent, use `farm-boss-intro`
- after Shop ownership, use `farm-boss-intro-shop-unlocked`

The first script may recur after an unsuccessful Farm attempt, but it should use a shortened reprise after its first completion rather than replaying the entire introductory exchange.

### Farm Narrative Requirements

- **Mud in the Way** establishes the Mudking's territorial authority and identifies the Tooth Collector as his prisoner.
- **Back Into the Pen** acknowledges previous Farm damage and treats later battles as increasingly personal rematches.
- **The Tooth Collector Freed** includes at least one spoken acknowledgment from the Player Goblin. The Tooth Collector explains that ordinary monster teeth are currency and offers to exchange them for useful goods. The scene does not imply that every tooth has a hidden metaphysical purpose.

## Mountains Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | The Archivist discovers that a goblin exists and decides the record must be corrected. | Start | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Enables the unresolved-machine search state. | Lore |
| `mountains-wrong-machine-search-repeat` | The High Pass Search | A kobold scout warns the goblin about the unsafe but functional machine hidden beyond the pass. | Start | Player Goblin, Kobold Scout | Conditional recurring | Requires Archivist first contact; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mountains-kobold-machine-trail` | Kobold Evidence | A kobold sentry identifies old goblin tool marks and traces the Wrong Machine toward the Swamps. | Before boss | Player Goblin, Kobold Sentry | One-time | Available while the Wrong Machine is locked and until completed once | 0 | Establishes the machine trail toward the Swamps. | Lore |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | A kobold sentry realizes that the goblins recovered the machine the Mountains tried to keep documented and distant. | Before boss | Player Goblin, Kobold Sentry | One-time | Available after the Wrong Machine is unlocked and until completed once | 3 | Records the kobolds' recovery reaction as complete. | Lore |
| `mountains-swamps-lead` | Toward the Swamps | The Archivist names the Bog Tyrant as the Wrong Machine's guardian and warns the goblin away. | Before exit | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Establishes the Swamps and Bog Tyrant as the next campaign objective. | Lore |
| `mountains-traveler-consumable-gifts` | Far Gifts | A lost traveler mistakes the goblin for a mountain local and shares supplies from another road. | Random route position | Player Goblin, Far Traveler | One-time | Available until completed once | 0 | Grants Field Poultice ×1 and Travel Ration ×1. | Dialogue only |

### Mountains Narrative Requirements

- **The Archivist Takes Notice** frames the Player Goblin as an error rather than a survivor and establishes The Archivist's detached but hostile voice.
- **The High Pass Search** is a short recurring reminder. Its variants should rotate between dangerous route advice, evidence that the machine moved south, and signs that Library agents are closing in.
- **Kobold Evidence** confirms that the machine is genuinely goblin-made and works precisely because it rejects stable construction.
- **The Recovered Contraption** is a one-time milestone reaction. It should not recur on every Mountains visit after recovery.
- **Toward the Swamps** escalates The Archivist's response and makes clear that the Bog Tyrant guards the machine from use, not merely theft.
- **Far Gifts** is a true one-time tutorial encounter. Its own seen state suppresses recurrence; it does not create a pseudo-feature named `consumables`.

## Swamps Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `swamps-bog-tyrant-first-confrontation` | Contraband of the Bog | The Bog Tyrant reveals that the Wrong Machine is dangerous contraband and refuses to surrender it. | Before boss | Player Goblin, Bog Tyrant | One-time | Wrong Machine locked; first Bog Tyrant confrontation not yet completed | 3 | Establishes the machine-defense conflict. | Lore |
| `swamps-bog-tyrant-machine-defense-repeat` | Still Under Guard | The Bog Tyrant continues defending the Wrong Machine after surviving an earlier challenge. | Before boss | Player Goblin, Bog Tyrant | Conditional recurring | Requires the first confrontation; available while the Wrong Machine remains locked | 0 | None beyond seen-state recording. | Dialogue only |
| `swamps-wrong-machine-recovered` | The Wrong Machine Reclaimed | The defeated Bog Tyrant yields the machine and warns what its return will bring. | Before exit | Player Goblin, Bog Tyrant | One-time | First victorious Swamps run while the Wrong Machine is locked | 3 | Unlocks the Wrong Machine and records its recovery. | Lore |
| `swamps-bog-tyrant-rematch` | The Bog Remembers | The Bog Tyrant confronts the goblin who stole the Wrong Machine and destabilized his rule. | Before boss | Player Goblin, Bog Tyrant | Recurring | Available after the Wrong Machine is unlocked | 3 | None beyond seen-state recording. | Dialogue only |

### Swamps Story Sequence

The Swamps arc follows this order:

1. **Contraband of the Bog** introduces the Bog Tyrant and establishes why the frogmen hold the Wrong Machine.
2. If the player returns before defeating him, **Still Under Guard** supplies shorter confrontation variants without replaying the full reveal.
3. After the first Bog Tyrant victory, **The Wrong Machine Reclaimed** completes the opening campaign arc and unlocks the Wrong Machine.
4. Later Swamps visits use **The Bog Remembers**, which treats the fight as a rematch and never implies that the machine can be recovered a second time.

### Required Beats: Contraband of the Bog

- The Bog Tyrant recognizes the player as a living goblin.
- He identifies the Wrong Machine as working contraband rather than broken machinery.
- He states that frogman control prevents the machine from returning settled things to possibility.
- His relationship with the Library is pragmatic: he benefits from regional authority and is expected to keep the machine contained.
- The Player Goblin receives three voice choices expressing violence, greed, or goblin logic.
- Every branch ends with the Bog Tyrant refusing surrender and initiating the boss battle.

### Required Beats: The Wrong Machine Reclaimed

- The defeated Bog Tyrant allows the goblins to take the machine but does not endorse its use.
- He warns that The Archivist will notice as soon as it begins operating.
- The machine hums, shudders, or otherwise reacts, but it is not a speaking participant.
- The Player Goblin receives three voice choices focused on rebuilding goblins, making useful disasters, or openly provoking The Archivist.
- Every branch ends with the machine returning to goblin control and the Wrong Machine feature becoming available.

### Required Beats: The Bog Remembers

- The Bog Tyrant acknowledges that the machine is gone and cannot be reclaimed from this battle.
- His anger centers on lost authority, regional instability, and humiliation rather than repeating the original containment exposition.
- The scene remains short enough for recurring use.
- Voice choices may taunt the Tyrant, demand more teeth, or claim ownership of the swamp through goblin logic.

## Participant Index

| Participant | Narrative role | Canonical scripts |
| --- | --- | --- |
| Player Goblin | Player viewpoint and selectable voice | Every script |
| The Whim | Creator, guide, and chaos-aligned patron | `start-run-kickoff`, `mystic-cave-wrong-machine-reminder`, `mystic-cave-wrong-machine-recovered` |
| Mudking | Farm ruler, boss, and recurring antagonist | `farm-boss-intro`, `farm-boss-intro-shop-unlocked`, `farm-shop-unlock` |
| The Tooth Collector | Economy character rescued from the Mudking | `farm-shop-unlock` |
| The Archivist | Central order-aligned antagonist | `mountains-archivist-first-contact`, `mountains-swamps-lead` |
| Kobold Scout | Recurring route informant | `mountains-wrong-machine-search-repeat` |
| Kobold Sentry | Technical witness to the Wrong Machine trail | `mountains-kobold-machine-trail`, `mountains-kobold-machine-recovered` |
| Far Traveler | Neutral visitor who introduces consumables | `mountains-traveler-consumable-gifts` |
| Bog Tyrant | Swamp ruler, Wrong Machine jailer, and recurring boss | All four Swamps scripts |

The Wrong Machine is an active scene object but not a speaking participant in the current campaign.

## Player Voice Choice Policy

Ten scripts contain a three-option Player Goblin response:

- `start-run-kickoff`
- `mystic-cave-wrong-machine-recovered`
- `farm-boss-intro`
- `farm-boss-intro-shop-unlocked`
- `mountains-archivist-first-contact`
- `mountains-kobold-machine-recovered`
- `mountains-swamps-lead`
- `swamps-bog-tyrant-first-confrontation`
- `swamps-wrong-machine-recovered`
- `swamps-bog-tyrant-rematch`

Current options should normally represent:

- **Violence:** direct threats, confidence, or enthusiasm for fighting.
- **Greed:** teeth, loot, ownership, or transactional self-interest.
- **Goblin logic:** internally consistent nonsense, technical literalism, or opportunistic reinterpretation.

These are voice choices only. They reconverge within the current scene and are not persisted. A future relationship or branching-story system must introduce a separate choice classification rather than silently changing the meaning of these existing options.

The remaining six scripts are linear.

## Recurring Variation Requirements

Recurring dialogue uses the same dialogue identity but contains a minimum of three authored exchange variants. Variant selection must avoid an immediate repeat for the same player when alternatives are available.

| Dialogue family | Minimum variants | Required variation topics |
| --- | ---: | --- |
| `mystic-cave-wrong-machine-reminder` | 3 | The machine's impossible behavior; The Whim's impatience; a warning not to climb inside it. |
| `farm-boss-intro-shop-unlocked` | 3 | Damage from prior visits; new but ineffective defenses; Mudking's resentment over the Tooth Collector and hidden teeth. |
| `mountains-wrong-machine-search-repeat` | 3 | Trapped or unstable routes; tool marks leading south; Library agents or inspectors approaching. |
| `swamps-bog-tyrant-machine-defense-repeat` | 3 | Continued containment duty; failed attempts to stabilize the machine; growing fear that goblins will reclaim it. |
| `swamps-bog-tyrant-rematch` | 3 | Lost authority; swamp instability after recovery; personal humiliation and revenge. |

The conditional pre-Shop Farm confrontation may use one shortened reprise after the introductory exchange if the player returns before unlocking the Shop.

Recurring variants should be shorter than milestone scenes and must not repeatedly deliver information the player has already canonically learned.

## Lore Codex Entries

The following nine scripts are canonical Lore Codex entries:

| Dialogue key | Lore title | Discovery condition |
| --- | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | Complete the dialogue. |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | Complete the one-time reaction after recovering the Wrong Machine. |
| `farm-shop-unlock` | The Tooth Collector Freed | Complete the Farm rescue dialogue. |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | Complete the first-contact dialogue. |
| `mountains-kobold-machine-trail` | Kobold Evidence | Complete the one-time machine-trail dialogue. |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | Complete the one-time kobold reaction after recovery. |
| `mountains-swamps-lead` | Toward the Swamps | Complete the Mountains exit dialogue. |
| `swamps-bog-tyrant-first-confrontation` | Contraband of the Bog | Complete the first Bog Tyrant confrontation. |
| `swamps-wrong-machine-recovered` | The Wrong Machine Reclaimed | Complete the Swamps recovery dialogue. |

The other seven script ids are valid dialogue content but are not Lore pages. Completing them creates seen-state records only.

## Completion Rewards

| Dialogue | Permanent progression | Items |
| --- | --- | --- |
| `mountains-traveler-consumable-gifts` | One-time completion recorded by the dialogue's own seen state | Field Poultice ×1; Travel Ration ×1 |
| `swamps-wrong-machine-recovered` | Unlocks the Wrong Machine | None |

No generic dialogue-completion key should be exposed as a player-facing feature unless that feature has its own canonical content definition.

## Narrative Continuity Rules

- The Tooth Collector cannot speak as a rescued hub character before `farm-shop-unlock` is complete.
- The Archivist first addresses the player in `mountains-archivist-first-contact`; later Archivist dialogue assumes that contact.
- No dialogue may state that the Wrong Machine is broken or malfunctioning. Its name is a proper name and its impossible operation is intentional.
- Pre-recovery dialogue treats the machine as controlled by the Bog Tyrant. Post-recovery dialogue treats it as permanently returned to goblin control.
- The Bog Tyrant remains available as a recurring regional ruler after defeat.
- The Wrong Machine does not speak in the current campaign.
- Completing a recurring dialogue does not make it a Lore entry unless explicitly listed above.

## Resolved Content Decisions

- The Farm boss scripts now have authored titles and summaries.
- The Player Goblin speaks in the Tooth Collector rescue scene.
- The Swamps now have a complete confrontation, failed-attempt, recovery, and rematch sequence.
- Wrong Machine recovery is a one-time Swamps milestone rather than an off-screen feature award.
- The Whim and kobold post-recovery reactions are one-time milestone scenes.
- Far Gifts is explicitly one-time and uses its own seen state instead of a pseudo-feature flag.
- Current choices are canonically voice-only and non-persistent.
- Recurring dialogue requires authored variation pools.
- Lore ownership is explicit and is not inferred from generic dialogue seen state.

## Maintenance Notes

- Add or revise a dialogue entry here before or alongside its script and run placement.
- Every script must identify spoken participants, repeatability, eligibility, title, summary, and Lore classification.
- Keep placement, completion rewards, and progression conditions synchronized with biome, item, reward, and Codex catalogs.
- Do not infer Lore status from a dialogue seen-state key.
- Keep route insertion, persistence, deterministic selection, API behavior, script materialization, and presentation fallbacks outside this document.
- Recurring dialogue must remain concise, state-aware, and varied.
