# Dice Goblins — Data Model (MVP)

This document defines the canonical MySQL data model for the Dice Goblins MVP.

Guiding principles:
- The backend is authoritative; the client is a renderer/controller.
- Auth is via Discord OAuth; local `users.id` is the stable identity.
- MVP is PvE-only, but multiplayer systems (region investments, allies) are planned and stubbed.
- Prefer explicit tables over “god JSON blobs,” except for battle logs (which are inherently event-y).

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

Notes:
- `discord_id` uniqueness is required for upsert logic.
- Any future “account” fields (email) can be added later.

---

## 2) Player State

### player_state
One row per user. Stores lightweight global progression state.

Columns:
- `user_id` BIGINT UNSIGNED PK (FK → users.id)
- `currency_soft` BIGINT UNSIGNED NOT NULL DEFAULT 0
- `currency_hard` BIGINT UNSIGNED NOT NULL DEFAULT 0 (optional; can remain unused)
- `last_login_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- This avoids scattering global counters across multiple systems.

### energy_state
Real-world time gating. One row per user.

Columns:
- `user_id` BIGINT UNSIGNED PK (FK → users.id)
- `energy_current` INT NOT NULL
- `energy_max` INT NOT NULL
- `regen_rate_per_hour` DECIMAL(6,3) NOT NULL DEFAULT 1.000
- `last_regen_at` TIMESTAMP NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Regen is computed server-side on read/write using `last_regen_at`.
- Keep logic in one place; never trust client.

---

## 3) Regions, Unlocks, Runs, Maps

### regions
Static region definitions.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE (e.g., `pest_cellars`)
- `name` VARCHAR(80)
- `theme` VARCHAR(80) (e.g., `pests`, `bandits`, `elves`)
- `recommended_level` INT NOT NULL DEFAULT 1
- `energy_cost` INT NOT NULL DEFAULT 1
- `is_enabled` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Region content/loot can be iterated without schema changes.

### region_unlocks
Which regions a user has access to.

Columns:
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `region_id` BIGINT UNSIGNED (FK → regions.id)
- `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
PK:
- (`user_id`, `region_id`)

Notes:
- Simple and flexible.

### region_runs
A single “run” through a region (procedural map instance).

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `region_id` BIGINT UNSIGNED (FK → regions.id)
- `seed` BIGINT UNSIGNED NOT NULL
- `status` ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active'
- `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `completed_at` TIMESTAMP NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- `seed` allows deterministic map generation and debugging.
- A run’s map is persisted below to simplify client rendering and state.

### run_nodes
Nodes in the run’s map graph.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `run_id` BIGINT UNSIGNED (FK → region_runs.id)
- `node_index` INT NOT NULL (0..N-1, stable ordering)
- `node_type` ENUM('combat','loot','rest','static','boss') NOT NULL
- `status` ENUM('locked','available','cleared') NOT NULL DEFAULT 'locked'
- `encounter_template_id` BIGINT UNSIGNED NULL (FK → encounter_templates.id)
- `meta_json` JSON NULL (node-specific data: labels, modifiers, etc.)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`run_id`, `node_index`) UNIQUE
- (`run_id`, `status`)

Notes:
- `meta_json` is allowed here because nodes vary and we want agility.
- Boss unlocking is enforced by server run logic.

### run_edges
Directed edges connecting nodes.

Columns:
- `run_id` BIGINT UNSIGNED (FK → region_runs.id)
- `from_node_id` BIGINT UNSIGNED (FK → run_nodes.id)
- `to_node_id` BIGINT UNSIGNED (FK → run_nodes.id)

PK:
- (`run_id`, `from_node_id`, `to_node_id`)

Notes:
- Use directed edges to support more interesting maps later.
- For simple maps you can treat it as bidirectional.

---

## 4) Squads, Units, Formation

### squads
Player-defined squads.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `name` VARCHAR(64) NOT NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 0
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `is_active`)

Notes:
- Enforce at most one active squad per user in app logic (or via partial uniqueness pattern later).

### unit_types
Static unit archetypes (e.g., Stabber, Shaman, Tank).

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `role` VARCHAR(32) (e.g., `tank`, `dps`, `support`)
- `base_stats_json` JSON NOT NULL (hp, atk, def, etc.)
- `ability_set_json` JSON NOT NULL (ability identifiers + parameters)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Store base stats and ability configs as JSON for speed of iteration.
- Combat engine interprets these configs server-side.

### unit_instances
An owned unit. This is the player’s progression object.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `unit_type_id` BIGINT UNSIGNED (FK → unit_types.id)
- `tier` INT NOT NULL DEFAULT 1
- `level` INT NOT NULL DEFAULT 1
- `xp` INT NOT NULL DEFAULT 0
- `locked` TINYINT(1) NOT NULL DEFAULT 0 (prevents using as fodder)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `unit_type_id`)
- (`user_id`, `tier`, `level`)

Notes:
- Tier offset rules are implemented in code; this table stores state only.

### squad_units
Membership of unit instances in a squad.

Columns:
- `squad_id` BIGINT UNSIGNED (FK → squads.id)
- `unit_instance_id` BIGINT UNSIGNED (FK → unit_instances.id)
- `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

PK:
- (`squad_id`, `unit_instance_id`)

Notes:
- A unit can be in multiple squads if you want; if not, enforce in code.

### squad_formation
3×3 placement. Stores which unit is in which cell.

Columns:
- `squad_id` BIGINT UNSIGNED (FK → squads.id)
- `cell` VARCHAR(2) NOT NULL (e.g., A1..C3)
- `unit_instance_id` BIGINT UNSIGNED NULL (FK → unit_instances.id)
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

PK:
- (`squad_id`, `cell`)

Notes:
- `unit_instance_id` nullable allows empty slots.

---

## 5) Dice Inventory, Affixes (Ethics Slots)

### dice_definitions
Static dice “base items” (e.g., d8) and rarity rules.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `sides` INT NOT NULL (4,6,8,10,12)
- `rarity` ENUM('common','uncommon','rare','very_rare') NOT NULL
- `slot_capacity` INT NOT NULL (0..3)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- You can generate these rows at startup: 5 sides × 4 rarities (or your chosen subset).

### affix_definitions
Static affixes (“ethics”) that can appear on dice.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE (e.g., `aff_hp_pct`)
- `name` VARCHAR(80)
- `slot_cost` INT NOT NULL DEFAULT 1
- `stat` VARCHAR(32) NOT NULL (e.g., hp, attack, fire_damage)
- `op` ENUM('flat_add','pct_add') NOT NULL
- `min_value` DECIMAL(10,3) NOT NULL
- `max_value` DECIMAL(10,3) NOT NULL
- `tags_json` JSON NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Roll values within [min,max] when generating loot.

### dice_instances
Owned dice.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `dice_definition_id` BIGINT UNSIGNED (FK → dice_definitions.id)
- `display_name` VARCHAR(128) NULL (optional; supports procedural naming)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`user_id`, `dice_definition_id`)

### dice_instance_affixes
Affix rolls attached to a dice instance.

Columns:
- `dice_instance_id` BIGINT UNSIGNED (FK → dice_instances.id)
- `affix_definition_id` BIGINT UNSIGNED (FK → affix_definitions.id)
- `value` DECIMAL(10,3) NOT NULL

PK:
- (`dice_instance_id`, `affix_definition_id`)

Notes:
- Enforce slot capacity in code (sum(slot_cost) <= capacity).

### unit_dice
Dice equipped to a unit instance.

Columns:
- `unit_instance_id` BIGINT UNSIGNED (FK → unit_instances.id)
- `dice_instance_id` BIGINT UNSIGNED (FK → dice_instances.id)
- `slot_index` INT NOT NULL (0..N-1; preserves “pool order” if desired)
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

PK:
- (`unit_instance_id`, `dice_instance_id`)

Indexes:
- (`unit_instance_id`, `slot_index`) UNIQUE

Notes:
- Pool ordering rule is “largest to smallest,” but `slot_index` can support explicit ordering if needed later.

---

## 6) Encounters & Enemies

### encounter_templates
Static definitions of enemy compositions.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `region_id` BIGINT UNSIGNED NULL (FK → regions.id) (optional)
- `difficulty_rating` INT NOT NULL DEFAULT 1
- `enemy_set_json` JSON NOT NULL (enemy template ids + counts + weights)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Keep encounter composition in JSON for rapid iteration.
- This is referenced by `run_nodes.encounter_template_id`.

### enemy_templates
Static enemy archetypes.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `base_stats_json` JSON NOT NULL
- `ability_set_json` JSON NOT NULL
- `tags_json` JSON NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Notes:
- Enemy scaling is code-driven (level, region difficulty, etc.).

---

## 7) Battles & Logs (Server-authoritative combat)

### battles
A battle instance tied to a run node.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `run_id` BIGINT UNSIGNED (FK → region_runs.id)
- `node_id` BIGINT UNSIGNED (FK → run_nodes.id)
- `squad_id` BIGINT UNSIGNED (FK → squads.id)
- `rules_version` VARCHAR(32) NOT NULL DEFAULT 'combat_v1'
- `seed` BIGINT UNSIGNED NOT NULL
- `status` ENUM('completed','claimed') NOT NULL DEFAULT 'completed'
- `outcome` ENUM('victory','defeat') NOT NULL
- `ticks` INT NOT NULL
- `rounds` INT NOT NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Indexes:
- (`run_id`, `node_id`) UNIQUE  (one battle per node)
- (`user_id`, `created_at`)

Notes:
- `claimed` indicates rewards applied; enforce idempotency.
- `seed` supports deterministic replay.

### battle_logs
Stores the combat timeline.

Columns:
- `battle_id` BIGINT UNSIGNED PK (FK → battles.id)
- `log_json` JSON NOT NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

Notes:
- JSON log is acceptable for MVP; optimize later if needed.
- For large logs, consider compression or chunked rows.

---

## 8) Loot & Rewards

MVP approach: reward application occurs during `POST /battles/{id}/claim`.
You can either (A) generate loot on claim, or (B) generate at battle end and store it.
For repeatable consistency, prefer storing generated loot.

### battle_rewards
Stores generated rewards for a battle prior to claiming.

Columns:
- `battle_id` BIGINT UNSIGNED PK (FK → battles.id)
- `xp_total` INT NOT NULL DEFAULT 0
- `currency_soft` INT NOT NULL DEFAULT 0
- `rewards_json` JSON NOT NULL (dice/unit drops, region items, etc.)
- `created_at` TIMESTAMP

Notes:
- Claim endpoint reads and applies this once.
- If you choose generation at claim-time, you can omit this table and rely on deterministic seeds.

### region_items (Tier 3 promotion gate)
Static definitions of region-specific rare items.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `region_id` BIGINT UNSIGNED (FK → regions.id)
- `slug` VARCHAR(64) UNIQUE
- `name` VARCHAR(80)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

### user_region_items
Inventory of region-specific items.

Columns:
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `region_item_id` BIGINT UNSIGNED (FK → region_items.id)
- `quantity` INT NOT NULL DEFAULT 0
PK:
- (`user_id`, `region_item_id`)

---

## 9) Promotions (Tier advancement)

Promotion events are mostly derivable from unit state, but storing audit can help.

### unit_promotions
Record of a promotion event.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `result_unit_instance_id` BIGINT UNSIGNED (FK → unit_instances.id)
- `consumed_units_json` JSON NOT NULL (array of consumed unit_instance_ids)
- `consumed_region_item_id` BIGINT UNSIGNED NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

Notes:
- Optional for MVP; recommended once promotions go live.

---

## 10) Multiplayer Stubs (Future)

### region_investments (stub)
Tracks investments/bribes into regions and potential modifiers.

Columns:
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `region_id` BIGINT UNSIGNED (FK → regions.id)
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `amount` BIGINT UNSIGNED NOT NULL DEFAULT 0
- `meta_json` JSON NULL
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

### allies (stub)
Represents alliances and potential betrayal mechanics.

Columns:
- `user_id` BIGINT UNSIGNED (FK → users.id)
- `ally_user_id` BIGINT UNSIGNED (FK → users.id)
- `status` ENUM('active','broken') NOT NULL DEFAULT 'active'
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

PK:
- (`user_id`, `ally_user_id`)

Notes:
- Keep symmetric vs asymmetric alliance logic in code.

---

## MVP Cut Line (what must exist early)
1. users
2. player_state, energy_state
3. regions, region_unlocks
4. squads, unit_types, unit_instances, squad_formation
5. dice_definitions, dice_instances, dice_instance_affixes, unit_dice
6. region_runs, run_nodes, run_edges
7. encounter_templates, enemy_templates
8. battles, battle_logs (and optionally battle_rewards)

Everything else can be added later without breaking the core loop.

---
