-- AUTO-GENERATED FILE. DO NOT EDIT.
-- Source: C:\xampp\htdocs\dice-goblin\backend\migrations

-- BEGIN MIGRATION: 00_setup.sql
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS=0;
-- END MIGRATION: 00_setup.sql

-- BEGIN MIGRATION: 01_users.sql
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `discord_id` VARCHAR(32) NOT NULL,
  `display_name` VARCHAR(128) NOT NULL,
  `avatar_url` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_discord_id` (`discord_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 01_users.sql

-- BEGIN MIGRATION: 02_regions.sql
CREATE TABLE `regions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `theme` VARCHAR(80) NOT NULL,
  `recommended_level` INT NOT NULL DEFAULT 1,
  `energy_cost` INT NOT NULL DEFAULT 5,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 02_regions.sql

-- BEGIN MIGRATION: 03_unit_types.sql
CREATE TABLE `unit_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  `base_stats_json` JSON NOT NULL,
  `ability_set_json` JSON NOT NULL,
  `max_level` INT NOT NULL,
  `attack_per_level` INT NOT NULL,
  `defense_per_level` INT NOT NULL,
  `max_hp_per_level` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_types_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 03_unit_types.sql

-- BEGIN MIGRATION: 04_dice_definitions.sql
CREATE TABLE `dice_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sides` INT NOT NULL,
  `rarity` ENUM('common','uncommon','rare') NOT NULL,
  `slot_capacity` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 04_dice_definitions.sql

-- BEGIN MIGRATION: 05_affix_definitions.sql
CREATE TABLE `affix_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `slot_cost` INT NOT NULL DEFAULT 1,
  `stat` VARCHAR(32) NOT NULL,
  `op` ENUM('flat_add','pct_add','conditional') NOT NULL,
  `min_value` DECIMAL(10,3) NOT NULL,
  `max_value` DECIMAL(10,3) NOT NULL,
  `tags_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_affix_definitions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 05_affix_definitions.sql

-- BEGIN MIGRATION: 06_enemy_templates.sql
CREATE TABLE `enemy_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `tier` INT NOT NULL DEFAULT 1,
  `role` VARCHAR(32) NOT NULL,
  `base_stats_json` JSON NOT NULL,
  `ability_set_json` JSON NOT NULL,
  `xp_reward` INT NOT NULL DEFAULT 10,
  `tags_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enemy_templates_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 06_enemy_templates.sql

-- BEGIN MIGRATION: 07_loot_tables.sql
CREATE TABLE `loot_tables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `tier` ENUM('t1','t2') NOT NULL,
  `entries_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loot_tables_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 07_loot_tables.sql

-- BEGIN MIGRATION: 08_player_state.sql
CREATE TABLE `player_state` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `currency_soft` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `currency_hard` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `last_login_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_player_state_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 08_player_state.sql

-- BEGIN MIGRATION: 09_energy_state.sql
CREATE TABLE `energy_state` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `energy_current` INT NOT NULL,
  `energy_max` INT NOT NULL DEFAULT 50,
  `regen_rate_per_hour` DECIMAL(6,3) NOT NULL DEFAULT 12.000,
  `last_regen_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_energy_state_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 09_energy_state.sql

-- BEGIN MIGRATION: 10_teams.sql
CREATE TABLE `teams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_teams_user_id_is_active` (`user_id`, `is_active`),
  CONSTRAINT `fk_teams_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 10_teams.sql

-- BEGIN MIGRATION: 11_region_unlocks.sql
CREATE TABLE `region_unlocks` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `region_id`),
  CONSTRAINT `fk_region_unlocks_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_region_unlocks_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 11_region_unlocks.sql

-- BEGIN MIGRATION: 12_region_runs.sql
CREATE TABLE `region_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `seed` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('active','completed','failed','abandoned') NOT NULL DEFAULT 'active',
  `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_region_runs_user_id_status` (`user_id`, `status`),
  CONSTRAINT `fk_region_runs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_region_runs_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 12_region_runs.sql

-- BEGIN MIGRATION: 13_encounter_templates.sql
CREATE TABLE `encounter_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `region_id` BIGINT UNSIGNED NULL,
  `difficulty_rating` INT NOT NULL DEFAULT 1,
  `enemy_set_json` JSON NOT NULL,
  `reward_profile_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_encounter_templates_slug` (`slug`),
  CONSTRAINT `fk_encounter_templates_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 13_encounter_templates.sql

-- BEGIN MIGRATION: 14_region_items.sql
CREATE TABLE `region_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_region_items_slug` (`slug`),
  CONSTRAINT `fk_region_items_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 14_region_items.sql

-- BEGIN MIGRATION: 15_unit_instances.sql
CREATE TABLE `unit_instances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `unit_type_id` BIGINT UNSIGNED NOT NULL,
  `tier` INT NOT NULL DEFAULT 1,
  `level` INT NOT NULL DEFAULT 1,
  `xp` INT NOT NULL DEFAULT 0,
  `locked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_unit_instances_user_id_unit_type_id` (`user_id`, `unit_type_id`),
  KEY `ix_unit_instances_user_id_tier_level` (`user_id`, `tier`, `level`),
  CONSTRAINT `fk_unit_instances_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_instances_unit_type_id` FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 15_unit_instances.sql

-- BEGIN MIGRATION: 16_dice_instances.sql
CREATE TABLE `dice_instances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `dice_definition_id` BIGINT UNSIGNED NOT NULL,
  `display_name` VARCHAR(128) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dice_instances_user_id_dice_definition_id` (`user_id`, `dice_definition_id`),
  CONSTRAINT `fk_dice_instances_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dice_instances_dice_definition_id` FOREIGN KEY (`dice_definition_id`) REFERENCES `dice_definitions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 16_dice_instances.sql

-- BEGIN MIGRATION: 17_run_nodes.sql
CREATE TABLE `run_nodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `node_index` INT NOT NULL,
  `node_type` ENUM('combat','loot','rest','boss') NOT NULL,
  `status` ENUM('locked','available','cleared') NOT NULL DEFAULT 'locked',
  `encounter_template_id` BIGINT UNSIGNED NULL,
  `meta_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_nodes_run_id_node_index` (`run_id`, `node_index`),
  KEY `ix_run_nodes_run_id_status` (`run_id`, `status`),
  CONSTRAINT `fk_run_nodes_run_id` FOREIGN KEY (`run_id`) REFERENCES `region_runs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_nodes_encounter_template_id` FOREIGN KEY (`encounter_template_id`) REFERENCES `encounter_templates`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 17_run_nodes.sql

-- BEGIN MIGRATION: 18_user_region_items.sql
CREATE TABLE `user_region_items` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`, `region_item_id`),
  CONSTRAINT `fk_user_region_items_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_region_items_region_item_id` FOREIGN KEY (`region_item_id`) REFERENCES `region_items`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 18_user_region_items.sql

-- BEGIN MIGRATION: 19_team_units.sql
CREATE TABLE `team_units` (
  `team_id` BIGINT UNSIGNED NOT NULL,
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`, `unit_instance_id`),
  CONSTRAINT `fk_team_units_team_id` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_team_units_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 19_team_units.sql

-- BEGIN MIGRATION: 20_team_formation.sql
CREATE TABLE `team_formation` (
  `team_id` BIGINT UNSIGNED NOT NULL,
  `cell` VARCHAR(2) NOT NULL,
  `unit_instance_id` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`, `cell`),
  CONSTRAINT `fk_team_formation_team_id` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_team_formation_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 20_team_formation.sql

-- BEGIN MIGRATION: 21_run_unit_state.sql
CREATE TABLE `run_unit_state` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `current_hp` INT NOT NULL,
  `is_defeated` TINYINT(1) NOT NULL DEFAULT 0,
  `cooldowns_json` JSON NOT NULL,
  `status_effects_json` JSON NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`run_id`, `unit_instance_id`),
  KEY `ix_run_unit_state_run_id_is_defeated` (`run_id`, `is_defeated`),
  CONSTRAINT `fk_run_unit_state_run_id` FOREIGN KEY (`run_id`) REFERENCES `region_runs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_unit_state_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 21_run_unit_state.sql

-- BEGIN MIGRATION: 22_unit_promotions.sql
CREATE TABLE `unit_promotions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `result_unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `consumed_units_json` JSON NOT NULL,
  `consumed_region_item_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_unit_promotions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_promotions_result_unit_instance_id` FOREIGN KEY (`result_unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 22_unit_promotions.sql

-- BEGIN MIGRATION: 23_dice_instance_affixes.sql
CREATE TABLE `dice_instance_affixes` (
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `affix_definition_id` BIGINT UNSIGNED NOT NULL,
  `value` DECIMAL(10,3) NOT NULL,
  PRIMARY KEY (`dice_instance_id`, `affix_definition_id`),
  CONSTRAINT `fk_dice_instance_affixes_dice_instance_id` FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dice_instance_affixes_affix_definition_id` FOREIGN KEY (`affix_definition_id`) REFERENCES `affix_definitions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 23_dice_instance_affixes.sql

-- BEGIN MIGRATION: 24_unit_dice.sql
CREATE TABLE `unit_dice` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `slot_index` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `dice_instance_id`),
  UNIQUE KEY `uq_unit_dice_unit_instance_id_slot_index` (`unit_instance_id`, `slot_index`),
  CONSTRAINT `fk_unit_dice_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_dice_dice_instance_id` FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 24_unit_dice.sql

-- BEGIN MIGRATION: 25_run_edges.sql
CREATE TABLE `run_edges` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `from_node_id` BIGINT UNSIGNED NOT NULL,
  `to_node_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`run_id`, `from_node_id`, `to_node_id`),
  CONSTRAINT `fk_run_edges_run_id` FOREIGN KEY (`run_id`) REFERENCES `region_runs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_edges_from_node_id` FOREIGN KEY (`from_node_id`) REFERENCES `run_nodes`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_edges_to_node_id` FOREIGN KEY (`to_node_id`) REFERENCES `run_nodes`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 25_run_edges.sql

-- BEGIN MIGRATION: 26_battles.sql
CREATE TABLE `battles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `node_id` BIGINT UNSIGNED NOT NULL,
  `team_id` BIGINT UNSIGNED NOT NULL,
  `rules_version` VARCHAR(32) NOT NULL DEFAULT 'combat_v1',
  `seed` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('completed','claimed') NOT NULL DEFAULT 'completed',
  `outcome` ENUM('victory','defeat') NOT NULL,
  `ticks` INT NOT NULL,
  `rounds` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_battles_run_id_node_id` (`run_id`, `node_id`),
  KEY `ix_battles_user_id_created_at` (`user_id`, `created_at`),
  CONSTRAINT `fk_battles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_battles_run_id` FOREIGN KEY (`run_id`) REFERENCES `region_runs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_battles_node_id` FOREIGN KEY (`node_id`) REFERENCES `run_nodes`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_battles_team_id` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 26_battles.sql

-- BEGIN MIGRATION: 27_battle_logs.sql
CREATE TABLE `battle_logs` (
  `battle_id` BIGINT UNSIGNED NOT NULL,
  `log_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`battle_id`),
  CONSTRAINT `fk_battle_logs_battle_id` FOREIGN KEY (`battle_id`) REFERENCES `battles`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 27_battle_logs.sql

-- BEGIN MIGRATION: 28_battle_rewards.sql
CREATE TABLE `battle_rewards` (
  `battle_id` BIGINT UNSIGNED NOT NULL,
  `xp_total` INT NOT NULL DEFAULT 0,
  `currency_soft` INT NOT NULL DEFAULT 0,
  `rewards_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`battle_id`),
  CONSTRAINT `fk_battle_rewards_battle_id` FOREIGN KEY (`battle_id`) REFERENCES `battles`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 28_battle_rewards.sql

-- BEGIN MIGRATION: 29_user_grants.sql
CREATE TABLE `user_grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `grant_slug` VARCHAR(64) NOT NULL,
  `meta_json` JSON NULL,
  `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_grants_user_id_grant_slug` (`user_id`, `grant_slug`),
  KEY `ix_user_grants_user_id` (`user_id`),
  CONSTRAINT `fk_user_grants_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END MIGRATION: 29_user_grants.sql

-- BEGIN MIGRATION: 30_seed_unit_types.sql
INSERT INTO `unit_types` (
  `slug`,
  `name`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `max_level`,
  `attack_per_level`,
  `defense_per_level`,
  `max_hp_per_level`
)
VALUES
  (
    'frontline_bruiser_t1',
    'Bruiser',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 3, 'max_hp', 22),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    6,
    1, 1, 2
  ),
  (
    'frontline_bruiser_t2',
    'Enforcer',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 7, 'defense', 5, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    1, 1, 3
  ),
  (
    'frontline_bruiser_t3',
    'Juggernaut',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 9, 'defense', 7, 'max_hp', 40),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    14,
    1, 2, 4
  ),

  (
    'frontline_guardian_t1',
    'Guardian',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 5, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    6,
    1, 2, 2
  ),
  (
    'frontline_guardian_t2',
    'Bulwark',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 7, 'max_hp', 32),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    1, 2, 3
  ),
  (
    'frontline_guardian_t3',
    'Ironwall',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 10, 'max_hp', 44),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    14,
    1, 3, 3
  ),

  (
    'backline_marksman_t1',
    'Marksman',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 18),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    6,
    2, 1, 2
  ),
  (
    'backline_marksman_t2',
    'Deadeye',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 3, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    10,
    2, 1, 2
  ),
  (
    'backline_marksman_t3',
    'Sharpshot',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 32),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    14,
    3, 1, 2
  ),

  (
    'support_banner_t1',
    'Bannerbearer',
    'support',
    JSON_OBJECT('version', 1, 'attack', 2, 'defense', 4, 'max_hp', 20),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    8,
    1, 2, 2
  ),
  (
    'support_banner_t2',
    'Warcaller',
    'support',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    12,
    1, 2, 3
  ),

  (
    'control_saboteur_t1',
    'Saboteur',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 3, 'max_hp', 18),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart'),
      'passives', JSON_ARRAY()
    ),
    8,
    2, 1, 2
  ),
  (
    'control_saboteur_t2',
    'Trickshot',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 4, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart'),
      'passives', JSON_ARRAY()
    ),
    12,
    2, 1, 3
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `max_level` = VALUES(`max_level`),
  `attack_per_level` = VALUES(`attack_per_level`),
  `defense_per_level` = VALUES(`defense_per_level`),
  `max_hp_per_level` = VALUES(`max_hp_per_level`);
-- END MIGRATION: 30_seed_unit_types.sql

-- BEGIN MIGRATION: 99_finalize.sql
SET FOREIGN_KEY_CHECKS=1;
-- END MIGRATION: 99_finalize.sql
