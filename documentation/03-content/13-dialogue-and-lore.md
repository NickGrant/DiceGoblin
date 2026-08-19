---
Title: "Dialogue and Lore Catalog"
Status: Canonical
Last Updated: 2026-08-03
Owner: Narrative Design + Content Design
Depends On:
  - documentation/01-lore/00-world-and-lore.md
  - documentation/01-lore/01-story-and-biome-progression.md
  - documentation/01-lore/02-character-profiles.md
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

Define the canonical run dialogue for the current four-region campaign. This document owns dialogue identity, authored title and summary, narrative purpose, spoken participants, region, placement, eligibility, repeatability, choice structure, completion effects, and Lore Codex classification.

Dialogue-node insertion algorithms, seen-state persistence, route unlocking, deterministic selection, script loading, presentation defaults, and completion transactions belong in system or technical documentation.

This catalog defines intended content. Runtime or script data that does not match it is implementation drift to be corrected separately.

## Catalog Rules

- **Participants** are characters with spoken lines. Mentioned characters, silent figures, machines, and environmental effects are not participants.
- Every script requires an authored title and summary. Humanized identifier fallbacks are not valid final presentation.
- **One-time** dialogue stops appearing after its dialogue id is completed once.
- **Conditional recurring** dialogue repeats only while its progression condition remains true.
- **Recurring** dialogue remains eligible after its prerequisite story state is reached.
- Completing dialogue records its id as seen. Seen state suppresses one-time entries and may gate later entries.
- Only scripts explicitly classified as **Lore** create Lore Codex entries.
- Player choices are currently voice choices. They express personality but do not alter rewards, relationships, combat, or future eligibility.
- Recurring dialogue uses authored variations and should avoid immediate repetition when alternatives exist.
- Exposition should be staged across repeat runs rather than concentrated in the first encounter.

## Repeat-Run Story Policy

Repeat-run story inventory is designed around the expected number of opportunities a player will actually see.

- A boss guarding a major unlock should normally have no more than **two pre-unlock boss conversations**: an initial confrontation and one conditional repeat family.
- Important post-unlock regions may have **three post-unlock conversation identities** that reveal consequences, backstory, or future hooks over subsequent runs.
- Sequential post-unlock conversations are unlocked by seen state. They should not compete randomly before their predecessors are completed.
- Once sequential exposition is exhausted, the final identity may remain recurring with multiple banter variants.
- Linear exposition is preferred when selectable answers would create expensive branches that disappear after one viewing.
- Voice choices are reserved for major confrontations, milestone reactions, and durable recurring banter.

## Canonical Scope

The current dialogue inventory contains:

- **16** run-placement definitions
- **17** script ids
- **11** one-time scripts
- **4** conditional recurring scripts
- **2** recurring scripts
- **9** Lore Codex scripts
- **8** dialogue-only scripts
- **9** spoken participant identities

The Farm boss uses one placement that selects between its pre-Shop and post-Shop script states. Mountain and Swamp boss placements use ordered eligibility so only one boss conversation is selected per run.

## Repeatability Vocabulary

| Type | Content meaning | Current count |
| --- | --- | ---: |
| One-time | A story, tutorial, reward, or milestone scene permanently suppressed after completion. | 13 |
| Conditional recurring | A reminder or unresolved-conflict scene eligible only while its condition remains true. | 5 |
| Recurring | A revisit scene that remains eligible after its prerequisite state is reached. | 3 |

## Mystic Cave Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `start-run-kickoff` | The Whim's First Fragment | The Whim gives its newly restored goblin a shape, a purpose, and a first destination. | Start | Player Goblin, The Whim | One-time | First unresolved Mystic Cave introduction | 3 | Records the introduction as complete. | Lore |
| `mystic-cave-wrong-machine-reminder` | A Marvelous Defect | The Whim points its fragment toward the Wrong Machine and offers exactly enough warning to make it irresistible. | Start | Player Goblin, The Whim | Conditional recurring | Requires `start-run-kickoff`; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mystic-cave-wrong-machine-recovered` | The Machine Comes Home | The Whim reacts to the recovered Wrong Machine and explains the reconstruction work it makes possible. | Start | Player Goblin, The Whim | One-time | Requires `start-run-kickoff` and the Wrong Machine unlock; unavailable after completion | 3 | Records The Whim's recovery reaction as complete. | Lore |

### Mystic Cave Narrative Requirements

- **The Whim's First Fragment** introduces goblin extinction, The Archivist, The Farm, the Mudking, and the Tooth Collector. It transforms the player's presentation from primordial fragment to Basic Goblin.
- **A Marvelous Defect** is a short reminder, not repeated exposition. Its variants emphasize unsafe machine behavior, The Whim's impatience, or a warning not to climb inside it.
- **The Machine Comes Home** occurs once. It explains Raw Chaos, restored goblin possibilities, and why operating the Machine escalates the conflict.
- The Whim may imply that the player's spark escaped through somebody else's curiosity, but the full custody chain is reserved for Mountain and Swamp revisits.

## Farm Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `farm-boss-intro` | Mud in the Way | The Mudking claims The Farm, its mud, and the imprisoned Tooth Collector before the first confrontation. | Before boss | Player Goblin, Mudking | Conditional recurring | Used while the Shop is locked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-boss-intro-shop-unlocked` | Back Into the Pen | The Mudking recognizes the returning goblin and turns another Farm visit into a personal rematch. | Before boss | Player Goblin, Mudking | Recurring | Replaces `farm-boss-intro` after the Shop is unlocked | 3 | None beyond seen-state recording. | Dialogue only |
| `farm-shop-unlock` | The Tooth Collector Freed | The defeated Mudking releases the Tooth Collector, who establishes the exchange of teeth for useful goods. | Before exit | Player Goblin, The Tooth Collector, Mudking | One-time | Available until completed once after the first Mudking victory | 0 | Introduces the Shop and records the rescue scene. | Lore |

### Farm Boss Selection

- While Shop ownership is absent, use `farm-boss-intro`.
- After Shop ownership, use `farm-boss-intro-shop-unlocked`.
- After the initial pre-Shop exchange, failed attempts use a shortened reprise rather than replaying the complete introduction.

### Farm Narrative Requirements

- The Mudking is dumb, pompous, territorial, and sincerely convinced that ordinary mud and broken farm structures are royal accomplishments.
- **Mud in the Way** establishes the Tooth Collector as his prisoner.
- **Back Into the Pen** rotates between damage from prior visits, new ineffective defenses, and resentment over the Tooth Collector and missing teeth.
- **The Tooth Collector Freed** explains ordinary monster teeth as currency without assigning them a required hidden cosmological function.

## Mountains Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `mountains-archivist-first-contact` | The Archivist Takes Notice | The Archivist discovers that a goblin exists and decides the record must be corrected. | Start | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Enables the unresolved-machine search state. | Lore |
| `mountains-wrong-machine-search-repeat` | The High Pass Search | A kobold scout warns the goblin about the unsafe but functional Machine and the technical authority guarding its records. | Start | Player Goblin, Kobold Scout | Conditional recurring | Requires Archivist first contact; available while the Wrong Machine is locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mountains-kobold-machine-trail` | The Chief Engineer's Record | The Chief Engineer admits that The Archivist delivered the Wrong Machine to the kobolds for controlled study and that it was later transferred south. | Before boss | Player Goblin, Kobold Chief Engineer | One-time | Wrong Machine locked; first Chief Engineer confrontation not yet completed | 0 | Establishes the Archivist-to-kobold-to-frogman custody chain. | Lore |
| `mountains-chief-engineer-containment-repeat` | Controlled Conditions | The Chief Engineer attempts to contain the goblin and defend his records after surviving an earlier challenge. | Before boss | Player Goblin, Kobold Chief Engineer | Conditional recurring | Requires `mountains-kobold-machine-trail`; available while the Wrong Machine remains locked | 0 | None beyond seen-state recording. | Dialogue only |
| `mountains-kobold-machine-recovered` | The Recovered Contraption | The Chief Engineer realizes that the goblins recovered the Machine and that his old experiment may become visible again. | Before boss | Player Goblin, Kobold Chief Engineer | One-time | Wrong Machine unlocked; unavailable after completion | 3 | Records the kobolds' first recovery reaction. | Lore |
| `mountains-chief-engineer-spark-confession` | The Unscheduled Output | On a later visit, the Chief Engineer admits that kobold experiments released one impossible spark before the Machine was transferred. | Before boss | Player Goblin, Kobold Chief Engineer | One-time | Requires `mountains-kobold-machine-recovered`; unavailable after completion | 0 | Reveals the likely source of the player's manifestation. | Dialogue only |
| `mountains-chief-engineer-prior-custody` | Before the Workshop | The Chief Engineer reveals that The Archivist's records begin before kobold custody and that another group originally found the Machine. | Before boss | Player Goblin, Kobold Chief Engineer | Recurring | Requires `mountains-chief-engineer-spark-confession` | 0 | First completion establishes the later-biome finder hook; later uses rotate technical banter. | Dialogue only |
| `mountains-swamps-lead` | Toward the Swamps | The Archivist names the Bog Tyrant as the Wrong Machine's guardian and warns the goblin away. | Before exit | Player Goblin, The Archivist | One-time | Available until completed once | 3 | Establishes the Swamps and Bog Tyrant as the next campaign objective. | Lore |
| `mountains-llamaver-energy-gift` | A Brightly Misplaced Supply | Llamaver mistakes the goblin for someone expected, or possibly unexpected, and shares an energy-restoring ration first. | Random route position | Player Goblin, Llamaver | One-time | Available until completed once | 0 | Unlocks consumables and grants Travel Ration x1. | Dialogue only |
| `mountains-llamaver-health-gift` | The Other Useful Bundle | Llamaver remembers the second half of their helpful errand and offers a field poultice for wounded run units. | Random route position | Player Goblin, Llamaver | One-time | Requires `mountains-llamaver-energy-gift`; available until completed once | 0 | Grants Field Poultice x1. | Dialogue only |

### Mountains Narrative Requirements

- **The Archivist Takes Notice** frames the Player Goblin as an error rather than a survivor and establishes The Archivist's detached but hostile voice.
- **The High Pass Search** is a short recurring reminder. Its variants should rotate between dangerous route advice, evidence that the machine moved south, and signs that Library agents are closing in.
- **Kobold Evidence** confirms that the machine is genuinely goblin-made and works precisely because it rejects stable construction.
- **The Recovered Contraption** is a one-time milestone reaction. It should not recur on every Mountains visit after recovery.
- **Toward the Swamps** escalates The Archivist's response and makes clear that the Bog Tyrant guards the machine from use, not merely theft.
- **A Brightly Misplaced Supply** and **The Other Useful Bundle** are a two-part consumable tutorial. Llamaver is nonbinary, inattentive, and optimistic: they lose the thread, recover it cheerfully, and treat mistakes as promising discoveries. The first encounter introduces energy consumables and the second introduces health consumables. Each encounter's own seen state suppresses recurrence; the first encounter may still grant the consumables feature unlock needed by runtime systems.

## Swamps Dialogue

| Key | Title | Summary | Placement | Participants | Repeatability | Eligibility | Choices | Completion effect | Classification |
| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| `swamps-bog-tyrant-first-confrontation` | Contraband of the Bog | The Bog Tyrant reveals that the Wrong Machine is dangerous contraband and refuses to surrender it. | Before boss | Player Goblin, Bog Tyrant | One-time | Wrong Machine locked; first Bog Tyrant confrontation not yet completed | 3 | Establishes the machine-defense conflict. | Lore |
| `swamps-bog-tyrant-machine-defense-repeat` | Still Under Guard | The Bog Tyrant continues defending the Wrong Machine after surviving an earlier challenge. | Before boss | Player Goblin, Bog Tyrant | Conditional recurring | Requires the first confrontation; available while the Wrong Machine remains locked | 0 | None beyond seen-state recording. | Dialogue only |
| `swamps-wrong-machine-recovered` | The Wrong Machine Reclaimed | The defeated Bog Tyrant yields the Machine and warns what its return will bring. | Before exit | Player Goblin, Bog Tyrant | One-time | First victorious Swamps run while the Wrong Machine is locked | 3 | Unlocks the Wrong Machine and records its recovery. | Lore |
| `swamps-bog-tyrant-rematch` | The Bog Remembers | On the first post-recovery revisit, the Bog Tyrant confronts the goblin who publicly broke his authority. | Before boss | Player Goblin, Bog Tyrant | One-time | Wrong Machine unlocked; unavailable after completion | 3 | Establishes humiliation and lost regional control. | Dialogue only |
| `swamps-bog-tyrant-kobold-transfer` | They Touched It | On the next revisit, the Bog Tyrant explains why the Machine was taken from the kobolds and placed under frogman guard. | Before boss | Player Goblin, Bog Tyrant | One-time | Requires `swamps-bog-tyrant-rematch`; unavailable after completion | 0 | Reveals the kobold containment incident from the frogman perspective. | Dialogue only |
| `swamps-bog-tyrant-before-kobolds` | Somebody Dug It Up | The Bog Tyrant confirms that the kobolds received the Machine from The Archivist after somebody else found it. | Before boss | Player Goblin, Bog Tyrant | Recurring | Requires `swamps-bog-tyrant-kobold-transfer` | 0 | First completion establishes the original-finder hook; later uses rotate rematch banter. | Dialogue only |

### Swamps Boss Sequence

Before recovery, the boss placement selects:

1. `swamps-bog-tyrant-first-confrontation` for the first confrontation.
2. `swamps-bog-tyrant-machine-defense-repeat` on later attempts while the Machine remains locked.

After recovery, the placement selects in order:

1. `swamps-bog-tyrant-rematch`.
2. `swamps-bog-tyrant-kobold-transfer`.
3. `swamps-bog-tyrant-before-kobolds`, which then remains the recurring Swamp boss conversation family.

This deliberately limits the short pre-recovery window to two dialogue identities while preserving three post-recovery conversations for repeat runs.

### Required Beats: Contraband of the Bog

- The Bog Tyrant recognizes the player as a living goblin.
- He identifies the Wrong Machine as working contraband rather than broken machinery.
- He states that frogman control prevents the Machine from returning settled things to possibility.
- His relationship with the Library is pragmatic: he benefits from regional authority and is expected to keep the Machine contained.
- Every branch ends with refusal and the boss battle.

### Required Beats: The Wrong Machine Reclaimed

- The defeated Bog Tyrant allows the goblins to take the Machine without endorsing its use.
- He warns that The Archivist will notice when it begins operating.
- The Machine reacts physically but is not a speaking participant.
- Voice choices focus on rebuilding goblins, making useful disasters, or provoking The Archivist.

### Required Beats: The Bog Remembers

- The Bog Tyrant acknowledges that the Machine is gone and cannot be recovered through this rematch.
- His anger centers on public humiliation, weakened authority, and regional instability.
- He remains loud, direct, and physically threatening rather than philosophical.

### Required Beats: They Touched It

- The Bog Tyrant reduces the kobold failure to one direct accusation: they were told to study the Machine and could not stop touching it.
- He witnessed or received credible evidence of an impossible containment incident.
- He does not explain the released spark in technical terms.
- His decision to lock the Machine away was both politically useful and sincerely motivated by danger.

### Required Beats: Somebody Dug It Up

- The Bog Tyrant confirms that frogman custody followed kobold custody.
- He states that The Archivist brought the Machine to the kobolds after another group found or excavated it.
- He either does not know or refuses to provide the original finders' identity.
- Later variants shift to lost authority, swamp instability, revenge, tolls, or demands that the goblin stop returning.

## Participant Index

| Participant | Narrative role | Canonical scripts |
| --- | --- | --- |
| Player Goblin | Player viewpoint and selectable voice | Every script |
| The Whim | Creator, guide, and chaos-aligned patron | Mystic Cave scripts |
| Mudking | Farm ruler, boss, and recurring antagonist | Farm boss scripts and Shop rescue |
| The Tooth Collector | Economy character rescued from the Mudking | `farm-shop-unlock` |
| The Archivist | Central order-aligned antagonist | `mountains-archivist-first-contact`, `mountains-swamps-lead` |
| Kobold Scout | Recurring route informant | `mountains-wrong-machine-search-repeat` |
| Kobold Sentry | Technical witness to the Wrong Machine trail | `mountains-kobold-machine-trail`, `mountains-kobold-machine-recovered` |
| Llamaver | Nonbinary, inattentive, optimistic traveler who introduces consumables | `mountains-llamaver-energy-gift`, `mountains-llamaver-health-gift` |
| Bog Tyrant | Swamp ruler, Wrong Machine jailer, and recurring boss | All four Swamps scripts |

The Wrong Machine is an active scene object but not a speaking participant in the current campaign.

## Player Voice Choice Policy

The following scripts contain three Player Goblin responses:

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

New short-lived exposition scripts are intentionally linear. Their purpose is to expose one clear piece of story rather than create branches that most players can only see once.

Player response families remain:

- **Violence:** threats, confidence, or enthusiasm for fighting.
- **Greed:** teeth, loot, ownership, or transactional self-interest.
- **Goblin logic:** internally consistent nonsense, technical literalism, or opportunistic reinterpretation.

These are voice choices only and reconverge inside the current scene.

## Recurring Variation Requirements

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

The other eight script ids are valid dialogue content but are not Lore pages. Completing them creates seen-state records only.

## Completion Rewards

| Dialogue | Permanent progression | Items |
| --- | --- | --- |
| `mountains-llamaver-energy-gift` | Unlocks consumables; one-time completion recorded by the dialogue's own seen state | Travel Ration x1 |
| `mountains-llamaver-health-gift` | One-time completion recorded by the dialogue's own seen state | Field Poultice x1 |
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
- Llamaver's consumable introduction is split into energy first, then health, and both scenes use their own seen state for suppression.
- Current choices are canonically voice-only and non-persistent.
- Recurring dialogue requires authored variation pools.
- Lore ownership is explicit and is not inferred from generic dialogue seen state.

## Maintenance Notes

- Keep character voice synchronized with `documentation/01-lore/02-character-profiles.md`.
- Add dialogue identities here before implementing scripts or placement data.
- Preserve ordered eligibility when multiple one-time conversations share a placement.
- Do not add more pre-unlock boss exposition than the expected number of failed attempts can support.
- Use later repeat runs for consequences and backstory rather than replaying the original objective.
- Keep Lore Codex classification synchronized with the Codex catalog when new entries are promoted from dialogue-only to Lore.
