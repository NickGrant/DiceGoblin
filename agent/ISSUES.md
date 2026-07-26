# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Chaos Encounters as Combat

### Finalize chaos reels into battle-backed encounters

**Milestone:** Chaos Encounters as Combat
**Status:** In Progress
**Priority:** High

#### Problem
Chaos nodes currently finalize into direct reward payout and node clearing. Player expectation is that accepted reels should produce the appropriate combat and then use the normal battle playback/reward claim flow.

#### Acceptance Criteria

- Finalizing a generated chaos result creates or returns one persisted battle for that run node.
- The battle log meta records the chaos reel summary and selected symbols.
- Existing finalized chaos results remain idempotent and do not duplicate rewards or battles.
- Backend integration coverage proves chaos finalization returns a battle payload with playback events.

### Transition chaos frontend into combat playback

**Milestone:** Chaos Encounters as Combat
**Status:** Open
**Priority:** High

#### Problem
The run-node UI treats confirmed chaos results as complete and returns to the map. It should instead show the battle result after finalization so the player can watch combat play out and claim rewards.

#### Acceptance Criteria

- The finalize button copy reflects starting/locking the encounter, not direct payout.
- A successful chaos finalize response sets the node result battle payload.
- Confirmed chaos nodes with an existing battle reopen into the battle playback surface.
- Frontend tests cover the chaos-to-playback transition.

### Expand chaos reel combat authoring

**Milestone:** Chaos Encounters as Combat
**Status:** Open
**Priority:** Medium

#### Problem
The first battle-backed version can map reels onto existing encounter templates, but the long-term design needs authored enemy-family, encounter-shape, and rule/reward catalogs that can produce more exact combat setups.

#### Acceptance Criteria

- Document the authoring contract for chaos enemy family, encounter shape, and rule/reward effects.
- Add backlog-ready work for richer combat modifiers such as bolstered enemies, ambush opening state, guaranteed loot, and Raw Chaos reward hooks.
- Keep the current implementation deterministic and backend-authoritative while leaving room for catalog growth.

## Progression Rewards and Unlock Clarity

### Correct story-gated feature unlock timing

**Milestone:** Progression Rewards and Unlock Clarity
**Status:** Open
**Priority:** High

#### Problem
Some feature unlocks are appearing at the wrong moment. Wrong Machine appears to unlock when Swamps starts instead of after the intended clear, and Tooth Merchant should unlock from beating Mudking for the first time rather than from the following conversation.

#### Acceptance Criteria

- Wrong Machine unlocks only after the intended first Swamps completion or boss-clear gate.
- Tooth Merchant unlocks immediately when Mudking is beaten for the first time.
- Unlock checks are backend-authoritative and idempotent across replayed requests.
- Automated coverage proves the features are unavailable before their gates and available immediately after.

### Surface unlocks, stolen pages, and complete reward totals

**Milestone:** Progression Rewards and Unlock Clarity
**Status:** Open
**Priority:** High

#### Problem
Rewards should make progression feel visible. Newly unlocked systems, first-clear codex additions, and teeth gains are either absent or too implicit in the reward flow.

#### Acceptance Criteria

- Reward screens show game unlocks as they become available, including Wrong Machine-style unlocks.
- First-clear codex additions are represented as stolen pages and surfaced in rewards.
- Reward summary totals include teeth gained alongside units and dice.
- The rewards screen displays special item drops when earned.

### Fix lineage item drops and reward presentation

**Milestone:** Progression Rewards and Unlock Clarity
**Status:** Open
**Priority:** High

#### Problem
Pig Ear and Mudking Crown Fragment do not appear to be dropping reliably, and earned special items need to appear in the rewards screen.

#### Acceptance Criteria

- Pig Ear and Mudking Crown Fragment have verified drop conditions.
- Earned special items persist to the player inventory or profile as intended.
- Reward claim responses include earned special items.
- Frontend reward presentation includes those items when present.

## Core UX Cleanup

### Refresh high-friction layouts and run presentation

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** High

#### Problem
Several core screens feel overly padded, visually stale, or structurally hard to use. Run screens have too much vertical padding, the run icon set needs a refresh, the guide page layout needs a full rework, and the landing/login page should become a compact marketing landing page with login.

#### Acceptance Criteria

- Run screens use tighter vertical spacing without crowding combat or node controls.
- Run icons are refreshed as a cohesive set.
- Guide page layout is rebuilt around scannable player tasks and reference sections.
- Landing/login page presents a small marketing surface plus clear login entry.

### Repair flickering and missing visual assets

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** Medium

#### Problem
The dropdown menu graphic flickers when opened, likely because it is not preloaded, and kobold dialogue images are not loading.

#### Acceptance Criteria

- Dropdown menu assets are preloaded or otherwise available before first open.
- Kobold dialogue image paths resolve correctly in the built app.
- Visual asset regressions are covered with a focused smoke test or documented manual check.

### Rework Academy and Shrine copy density

**Milestone:** Core UX Cleanup
**Status:** Open
**Priority:** Medium

#### Problem
Academy should feel closer to the shop UI, unit types should show their tier, and shrine currently repeats too much messaging. Vague phrases like "available now" and "requirements met" do not tell players what they need to know.

#### Acceptance Criteria

- Academy list presentation is visually aligned with shop patterns.
- Academy unit entries show role/type and tier in player-facing language.
- Shrine screen removes duplicate messaging while preserving required state and consequence text.
- Generic "available now" and "requirements met" labels are replaced with specific unlock/status information.

## Inventory Scale and Actions

### Add pagination to inventory collections

**Milestone:** Inventory Scale and Actions
**Status:** Open
**Priority:** High

#### Problem
Inventories for dice, units, and related collections will become unwieldy as player collections grow.

#### Acceptance Criteria

- Dice inventory supports pagination or an equivalent chunking control.
- Unit inventory supports pagination or an equivalent chunking control.
- Empty, filtered, and final-page states are clear and stable.
- Existing sort/filter behavior continues to work with pagination.

### Complete unlocked dice action affordances

**Milestone:** Inventory Scale and Actions
**Status:** Open
**Priority:** Medium

#### Problem
Dice inventory should avoid duplicate clutter and expose salvage only when the player has unlocked the Wrong Machine.

#### Acceptance Criteria

- Dice inspect modal includes salvage only after Wrong Machine unlock.
- Players cannot earn or salvage Raw Chaos until Wrong Machine is unlocked.
- Raw Chaos tracker appears in the controls area after Wrong Machine unlock.
- Rarity and "Raw Chaos ready" badges are removed from dice inventory tiles.
- Duplicate `.dg-proto-chip` information is removed where it does not add unique value.

## Node Quality Art Expansion

### Implement loot and shrine quality tiers

**Milestone:** Node Quality Art Expansion
**Status:** Open
**Priority:** Medium

#### Problem
Loot and shrine nodes should use the generated quality-tier assets, with optional A/B variants, so the map better communicates node value.

#### Acceptance Criteria

- Loot nodes select quality tiers that match the relevant generated assets.
- Shrine nodes select quality tiers that match the relevant generated assets.
- A/B variants are chosen by a stable rule, such as deterministic node id parity, or by documented backend randomness.
- Node visuals remain consistent between map display, node details, and reward expectations.
