# Dice Goblins - Data Model (MVP)

Status: active  
Last Updated: 2026-03-02  
Owner: Backend/Data  
Depends On: `backend/migrations/schema_all.sql`, `backend/src/Repositories/`

This document defines the canonical MySQL data model for the Dice Goblins MVP.

Guiding principles:
- The backend is authoritative; the client is a renderer/controller.
- Auth is via Discord OAuth; local `users.id` is the stable identity.
- MVP is PvE-only; no multiplayer systems are implemented.
- Prefer explicit tables over "god JSON blobs," except for inherently event-like data (e.g., battle logs).

Conventions:
- All tables use `utf8mb4` and InnoDB.
- Primary keys are numeric `BIGINT UNSIGNED` unless noted.
- Timestamps: `created_at`, `updated_at` whenever the table is mutable.
- Where appropriate: unique keys enforce invariants (e.g., `discord_id`).

---

## 1) Users & Identity

### users
Stores the local user identity linked to Discord.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `discord_id` VARCHAR(32) UNIQUE (snowflake)
- `display_name` VARCHAR(128)
- `avatar_url` VARCHAR(255) NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

---

## 2) Player State

### player_state
One row per user. Stores lightweight global progression state.

Columns:
- `user_id` BIGINT UNSIGNED PK (FK -> users.id)
- `currency_soft` BIGINT UNSIGNED NOT NULL DEFAULT 0
- `currency_hard` BIGINT UNSIGNED NOT NULL DEFAULT 0 (unused in MVP)
- `last_login_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

### energy_state
Real-world time gating. One row per user.

Columns:
- `user_id` BIGINT UNSIGNED PK (FK -> users.id)
- `energy_current` INT NOT NULL
- `energy_max` INT NOT NULL DEFAULT 50
- `regen_rate_per_hour` DECIMAL(6,3) NOT NULL DEFAULT 12.000  
  (1 energy per 5 minutes)
- `last_regen_at` TIMESTAMP NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Regen is computed server-side on read/write using `last_regen_at`.
- MVP pacing: max 50 energy, runs cost 5 energy (see `regions.energy_cost`).

---

## 3) Regions (Biomes), Unlocks, Runs, Maps

In MVP, each **biome is represented as a region**.

### regions
Static region definitions.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE (e.g., `mountains`, `swamps`)
- `name` VARCHAR(80)
- `theme` VARCHAR(80) (e.g., `kobolds`, `frogmen`)
- `recommended_level` INT NOT NULL DEFAULT 1
- `energy_cost` INT NOT NULL DEFAULT 5
- `is_enabled` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- MVP includes exactly two regions/biomes: Mountains (kobolds) and Swamps (frogmen).

### region_unlocks
Which regions a user has access to.

Columns:
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `region_id` BIGINT UNSIGNED (FK -> regions.id)
- `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

PK:
- (`user_id`, `region_id`)

### region_runs
A single "run" through a region (procedural map instance).

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `region_id` BIGINT UNSIGNED (FK -> regions.id)
- `team_id` BIGINT UNSIGNED (FK -> teams.id)
- `seed` BIGINT UNSIGNED NOT NULL
- `status` ENUM('active','completed','failed','abandoned') NOT NULL DEFAULT 'active'
- `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `ended_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `status`)

Notes:
- Exactly one active run is allowed per user (enforced in app logic).
- Runs remain resumable until `status` is terminal.
- Records which saved Team was selected at run start (for audit/debug). Run uses run-scoped snapshot for actual state.

### run_nodes
Nodes in the run's map graph.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `run_id` BIGINT UNSIGNED (FK -> region_runs.id)
- `node_index` INT NOT NULL (0..N-1, stable ordering)
- `node_type` ENUM('combat','loot','rest','boss') NOT NULL
- `status` ENUM('locked','available','cleared') NOT NULL DEFAULT 'locked'
- `encounter_template_id` BIGINT UNSIGNED NULL (FK -> encounter_templates.id)
- `meta_json` JSON NULL (node-specific data: labels, modifiers, etc.)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`run_id`, `node_index`) UNIQUE
- (`run_id`, `status`)

### run_edges
Directed edges connecting nodes.

Columns:
- `run_id` BIGINT UNSIGNED (FK -> region_runs.id)
- `from_node_id` BIGINT UNSIGNED (FK -> run_nodes.id)
- `to_node_id` BIGINT UNSIGNED (FK -> run_nodes.id)

PK:
- (`run_id`, `from_node_id`, `to_node_id`)

---

## 4) Teams, Units, Formation

### teams
Player-defined teams.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `name` VARCHAR(64) NOT NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 0
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `is_active`)

### unit_types
Static unit archetypes.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `role` VARCHAR(32) (e.g., `frontline`, `backline`, `support`, `control`)
- `base_stats_json` JSON NOT NULL
- `ability_set_json` JSON NOT NULL
- `max_level` INT NOT NULL
- `growth_attack_per_ability_per_level` INT NOT NULL
- `growth_defense_per_ability_per_level` INT NOT NULL
- `growth_max_hp_per_ability_per_level` INT NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- MVP ability scope: 2 active + up to 2 passives.
- `unit_instances.xp` represents progress-within-current-level (not lifetime XP).
- Units do not gain XP once `level == unit_types.max_level`.

### unit_instances
An owned unit. This is the player's persistent progression object.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `unit_type_id` BIGINT UNSIGNED (FK -> unit_types.id)
- `tier` INT NOT NULL DEFAULT 1
- `level` INT NOT NULL DEFAULT 1
- `xp` INT NOT NULL DEFAULT 0
- `locked` TINYINT(1) NOT NULL DEFAULT 0
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `unit_type_id`)
- (`user_id`, `tier`, `level`)

Notes:
- `xp` is progress within the current level.
- On level-up, `xp` is reduced by the computed level-up cost (not cumulative thresholds).
- At max level, XP does not increase.

### team_units
Membership of unit instances in a team.

Columns:
- `team_id` BIGINT UNSIGNED (FK -> teams.id)
- `unit_instance_id` BIGINT UNSIGNED (FK -> unit_instances.id)
- `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

PK:
- (`team_id`, `unit_instance_id`)

### team_formation
3Ã-3 placement. Stores which unit is in which cell.

Columns:
- `team_id` BIGINT UNSIGNED (FK -> teams.id)
- `cell` VARCHAR(2) NOT NULL (e.g., A1..C3)
- `unit_instance_id` BIGINT UNSIGNED NULL (FK -> unit_instances.id)
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

PK:
- (`team_id`, `cell`)

---

## 5) Run-Scoped Unit State (Save/Resume)

MVP requires persisting attrition across nodes (HP, status effects, rechargeable abilities). This data must be **run-scoped** and must not overwrite the persistent `unit_instances` row.

### run_unit_state
Stores a unit's mutable, within-run condition.

Columns:
- `run_id` BIGINT UNSIGNED (FK -> region_runs.id)
- `unit_instance_id` BIGINT UNSIGNED (FK -> unit_instances.id)
- `current_hp` INT NOT NULL
- `is_defeated` TINYINT(1) NOT NULL DEFAULT 0
- `cooldowns_json` JSON NOT NULL  
  (rechargeable ability cooldown counters / flags)
- `status_effects_json` JSON NOT NULL  
  (e.g., poison/bolstered/sleep with remaining duration)
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

PK:
- (`run_id`, `unit_instance_id`)

Indexes:
- (`run_id`, `is_defeated`)

Notes:
- On run start: seed `run_unit_state` for units in the selected Team snapshot (and any additional bench rules, if added later).
- Between nodes: update HP/status/cooldowns based on combat outcomes.
- On run end (success/fail/abandon): clear `run_unit_state` rows for that run.

### run_team_formation

Columns:
- run_id BIGINT UNSIGNED (FK -> region_runs.id)
- cell VARCHAR(2) NOT NULL (A1..C3)
- unit_instance_id BIGINT UNSIGNED NULL (FK -> unit_instances.id)
- updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

PK:
- (`run_id`, `cell`)

Notes:
- Seeded at run start from team_formation.
- May be updated only at Rest nodes.

---

## 6) Dice Inventory & Affixes

### dice_definitions
Static dice "base items" and rarity rules.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `sides` INT NOT NULL  
  (MVP: 4, 6, 8, 10)
- `rarity` ENUM('common','uncommon','rare') NOT NULL
- `slot_capacity` INT NOT NULL  
  (MVP: common 0, uncommon 1, rare 2)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

### affix_definitions
Static affixes that can appear on dice.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `slot_cost` INT NOT NULL DEFAULT 1
- `stat` VARCHAR(32) NOT NULL
- `op` ENUM('flat_add','pct_add','conditional') NOT NULL
- `min_value` DECIMAL(10,3) NOT NULL
- `max_value` DECIMAL(10,3) NOT NULL
- `tags_json` JSON NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- MVP affix set is closed by scope: flat/% for atk/def/hp, flat fire/ice, and two conditionals.

### dice_instances
Owned dice.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `dice_definition_id` BIGINT UNSIGNED (FK -> dice_definitions.id)
- `display_name` VARCHAR(128) NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `dice_definition_id`)

### dice_instance_affixes
Affix rolls attached to a dice instance.

Columns:
- `dice_instance_id` BIGINT UNSIGNED (FK -> dice_instances.id)
- `affix_definition_id` BIGINT UNSIGNED (FK -> affix_definitions.id)
- `value` DECIMAL(10,3) NOT NULL

PK:
- (`dice_instance_id`, `affix_definition_id`)

### unit_dice
Dice equipped to a unit instance.

Columns:
- `unit_instance_id` BIGINT UNSIGNED (FK -> unit_instances.id)
- `dice_instance_id` BIGINT UNSIGNED (FK -> dice_instances.id)
- `slot_index` INT NOT NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

PK:
- (`unit_instance_id`, `dice_instance_id`)

Indexes:
- (`unit_instance_id`, `slot_index`) UNIQUE

---

## 7) Encounters, Enemies, Loot Tables

### encounter_templates
Static definitions of enemy compositions and reward profile.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `region_id` BIGINT UNSIGNED NULL (FK -> regions.id)
- `difficulty_rating` INT NOT NULL DEFAULT 1
- `enemy_set_json` JSON NOT NULL
- `reward_profile_json` JSON NOT NULL  
  (e.g., {"tier":"t1","rolls":1} or {"tier":"t1","rolls_min":2,"rolls_max":3} or {"tier":"t2","rolls":1})
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Reward mapping is data-driven and can be tuned without code changes.

### enemy_templates
Static enemy archetypes.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `tier` INT NOT NULL DEFAULT 1
- `role` VARCHAR(32) NOT NULL  
  (frontline/backline/specialty)
- `base_stats_json` JSON NOT NULL
- `ability_set_json` JSON NOT NULL
- `xp_reward` INT NOT NULL DEFAULT 10
- `tags_json` JSON NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

### loot_tables
Defines rollable loot pools.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE  
  (e.g., `loot_t1`, `loot_t2`)
- `tier` ENUM('t1','t2') NOT NULL
- `entries_json` JSON NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- `entries_json` stores weighted category + payload definitions (dice/unit/currency).
- Tier 3 items are handled by boss logic, not table tiers.

---

## 8) Battles & Logs (Server-authoritative combat)

### battles
A battle instance tied to a run node.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `run_id` BIGINT UNSIGNED (FK -> region_runs.id)
- `node_id` BIGINT UNSIGNED (FK -> run_nodes.id)
- `team_id` BIGINT UNSIGNED (FK -> teams.id)
- `rules_version` VARCHAR(32) NOT NULL DEFAULT 'combat_v1'
- `seed` BIGINT UNSIGNED NOT NULL
- `status` ENUM('completed','claimed') NOT NULL DEFAULT 'completed'
- `outcome` ENUM('victory','defeat') NOT NULL
- `ticks` INT NOT NULL
- `rounds` INT NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`run_id`, `node_id`) UNIQUE
- (`user_id`, `created_at`)

Notes:
- Combat is resolved exactly once per node; UI replay uses stored logs.
- A round is exactly 20 ticks

### battle_logs
Stores the combat timeline.

Columns:
- `battle_id` BIGINT UNSIGNED PK (FK -> battles.id)
- `log_json` JSON NOT NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

Notes:
- log_json should make sure to store round, tick, and phase.
- events in log_json should be stored in execution order
---

## 9) Rewards, Region Items, Promotions

### battle_rewards
Stores generated rewards for a battle prior to claiming.

Columns:
- `battle_id` BIGINT UNSIGNED PK (FK -> battles.id)
- `xp_total` INT NOT NULL DEFAULT 0
- `currency_soft` INT NOT NULL DEFAULT 0
- `rewards_json` JSON NOT NULL
- `created_at` TIMESTAMP

Notes:
- Claim endpoint reads and applies this once.

### region_items
Static definitions of biome-specific Tier 3 promotion items.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `region_id` BIGINT UNSIGNED (FK -> regions.id)
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

MVP content examples:
- Mountains: `roc_egg` (Roc Egg)
- Swamps: `gator_head` (Gator Head)

### user_region_items
Inventory of region-specific items.

Columns:
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `region_item_id` BIGINT UNSIGNED (FK -> region_items.id)
- `quantity` INT NOT NULL DEFAULT 0

PK:
- (`user_id`, `region_item_id`)

### unit_promotions
Record of a promotion event.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK -> users.id)
- `result_unit_instance_id` BIGINT UNSIGNED (FK -> unit_instances.id)
- `consumed_units_json` JSON NOT NULL
- `consumed_region_item_id` BIGINT UNSIGNED NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

---

## 10) Notes on Run Resolution (Schema Implications)

Run resolution rules are implemented in code, but the schema must support:
- Attrition persistence (handled by `run_unit_state`)
- Terminal run outcomes (`region_runs.status` includes `failed` and `abandoned`)
- Idempotent battle reward claiming (`battles.status = claimed`)

On run end:
- Clear `run_unit_state` for the run
- Apply XP reset for defeated units (set `unit_instances.xp` to 0)
- Heal/recharge/cleanse is performed by clearing run-scoped state rather than mutating base unit fields


