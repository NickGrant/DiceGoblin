---
Title: "vNext Progression State Model"
Status: Accepted
Last Updated: 2026-09-09
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-currency-economy-model.md
Category: 07-development-path
Tags:
  - vnext
  - progression
  - unlocks
  - codex
  - dialogue
  - objectives
---

# vNext Progression State Model

## Decision

vNext should not introduce a generic player-progression, historical-event, or story-flag layer unless a future mechanic demonstrates a concrete need for one.

Permanent progression should be represented through owned unique rewards wherever practical. Mutable progress should remain inside the domain that actually needs to track it.

The guiding rule is:

> Persist the durable result the game cares about, not every historical fact that produced it.

## Unlocks

Unlocks represent permanent access, capability, or eligibility granted to the player.

Examples include:

- region access
- feature/facility access
- unit-type access
- restored kin/racial variants
- other permanent capabilities

Unlocks are granted through the accepted event-to-reward model and are unique rewards.

If the player already owns an unlock and that unlock is rolled again, the reward resolves to nothing under the normal duplicate-reward rule.

## Region Completion

vNext does not require a separate generic `region complete` progression state for current gameplay.

If defeating a region boss should open the next biome, the boss-node completion event may award the next region unlock directly. The opening progression has a different authored order: completing the Whim's onboarding grants Farm access; first Mudking victory grants Shop access and leads to the Tooth Collector rescue conversation. Mountains remain locked until the later post-Farm Whim beat.

Example:

```text
Whim onboarding completed
        |
        v
Basic Goblin Warband and Farm unlock granted atomically
        |
        v
Mudking boss node completed
        |
        +--> normal rewards and Shop access
        +--> durable Tooth Collector rescue dialogue node
        |
        v
Tooth Collector rescue knowledge owned
        +--> Camp Whim conversation becomes available
        |
        v
Whim explains the missing Wrong Machine and the kobold lead
        +--> search knowledge and Mountains unlock in one completion
```

A separate Farm-completed record should only be introduced later if a concrete feature needs completion history itself, such as completion counts, difficulty-specific clears, first-clear timestamps, ranks, or achievements.

The Camp Whim interaction is eligible only while the player owns Shop access and Tooth Collector rescue knowledge but has not yet learned the search or unlocked Mountains. Its dialogue completion grants meaningful search knowledge and Mountains access together through the existing reward transaction. The client sees only the current eligible dialogue ID, never future rewards or prerequisite identities. Wrong Machine capability access remains locked.

## Kin Restoration and Ownership

vNext does not require a separate first-kin-ownership flag.

The kin unlock itself is the authoritative durable state indicating that the kin has been restored and is available to the player.

The Wrong Machine should distinguish two cases:

### Kin not yet unlocked

Using the Wrong Machine to reconstruct an unavailable kin should:

1. award the kin unlock; and
2. award a random unit using that kin plus one of the player's currently unlocked unit types.

Example:

```text
Reconstruct Pig Kin for first time
        |
        +--> Pig Kin unlock
        +--> random Pig Kin unit from unlocked unit types
```

### Kin already unlocked

Once the kin unlock is already owned, the Wrong Machine may allow the player to choose a specific kin plus unlocked unit-type combination and award that exact unit.

This preserves the Wrong Machine as a targeted Raw Chaos sink without requiring duplicate first-ownership state.

## Codex Entries

Codex entries are unique collectible rewards.

They may be granted by combat, dialogue, encounters, bosses, or other event reward definitions.

Because Codex entries are unique, rolling an entry the player already owns resolves to nothing under the standard duplicate rule.

Codex entries should have player-facing meaning as retained knowledge, lore, discoveries, or important information rather than acting as arbitrary invisible implementation flags.

## Dialogue History

Important completed dialogue may grant a Codex entry representing the information learned during that conversation.

Dialogue selection may then use Codex ownership as a prerequisite for deciding which authored conversation should play.

Example:

```text
Player does not own "Whim: Arrival"
        |
        v
play Arrival dialogue
        |
        v
reward Codex entry "Whim: Arrival"

Player owns "Whim: Arrival"
        |
        v
select follow-up/default dialogue
```

Dialogues remain replayable. Replaying a dialogue whose Codex entry is already owned simply causes the unique Codex reward to resolve to nothing.

This removes the need for a generic `has_seen_dialogue` or story-history table for current game requirements.

Codex entries should only be used this way when the completed conversation represents meaningful information worth recording. If future branching narrative requires remembering arbitrary choices that do not map naturally to knowledge or unlocks, a dedicated narrative-state model can be introduced at that time.

## Mystic Cave Onboarding State

Mystic Cave onboarding currently has one authored Whim conversation. It grants meaningful Codex knowledge through the existing dialogue event/reward transaction. Owning that knowledge makes the player ready for Warband provisioning. The onboarding query continues to derive readiness from authored knowledge steps rather than a separate tutorial flag.

The ordinary dialogue read and completion boundaries use this same authoritative current step to admit Mystic Cave onboarding conversations. A known future or past onboarding dialogue is unavailable to a new request. Exact-key completion replay checks its finalized receipt first, so reconnecting cannot grant knowledge or advance revision again. Other dialogue families retain their existing eligibility rules.

Owning any unit instance is the durable completion marker for the current vNext unit lifecycle. Starting unit provisioning must be one atomic operation that creates the Basic Goblin roster and its required equipment/squad before this marker can become visible. Existing returning players with units therefore remain in the normal Camp/core loop without a knowledge backfill. A new player with no units and no Whim knowledge sees the opening conversation; owning the Whim knowledge with no units makes provisioning available. Unit ownership does not imply Wrong Machine access.

The Mystic Cave region has an authored identity but no ordinary run generator. Its flow references `kin.goblin` for Basic Goblin establishment, not a kin restoration recipe. The Whim assigns the Tooth Collector rescue at the Farm. The Wrong Machine is first introduced by the post-Farm Whim beat in a later package; the opening conversation grants neither Wrong Machine knowledge nor access. No tutorial flag, story-history table, or new account bootstrap path is needed.

When all authored onboarding knowledge is owned and no unit exists, one idempotent server transaction provisions the flow's authored starting Warband: two tier-one Basic Goblin Bruisers in front positions 2 and 5, and two tier-one Basic Goblin Marksmen in back positions 0 and 3. Each unit receives its configured active ability and bound die, and all four join the active squad. The Farm region unlock, unit ownership completion marker, squad activation, player revision, and finalized receipt commit together. The receipt identifies all four units and dice. A new request after completion cannot provision again; exact-key replay returns the stored result. Existing players who completed onboarding before the Farm unlock grant receive that unlock through the one-time Farm access reconciliation; ordinary run entry always requires owned region access.

## Bounties and Objectives

Bounties and objectives are intentionally separate from unlock/Codex progression because their intermediate state matters.

Example:

```text
Defeat 10 kobolds
Progress: 6 / 10
```

That `6 / 10` value must persist, so the bounty/objective system owns its own mutable progress state.

When the requirement is satisfied, the completed bounty/objective may emit an event and receive rewards through the normal event-to-reward system.

Example:

```text
objective reaches target
        |
        v
objective_completed event
        |
        v
reward resolution
```

The mutable counter is not itself an unlock or reward.

## Current Persistent Progression Categories

The current vNext game therefore needs the following progression-shaped persistent concepts:

| Need | Persistent representation |
| --- | --- |
| Region available | Unlock |
| Unit type available | Unlock |
| Kin restored | Unlock |
| Feature/facility available | Unlock |
| Lore/discovery | Codex entry |
| Important dialogue knowledge/history | Codex entry |
| Bounty progress | Bounty/objective state |
| Objective progress | Bounty/objective state |
| Current run/node/combat progress | Run state |
| Units, dice, items, currencies | Their owned-asset domain models |

## Explicitly Not Required for Initial vNext

The initial vNext model does not require:

- generic region-completion state
- first-kin-ownership flags
- generic story flags
- generic historical-event persistence
- a catch-all `player_progression` table
- generic `has_seen_dialogue` state

These concepts should not be added preemptively. A future feature should justify any new durable state by requiring information that cannot be represented naturally by existing unlocks, Codex ownership, objective progress, run state, or owned assets.

## Consequences

This decision reduces duplicated representations of the same player progression.

For example, vNext should avoid simultaneously storing concepts such as `player_has_pig_kin`, `player_first_owned_pig_kin`, `player_pig_kin_eligible`, and `player_unlocked_pig_kin` when one Pig Kin unlock can represent the durable gameplay capability the product actually uses.

It also keeps historical facts out of the database when the game only cares about the reward or capability produced by those facts.

This decision supersedes earlier vNext planning assumptions that suggested a broad generic progression/history layer or separate region-completion and first-ownership records would necessarily be required.
