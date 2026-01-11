-- Dice Goblins — MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Optional: create and select DB
-- CREATE DATABASE IF NOT EXISTS `dice_goblins` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `dice_goblins`;

SET FOREIGN_KEY_CHECKS=0;

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

CREATE TABLE `unit_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  `base_stats_json` JSON NOT NULL,
  `ability_set_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_types_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dice_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sides` INT NOT NULL,
  `rarity` ENUM('common','uncommon','rare') NOT NULL,
  `slot_capacity` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `region_unlocks` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `region_id`),
  CONSTRAINT `fk_region_unlocks_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_region_unlocks_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `user_region_items` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`, `region_item_id`),
  CONSTRAINT `fk_user_region_items_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_region_items_region_item_id` FOREIGN KEY (`region_item_id`) REFERENCES `region_items`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_units` (
  `team_id` BIGINT UNSIGNED NOT NULL,
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`, `unit_instance_id`),
  CONSTRAINT `fk_team_units_team_id` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_team_units_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_formation` (
  `team_id` BIGINT UNSIGNED NOT NULL,
  `cell` VARCHAR(2) NOT NULL,
  `unit_instance_id` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`, `cell`),
  CONSTRAINT `fk_team_formation_team_id` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_team_formation_unit_instance_id` FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `dice_instance_affixes` (
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `affix_definition_id` BIGINT UNSIGNED NOT NULL,
  `value` DECIMAL(10,3) NOT NULL,
  PRIMARY KEY (`dice_instance_id`, `affix_definition_id`),
  CONSTRAINT `fk_dice_instance_affixes_dice_instance_id` FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dice_instance_affixes_affix_definition_id` FOREIGN KEY (`affix_definition_id`) REFERENCES `affix_definitions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `run_edges` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `from_node_id` BIGINT UNSIGNED NOT NULL,
  `to_node_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`run_id`, `from_node_id`, `to_node_id`),
  CONSTRAINT `fk_run_edges_run_id` FOREIGN KEY (`run_id`) REFERENCES `region_runs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_edges_from_node_id` FOREIGN KEY (`from_node_id`) REFERENCES `run_nodes`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_edges_to_node_id` FOREIGN KEY (`to_node_id`) REFERENCES `run_nodes`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `battle_logs` (
  `battle_id` BIGINT UNSIGNED NOT NULL,
  `log_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`battle_id`),
  CONSTRAINT `fk_battle_logs_battle_id` FOREIGN KEY (`battle_id`) REFERENCES `battles`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `battle_rewards` (
  `battle_id` BIGINT UNSIGNED NOT NULL,
  `xp_total` INT NOT NULL DEFAULT 0,
  `currency_soft` INT NOT NULL DEFAULT 0,
  `rewards_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`battle_id`),
  CONSTRAINT `fk_battle_rewards_battle_id` FOREIGN KEY (`battle_id`) REFERENCES `battles`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
