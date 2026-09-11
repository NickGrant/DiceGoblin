---
Title: "Core Gameplay Loop"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-reward-unlock-model.md
  - documentation/07-development-path/vnext-energy-model.md
Category: 00-overview
Tags: [overview, gameplay-loop, vnext]
---

# Core Gameplay Loop

## Between Runs
1. Enter Camp through the authenticated Phaser game client.
2. Inspect and configure the active squad, unit ability order, and exact dice bindings.
3. Use available Camp systems such as Shop, Academy, Wrong Machine, objectives, and Codex when unlocked.
4. Choose an available region and start a run.

## Run Start
Run creation is authoritative and transactional. Energy is consumed exactly once only when the run is successfully created. Starting a run locks the player-controlled combat configuration relevant to that run until the run reaches a terminal state.

## During a Run
1. View the authoritative persisted run graph in `RunScene`.
2. Select a currently available node.
3. PHP validates and resolves the node.
4. If combat occurs, PHP resolves the battle and returns authoritative playback/result data; `BattleScene` presents it.
5. Apply finalized rewards/progression during authoritative resolution, not through a later claim transaction.
6. Return to `RunScene` with updated HP, modifiers, node state, and progression.
7. Continue until success, failure, or abandonment.

Non-combat nodes may use specialized commands only when their gameplay requires persistent intermediate authoritative state.

## Run End
A terminal run returns the player to the between-run game with persistent rewards/progression already applied. Boss rewards may directly grant the next region unlock; a generic region-complete flag is not required unless a future feature needs completion history.

## Authority Rule
Phaser provides presentation, input, navigation, local drafts, and cached authoritative state. PHP decides and persists player-affecting outcomes. Reloading or retrying must never reroll finalized gameplay or duplicate durable grants.
