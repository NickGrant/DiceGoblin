-- AUTO-GENERATED FILE. DO NOT EDIT.
-- Source: C:\xampp\htdocs\dice-goblin\backend\migrations

-- BEGIN MIGRATION: 00_setup.sql
-- Dice Goblins â€” MySQL Schema (MVP)
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

-- Safety for repeated runs (comment out if you do not want drops)
SET FOREIGN_KEY_CHECKS=0;

-- END MIGRATION: 00_setup.sql

-- BEGIN MIGRATION: 01_users.sql
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


CREATE TABLE `battle_logs` (
  `battle_id` BIGINT UNSIGNED NOT NULL,
  `log_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`battle_id`),
  CONSTRAINT `fk_battle_logs_battle_id` FOREIGN KEY (`battle_id`) REFERENCES `battles`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 27_battle_logs.sql

-- BEGIN MIGRATION: 28_battle_rewards.sql
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)


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

-- BEGIN MIGRATION: 31_seed_enemy_templates.sql
-- Seed initial enemy templates (Milestone 1)
-- Factions: kobolds, frogmen
-- Uses existing AbilityRegistry ability IDs only.

INSERT INTO `enemy_templates` (
  `slug`,
  `name`,
  `tier`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `xp_reward`,
  `tags_json`
)
VALUES
  (
    'kobold_skirmisher',
    'Kobold Skirmisher',
    1,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 16),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    10,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'grunt', 'damage_profile', 'ranged')
  ),
  (
    'kobold_shieldbearer',
    'Kobold Shieldbearer',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'kobold_sharpshooter',
    'Kobold Sharpshooter',
    2,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 9, 'defense', 3, 'max_hp', 22),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    15,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'elite', 'damage_profile', 'ranged')
  ),
  (
    'kobold_warchief',
    'Kobold Warchief',
    3,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 40),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    30,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'boss', 'damage_profile', 'ranged')
  ),

  (
    'frogman_bruiser',
    'Frogman Bruiser',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 5, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'frogman_spearhunter',
    'Frogman Spearhunter',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 4, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY()
    ),
    10,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'frogman_wardrummer',
    'Frogman Wardrummer',
    2,
    'support',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 5, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    15,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'elite', 'utility', 'buff')
  ),
  (
    'frogman_bog_tyrant',
    'Bog Tyrant',
    3,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 7, 'max_hp', 50),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    30,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'boss', 'damage_profile', 'melee', 'theme', 'attrition')
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tier` = VALUES(`tier`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `xp_reward` = VALUES(`xp_reward`),
  `tags_json` = VALUES(`tags_json`);

-- END MIGRATION: 31_seed_enemy_templates.sql

-- BEGIN MIGRATION: 32_seed_region_items.sql
-- Seed region items (Milestone 1)
-- Ensures required regions exist: mountains, swamps
-- Region items:
-- - mountains: roc_egg
-- - swamps: gator_head

-- 1) Ensure regions exist (FK dependency for region_items.region_id)
INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('mountains', 'Mountains', 'mountain', 1, 5, 1),
  ('swamps',    'Swamps',    'swamp',    1, 5, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);

-- 2) Seed region items (boss-drop items)
INSERT INTO `region_items` (
  `region_id`,
  `slug`,
  `name`
)
VALUES
  (
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    'roc_egg',
    'Roc Egg'
  ),
  (
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    'gator_head',
    'Gator Head'
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `name` = VALUES(`name`);

-- END MIGRATION: 32_seed_region_items.sql

-- BEGIN MIGRATION: 33_seed_loot_tables.sql
-- Seed initial loot tables (Milestone 1)
-- Table shape: loot_tables(slug, tier ENUM('t1','t2'), entries_json JSON)
-- Locked loot schema stored in entries_json:
--   version, description, drops.currency.soft(min/max), drops.dice(chance/rolls/choices[]),
--   drops.units(chance/pool[]), drops.region_items[] (boss only)

INSERT INTO `loot_tables` (
  `slug`,
  `tier`,
  `entries_json`
)
VALUES
  (
    'kobold_basic_loot',
    't1',
    JSON_OBJECT(
      'version', 1,
      'description', 'Standard rewards for kobold combat encounters. Dice and unit drops are optional; no region items.',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 8, 'max', 12)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.4,
          'rolls', 1,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 4, 'weight', 40),
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 30),
            JSON_OBJECT('material', 'wood',      'sides', 4, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.1,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'backline_marksman_t1', 'weight', 70),
            JSON_OBJECT('unit_type_slug', 'support_banner_t1',    'weight', 25),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',  'weight', 5)
          )
        )
      )
    )
  ),
  (
    'kobold_boss_loot',
    't2',
    JSON_OBJECT(
      'version', 1,
      'description', 'Boss rewards for kobold encounters. Includes mountain region item (Roc Egg).',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 25, 'max', 35)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.85,
          'rolls', 2,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 8, 'weight', 25),
            JSON_OBJECT('material', 'bone',      'sides', 6, 'weight', 10),
            JSON_OBJECT('material', 'bone',      'sides', 8, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.25,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'backline_marksman_t1', 'weight', 60),
            JSON_OBJECT('unit_type_slug', 'support_banner_t1',    'weight', 30),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',  'weight', 10)
          )
        ),
        'region_items', JSON_ARRAY(
          JSON_OBJECT('slug', 'roc_egg', 'chance', 0.4)
        )
      )
    )
  ),
  (
    'frogman_basic_loot',
    't1',
    JSON_OBJECT(
      'version', 1,
      'description', 'Standard rewards for frogman combat encounters. Dice and unit drops are optional; no region items.',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 8, 'max', 12)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.4,
          'rolls', 1,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 4, 'weight', 35),
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 4, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.1,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'frontline_bruiser_t1',  'weight', 70),
            JSON_OBJECT('unit_type_slug', 'frontline_guardian_t1', 'weight', 25),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',   'weight', 5)
          )
        )
      )
    )
  ),
  (
    'frogman_boss_loot',
    't2',
    JSON_OBJECT(
      'version', 1,
      'description', 'Boss rewards for frogman encounters. Includes swamp region item (Gator Head).',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 25, 'max', 35)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.85,
          'rolls', 2,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 15),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 8, 'weight', 25),
            JSON_OBJECT('material', 'bone',      'sides', 6, 'weight', 15),
            JSON_OBJECT('material', 'bone',      'sides', 8, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.25,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'frontline_bruiser_t1',  'weight', 60),
            JSON_OBJECT('unit_type_slug', 'frontline_guardian_t1', 'weight', 30),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',   'weight', 10)
          )
        ),
        'region_items', JSON_ARRAY(
          JSON_OBJECT('slug', 'gator_head', 'chance', 0.4)
        )
      )
    )
  )
ON DUPLICATE KEY UPDATE
  `tier` = VALUES(`tier`),
  `entries_json` = VALUES(`entries_json`);

-- END MIGRATION: 33_seed_loot_tables.sql

-- BEGIN MIGRATION: 34_seed_encounter_templates.sql
-- Seed encounter templates (Milestone 1)
-- Position-aware enemy layout schema:
-- enemy_set_json.version = 2
-- enemy_set_json.teams[] = { team_id, label, units[] }
-- units[] = { enemy_template_slug, pos: { x, y } }
--
-- Regions assumed to exist with slugs: 'mountains', 'swamps'

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  -- =========================
  -- MOUNTAINS (Kobolds)
  -- =========================

  (
    'mountains_kobold_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_combat_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_combat_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    3,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    5,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Command',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_warchief',     'pos', JSON_OBJECT('x', 2, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_boss_loot', 'rolls', 1)
  ),

  -- =========================
  -- SWAMPS (Frogmen)
  -- =========================

  (
    'swamps_frogman_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_combat_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_combat_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    3,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    5,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Bog Court',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bog_tyrant', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_boss_loot', 'rolls', 1)
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);

-- END MIGRATION: 34_seed_encounter_templates.sql

-- BEGIN MIGRATION: 35_encounter_noncombat_and_descriptions.sql
-- Add encounter description field + seed non-combat encounters (Milestone 1)
-- Compatible with MySQL versions that do NOT support: ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...

-- 1) Conditionally add player-facing description text (idempotent)
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'encounter_templates'
    AND COLUMN_NAME = 'description'
);

SET @ddl := IF(
  @col_exists = 0,
  'ALTER TABLE `encounter_templates` ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '''' AFTER `difficulty_rating`;',
  'SELECT 1;'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Backfill descriptions for existing encounters (from 34_seed_encounter_templates.sql)
UPDATE `encounter_templates`
SET `description` = CASE
  WHEN `slug` LIKE 'mountains\\_kobold\\_boss\\_%' THEN
    'A warhorn screams through the crags. The kobold command has taken the field.'
  WHEN `slug` LIKE 'mountains\\_kobold\\_combat\\_%' THEN
    'Loose stones shift underfoot as a kobold warband scrambles into position.'
  WHEN `slug` LIKE 'swamps\\_frogman\\_boss\\_%' THEN
    'The swamp goes still. Something immense rises from the black water.'
  WHEN `slug` LIKE 'swamps\\_frogman\\_combat\\_%' THEN
    'Wet reeds part and frogmen emergeâ€”quiet, patient, and hard to kill.'
  ELSE `description`
END
WHERE `description` = '';

-- 3) Seed non-combat encounters: 3 loot + 2 rest per biome
-- Uses enemy_set_json v2 with empty teams.

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `description`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  -- MOUNTAINS (Kobolds) â€” LOOT x3
  (
    'mountains_kobold_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    'Before you lies a pile of bones and scraps. Underneath, something glintsâ€”salvage worth keeping.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_loot_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    'A collapsed supply crate is wedged between rocks. Most of it is ruined, but not all.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_loot_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    3,
    'You find a scorched campsite and a half-buried satchel. Whatever happened here, it ended fast.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),

  -- MOUNTAINS (Kobolds) â€” REST x2
  (
    'mountains_kobold_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    'A sheltered ledge offers a moment to breathe. You patch gear and steady your hands.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),
  (
    'mountains_kobold_rest_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    'Warm air rises from a crack in the stone. It is not safe, but it is quietâ€”for now.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),

  -- SWAMPS (Frogmen) â€” LOOT x3
  (
    'swamps_frogman_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    'A waterlogged bundle hangs from a dead branch. Inside: salvage, wrapped tight against the muck.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_loot_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    'You pry open a half-sunk chest. The hinges scream, but the contents are still usable.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_loot_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    3,
    'Something is tangled in the reedsâ€”gear left behind in a hurry. You take what you can.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),

  -- SWAMPS (Frogmen) â€” REST x2
  (
    'swamps_frogman_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    'You find a dry patch of ground and hold still long enough to recover. The swamp watches.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),
  (
    'swamps_frogman_rest_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    'A ring of standing stones breaks the wind and the insects. You rest, but do not sleep.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `description` = VALUES(`description`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);

-- END MIGRATION: 35_encounter_noncombat_and_descriptions.sql

-- BEGIN MIGRATION: 36_add_unique_dice_definitions_sides_rarity.sql
ALTER TABLE `dice_definitions`
  ADD UNIQUE KEY `uq_dice_definitions_sides_rarity` (`sides`, `rarity`);

-- END MIGRATION: 36_add_unique_dice_definitions_sides_rarity.sql

-- BEGIN MIGRATION: 37_seed_dice_definitions.sql
INSERT INTO `dice_definitions` (
  `sides`,
  `rarity`,
  `slot_capacity`
)
VALUES
  (4,  'common',   0),
  (6,  'common',   0),
  (8,  'common',   0),
  (10, 'common',   0),

  (4,  'uncommon', 1),
  (6,  'uncommon', 1),
  (8,  'uncommon', 1),
  (10, 'uncommon', 1),

  (4,  'rare',     2),
  (6,  'rare',     2),
  (8,  'rare',     2),
  (10, 'rare',     2)
ON DUPLICATE KEY UPDATE
  `slot_capacity` = VALUES(`slot_capacity`);

-- END MIGRATION: 37_seed_dice_definitions.sql

-- BEGIN MIGRATION: 38_run_nodes_add_exit_type.sql
-- Migration: allow explicit run exit node type in run graph.
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit') NOT NULL;


-- END MIGRATION: 38_run_nodes_add_exit_type.sql

-- BEGIN MIGRATION: 39_unit_types_add_max_equipped_dice.sql
-- Migration: unit definition cap for equipped dice.
ALTER TABLE `unit_types`
  ADD COLUMN `max_equipped_dice` INT NOT NULL DEFAULT 2 AFTER `max_level`;


-- END MIGRATION: 39_unit_types_add_max_equipped_dice.sql

-- BEGIN MIGRATION: 40_affix_rarity_and_metadata.sql
ALTER TABLE `dice_definitions`
  MODIFY COLUMN `rarity` ENUM('common','uncommon','rare','epic','legendary') NOT NULL;

ALTER TABLE `affix_definitions`
  ADD COLUMN `rarity` ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common' AFTER `name`,
  ADD COLUMN `behavior_kind` ENUM('passive','triggered') NOT NULL DEFAULT 'passive' AFTER `op`,
  ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '' AFTER `max_value`,
  ADD KEY `ix_affix_definitions_rarity` (`rarity`);

-- END MIGRATION: 40_affix_rarity_and_metadata.sql

-- BEGIN MIGRATION: 41_seed_affix_definitions.sql
INSERT INTO `affix_definitions` (
  `slug`,
  `name`,
  `rarity`,
  `slot_cost`,
  `stat`,
  `op`,
  `behavior_kind`,
  `min_value`,
  `max_value`,
  `description`,
  `tags_json`
)
VALUES
  (
    'atk_plus',
    'Atk+',
    'common',
    1,
    'damage',
    'flat_add',
    'passive',
    1.000,
    1.000,
    '+1 damage on attack rolls.',
    JSON_ARRAY('starter_pool', 'combat')
  ),
  (
    'guard_plus',
    'Guard+',
    'common',
    1,
    'defense',
    'flat_add',
    'passive',
    1.000,
    1.000,
    '+1 defense while this die is equipped.',
    JSON_ARRAY('starter_pool', 'combat')
  ),
  (
    'bulwark_plus',
    'Bulwark',
    'uncommon',
    1,
    'defense',
    'pct_add',
    'passive',
    0.100,
    0.100,
    '+10% defense while this die is equipped.',
    JSON_ARRAY('starter_pool', 'combat')
  ),
  (
    'precision_plus',
    'Precision',
    'uncommon',
    1,
    'attack',
    'pct_add',
    'passive',
    0.100,
    0.100,
    '+10% attack while this die is equipped.',
    JSON_ARRAY('starter_pool', 'combat')
  ),
  (
    'execute_below_half',
    'Execute',
    'rare',
    1,
    'damage',
    'conditional',
    'triggered',
    0.150,
    0.150,
    'When the target is below 50% HP, deal 15% more damage.',
    JSON_ARRAY('starter_pool', 'combat', 'conditional')
  ),
  (
    'explode_once',
    'Explode',
    'rare',
    1,
    'dice_roll',
    'conditional',
    'triggered',
    1.000,
    1.000,
    'When this die rolls max, roll again once and combine the result.',
    JSON_ARRAY('starter_pool', 'combat', 'conditional')
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `rarity` = VALUES(`rarity`),
  `slot_cost` = VALUES(`slot_cost`),
  `stat` = VALUES(`stat`),
  `op` = VALUES(`op`),
  `behavior_kind` = VALUES(`behavior_kind`),
  `min_value` = VALUES(`min_value`),
  `max_value` = VALUES(`max_value`),
  `description` = VALUES(`description`),
  `tags_json` = VALUES(`tags_json`);

-- END MIGRATION: 41_seed_affix_definitions.sql

-- BEGIN MIGRATION: 42_shop_daily_deals.sql
CREATE TABLE `shop_daily_deals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `shop_date` DATE NOT NULL,
  `dice_definition_id` BIGINT UNSIGNED NOT NULL,
  `affix_definition_id` BIGINT UNSIGNED NOT NULL,
  `affix_value` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `purchased_at` TIMESTAMP NULL DEFAULT NULL,
  `purchased_dice_instance_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_daily_deals_user_date` (`user_id`, `shop_date`),
  KEY `ix_shop_daily_deals_user_id` (`user_id`),
  CONSTRAINT `fk_shop_daily_deals_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_shop_daily_deals_dice_definition_id` FOREIGN KEY (`dice_definition_id`) REFERENCES `dice_definitions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_shop_daily_deals_affix_definition_id` FOREIGN KEY (`affix_definition_id`) REFERENCES `affix_definitions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_shop_daily_deals_purchased_dice_instance_id` FOREIGN KEY (`purchased_dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 42_shop_daily_deals.sql

-- BEGIN MIGRATION: 43_seed_farm_tutorial_content.sql
INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('the_farm', 'The Farm', 'farm', 1, 3, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `enemy_templates` (
  `slug`,
  `name`,
  `tier`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `xp_reward`,
  `tags_json`
)
VALUES
  (
    'mudwrestler',
    'Mudwrestler',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 2, 'max_hp', 16),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee'), 'passives', JSON_ARRAY()),
    8,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'mudslinger',
    'Mudslinger',
    1,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 1, 'max_hp', 14),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_ranged'), 'passives', JSON_ARRAY()),
    8,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'grunt', 'damage_profile', 'ranged')
  ),
  (
    'mudking',
    'Mudking',
    2,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 4, 'max_hp', 30),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'), 'passives', JSON_ARRAY('thick_hide')),
    16,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'boss', 'damage_profile', 'melee')
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tier` = VALUES(`tier`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `xp_reward` = VALUES(`xp_reward`),
  `tags_json` = VALUES(`tags_json`);

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `description`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  (
    'the_farm_mud_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'A pair of pigs lurches out of the muck, giving the warband its first real skirmish.',
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Pigs',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'mudwrestler', 'pos', JSON_OBJECT('x', 2, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'mudslinger', 'pos', JSON_OBJECT('x', 0, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'the_farm_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'A crate of feed and spare gear sits untouched beside the fence line.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'the_farm_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'The warband catches its breath at a dry patch of hay before the final push.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest')
  ),
  (
    'the_farm_mud_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    2,
    'The Mudking snorts, stamps, and charges to defend the whole sty.',
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Pigs',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'mudking', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_boss_loot', 'rolls', 1)
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `description` = VALUES(`description`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);

-- END MIGRATION: 43_seed_farm_tutorial_content.sql

-- BEGIN MIGRATION: 44_unit_loadout_foundations.sql
ALTER TABLE `unit_instances`
  ADD COLUMN `display_name` VARCHAR(80) NULL AFTER `unit_type_id`;

CREATE TABLE `unit_instance_unlocked_abilities` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `source_tier` INT NOT NULL DEFAULT 1,
  `source_unit_type_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `ability_id`),
  KEY `ix_unit_instance_unlocked_abilities_source_unit_type_id` (`source_unit_type_id`),
  CONSTRAINT `fk_unit_instance_unlocked_abilities_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_instance_unlocked_abilities_source_unit_type_id`
    FOREIGN KEY (`source_unit_type_id`) REFERENCES `unit_types`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_instance_equipped_abilities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `equip_order` INT NOT NULL,
  `speed_cost` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_instance_equipped_abilities_unit_order` (`unit_instance_id`, `equip_order`),
  KEY `ix_unit_instance_equipped_abilities_unit_ability` (`unit_instance_id`, `ability_id`),
  CONSTRAINT `fk_unit_instance_equipped_abilities_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_ability_dice` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `slot_index` INT NOT NULL,
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `ability_id`, `slot_index`),
  UNIQUE KEY `uq_unit_ability_dice_dice_instance_id` (`dice_instance_id`),
  CONSTRAINT `fk_unit_ability_dice_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_ability_dice_dice_instance_id`
    FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `enemy_templates`
  ADD COLUMN `equipped_abilities_json` JSON NULL AFTER `ability_set_json`;

-- END MIGRATION: 44_unit_loadout_foundations.sql

-- BEGIN MIGRATION: 45_seed_enemy_equipped_loadouts.sql
UPDATE `enemy_templates`
SET `equipped_abilities_json` = JSON_OBJECT(
  'version', 1,
  'equipped', COALESCE(JSON_EXTRACT(`ability_set_json`, '$.actives'), JSON_ARRAY())
)
WHERE `equipped_abilities_json` IS NULL;

-- END MIGRATION: 45_seed_enemy_equipped_loadouts.sql

-- BEGIN MIGRATION: 46_multi_cell_unit_footprints.sql
UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(
  COALESCE(`base_stats_json`, JSON_OBJECT()),
  '$.formation',
  JSON_OBJECT('w', 2, 'h', 2)
)
WHERE JSON_UNQUOTE(JSON_EXTRACT(`tags_json`, '$.archetype')) = 'boss';

-- END MIGRATION: 46_multi_cell_unit_footprints.sql

-- BEGIN MIGRATION: 47_user_unlocks.sql
CREATE TABLE `user_unlocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `unlock_namespace` VARCHAR(64) NOT NULL,
  `unlock_key` VARCHAR(128) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_unlocks_user_namespace_key` (`user_id`, `unlock_namespace`, `unlock_key`),
  KEY `ix_user_unlocks_user_namespace` (`user_id`, `unlock_namespace`),
  CONSTRAINT `fk_user_unlocks_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
);

-- END MIGRATION: 47_user_unlocks.sql

-- BEGIN MIGRATION: 48_shop_daily_deal_slots.sql
ALTER TABLE `shop_daily_deals`
  ADD COLUMN `deal_slot` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `shop_date`;

ALTER TABLE `shop_daily_deals`
  DROP INDEX `uq_shop_daily_deals_user_date`,
  ADD UNIQUE KEY `uq_shop_daily_deals_user_date_slot` (`user_id`, `shop_date`, `deal_slot`);

-- END MIGRATION: 48_shop_daily_deal_slots.sql

-- BEGIN MIGRATION: 49_unit_progression_rework_foundations.sql
ALTER TABLE `unit_types`
  ADD COLUMN `promotion_level` INT NULL AFTER `max_level`,
  ADD COLUMN `promotion_grants_json` JSON NULL AFTER `ability_set_json`,
  ADD COLUMN `capstone_choices_json` JSON NULL AFTER `promotion_grants_json`;

CREATE TABLE `unit_instance_capstone_choices` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `source_unit_type_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `source_unit_type_id`),
  KEY `ix_unit_instance_capstone_choices_source_unit_type_id` (`source_unit_type_id`),
  CONSTRAINT `fk_unit_instance_capstone_choices_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_instance_capstone_choices_source_unit_type_id`
    FOREIGN KEY (`source_unit_type_id`) REFERENCES `unit_types`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 49_unit_progression_rework_foundations.sql

-- BEGIN MIGRATION: 50_seed_unit_progression_rework.sql
UPDATE `unit_types`
SET
  `max_level` = 10,
  `promotion_level` = CASE
    WHEN `slug` LIKE '%_t3' THEN NULL
    ELSE 6
  END,
  `promotion_grants_json` = CASE `slug`
    WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
    WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
    ELSE JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_bruiser_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('brawl_hardened', 'finisher'))
    WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('no_mercy', 'brutal_suppression'))
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'frontline_guardian_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('bodyguard', 'hold_the_line'))
    WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('unmoving', 'wall_of_scrap'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'backline_marksman_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('patient_aim', 'pick_your_mark'))
    WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('kill_lane', 'armor_gap'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'support_banner_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('rally_rhythm', 'patch_job'))
    WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('chant_of_violence', 'mob_mentality'))
    WHEN 'control_saboteur_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('toxic_tools', 'spiteful_reflex'))
    WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('disabling_hit', 'clean_shot'))
    ELSE JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
  END;

-- END MIGRATION: 50_seed_unit_progression_rework.sql

-- BEGIN MIGRATION: 51_seed_progression_branch_packages.sql
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
    'frontline_pit_fighter_t2',
    'Pit Fighter',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 4, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('desperate_swing'),
      'passives', JSON_ARRAY('counterpunch')
    ),
    10,
    2, 1, 3
  ),
  (
    'frontline_shieldbreaker_t2',
    'Shieldbreaker',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 6, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('crack_armor'),
      'passives', JSON_ARRAY('find_the_gap')
    ),
    10,
    1, 2, 3
  ),
  (
    'backline_trapper_t2',
    'Trapper',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 7, 'defense', 3, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('mark_target'),
      'passives', JSON_ARRAY('treasure_sense')
    ),
    10,
    2, 1, 2
  ),
  (
    'support_mascot_t2',
    'Mascot',
    'support',
    JSON_OBJECT('version', 1, 'attack', 2, 'defense', 5, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('lucky_chant'),
      'passives', JSON_ARRAY('attention_hog')
    ),
    10,
    1, 2, 3
  ),
  (
    'control_plaguehand_t2',
    'Plaguehand',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 4, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('poison_cloud'),
      'passives', JSON_ARRAY('nerve_toxin')
    ),
    10,
    2, 1, 2
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

UPDATE `unit_types`
SET `ability_set_json` = CASE `slug`
  WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
  WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
  WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
  WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
  WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
  ELSE `ability_set_json`
END
WHERE `slug` IN (
  'frontline_bruiser_t2',
  'frontline_guardian_t2',
  'backline_marksman_t2',
  'support_banner_t2',
  'control_saboteur_t2'
);

UPDATE `unit_types`
SET
  `promotion_level` = CASE
    WHEN RIGHT(`slug`, 3) = '_t2' THEN 6
    ELSE `promotion_level`
  END,
  `promotion_grants_json` = CASE `slug`
    WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('desperate_swing'), 'passives', JSON_ARRAY('counterpunch'))
    WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('crack_armor'), 'passives', JSON_ARRAY('find_the_gap'))
    WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('mark_target'), 'passives', JSON_ARRAY('treasure_sense'))
    WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('lucky_chant'), 'passives', JSON_ARRAY('attention_hog'))
    WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('poison_cloud'), 'passives', JSON_ARRAY('nerve_toxin'))
    ELSE `promotion_grants_json`
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('last_goblin_standing', 'crowd_favorite'))
    WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('shatter_plate', 'break_open'))
    WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('exposed_weaknesses', 'barbed_mark'))
    WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('dumb_luck', 'morale_goblin'))
    WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('lingering_cloud', 'sickly_weakness'))
    ELSE `capstone_choices_json`
  END
WHERE `slug` IN (
  'frontline_pit_fighter_t2',
  'frontline_shieldbreaker_t2',
  'backline_trapper_t2',
  'support_mascot_t2',
  'control_plaguehand_t2'
);

-- END MIGRATION: 51_seed_progression_branch_packages.sql

-- BEGIN MIGRATION: 52_rebalance_unit_drop_rates.sql
-- Rebalance unit drop rates to reduce inventory flooding from combat rewards.

UPDATE `loot_tables`
SET `entries_json` = JSON_SET(`entries_json`, '$.drops.units.chance', 0.05)
WHERE `slug` IN ('kobold_basic_loot', 'frogman_basic_loot');

UPDATE `loot_tables`
SET `entries_json` = JSON_SET(`entries_json`, '$.drops.units.chance', 0.12)
WHERE `slug` IN ('kobold_boss_loot', 'frogman_boss_loot');

-- END MIGRATION: 52_rebalance_unit_drop_rates.sql

-- BEGIN MIGRATION: 53_rebalance_farm_pigs.sql
UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'wrestle'),
    'passives', JSON_ARRAY()
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'wrestle')
WHERE `slug` = 'mudwrestler';

UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_ranged', 'mud_sling'),
    'passives', JSON_ARRAY()
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_ranged', 'mud_sling')
WHERE `slug` = 'mudslinger';

UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'wrestle', 'mud_slam'),
    'passives', JSON_ARRAY('thick_hide')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'wrestle', 'mud_slam')
WHERE `slug` = 'mudking';

-- END MIGRATION: 53_rebalance_farm_pigs.sql

-- BEGIN MIGRATION: 54_rebalance_kobolds_frogmen.sql
UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 28),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'taunting_guard'),
    'passives', JSON_ARRAY('shield_set', 'wall_of_scrap', 'unmoving')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'taunting_guard')
WHERE `slug` = 'kobold_shieldbearer';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 18),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('bomb_toss', 'basic_attack_ranged'),
    'passives', JSON_ARRAY('sharpshooter')
  ),
  `equipped_abilities_json` = JSON_ARRAY('bomb_toss', 'basic_attack_ranged')
WHERE `slug` = 'kobold_skirmisher';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 9, 'defense', 3, 'max_hp', 22),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_ranged', 'disarming_shot', 'aimed_shot'),
    'passives', JSON_ARRAY('sharpshooter', 'clean_shot')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_ranged', 'disarming_shot', 'aimed_shot')
WHERE `slug` = 'kobold_sharpshooter';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 42),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('bomb_toss', 'basic_attack_ranged', 'aimed_shot'),
    'passives', JSON_ARRAY('sharpshooter', 'patient_aim', 'dumb_luck')
  ),
  `equipped_abilities_json` = JSON_ARRAY('bomb_toss', 'basic_attack_ranged', 'aimed_shot')
WHERE `slug` = 'kobold_warchief';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 4, 'defense', 5, 'max_hp', 30),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'bog_splash'),
    'passives', JSON_ARRAY('thick_hide', 'brawl_hardened')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'bog_splash')
WHERE `slug` = 'frogman_bruiser';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 7, 'defense', 3, 'max_hp', 24),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'reed_spear', 'heavy_strike'),
    'passives', JSON_ARRAY('find_the_gap')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'reed_spear', 'heavy_strike')
WHERE `slug` = 'frogman_spearhunter';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 4, 'defense', 4, 'max_hp', 28),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'swamp_holler'),
    'passives', JSON_ARRAY('chant_of_violence', 'morale_goblin')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'swamp_holler')
WHERE `slug` = 'frogman_wardrummer';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 8, 'defense', 7, 'max_hp', 54),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'bog_splash', 'skullcrack'),
    'passives', JSON_ARRAY('thick_hide', 'crowd_favorite')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'bog_splash', 'skullcrack')
WHERE `slug` = 'frogman_bog_tyrant';

-- END MIGRATION: 54_rebalance_kobolds_frogmen.sql

-- BEGIN MIGRATION: 55_rebalance_mountains_swamps_encounters.sql
UPDATE `encounter_templates`
SET
  `difficulty_rating` = 1,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 2,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_2';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 3,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_3';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 5,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Command',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'kobold_warchief',     'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_boss_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 1,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 2,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_2';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 3,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_3';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 5,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Bog Court',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_bog_tyrant',  'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_boss_1';

-- END MIGRATION: 55_rebalance_mountains_swamps_encounters.sql

-- BEGIN MIGRATION: 56_dialogue_run_nodes_and_mystic_cave.sql
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue') NOT NULL;

INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('mystic_cave', 'Mystic Cave', 'mystic_cave', 1, 0, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);

-- END MIGRATION: 56_dialogue_run_nodes_and_mystic_cave.sql

-- BEGIN MIGRATION: 57_local_account_auth.sql
ALTER TABLE `users`
  MODIFY COLUMN `discord_id` VARCHAR(32) NULL,
  ADD COLUMN `local_email` VARCHAR(255) NULL AFTER `discord_id`,
  ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `local_email`,
  ADD UNIQUE KEY `uq_users_local_email` (`local_email`);

-- END MIGRATION: 57_local_account_auth.sql

-- BEGIN MIGRATION: 58_password_reset_tokens.sql
CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
  KEY `ix_password_reset_tokens_user_id_created_at` (`user_id`, `created_at`),
  CONSTRAINT `fk_password_reset_tokens_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 58_password_reset_tokens.sql

-- BEGIN MIGRATION: 59_unit_splice_variant_foundation.sql
ALTER TABLE `unit_instances`
  ADD COLUMN `splice_variant_slug` VARCHAR(64) NOT NULL DEFAULT 'basic_goblin' AFTER `unit_type_id`;

-- END MIGRATION: 59_unit_splice_variant_foundation.sql

-- BEGIN MIGRATION: 60_run_nodes_hazard_type.sql
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue','hazard') NOT NULL;

-- END MIGRATION: 60_run_nodes_hazard_type.sql

-- BEGIN MIGRATION: 61_bounty_board_foundation.sql
CREATE TABLE `bounty_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `description` TEXT NOT NULL,
  `category` ENUM('hunting','region','challenge') NOT NULL,
  `objective_json` JSON NOT NULL,
  `reward_json` JSON NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bounty_definitions_slug` (`slug`),
  KEY `ix_bounty_definitions_enabled_category` (`is_enabled`, `category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_bounties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `bounty_definition_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('accepted','completed','claimed','abandoned') NOT NULL DEFAULT 'accepted',
  `progress_json` JSON NOT NULL,
  `accepted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `claimed_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_bounties_user_definition` (`user_id`, `bounty_definition_id`),
  KEY `ix_user_bounties_user_status` (`user_id`, `status`, `updated_at`),
  CONSTRAINT `fk_user_bounties_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_bounties_definition_id`
    FOREIGN KEY (`bounty_definition_id`) REFERENCES `bounty_definitions` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 61_bounty_board_foundation.sql

-- BEGIN MIGRATION: 62_seed_precision_resolve_stats.sql
UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'frontline_bruiser_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_bruiser_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 7)
WHERE `slug` = 'frontline_bruiser_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 6)
WHERE `slug` = 'frontline_guardian_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 7)
WHERE `slug` = 'frontline_guardian_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 3, '$.resolve', 8)
WHERE `slug` = 'frontline_guardian_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 8, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'support_banner_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 7)
WHERE `slug` = 'support_banner_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'control_saboteur_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'control_saboteur_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_pit_fighter_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_shieldbreaker_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'backline_trapper_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'support_mascot_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'control_plaguehand_t2';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'kobold_skirmisher';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 6)
WHERE `slug` = 'kobold_shieldbearer';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'kobold_sharpshooter';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 5)
WHERE `slug` = 'kobold_warchief';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frogman_bruiser';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'frogman_spearhunter';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frogman_wardrummer';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 8)
WHERE `slug` = 'frogman_bog_tyrant';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'mudwrestler';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'mudslinger';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 7)
WHERE `slug` = 'mudking';

-- END MIGRATION: 62_seed_precision_resolve_stats.sql

-- BEGIN MIGRATION: 63_seed_splice_variants.sql
CREATE TABLE IF NOT EXISTS `splice_variants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NOT NULL,
  `stat_modifiers_json` JSON NOT NULL,
  `passive_summary` VARCHAR(255) NOT NULL DEFAULT '',
  `grant_weight` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_splice_variants_slug` (`slug`),
  KEY `ix_splice_variants_enabled_weight` (`is_enabled`, `grant_weight`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `splice_variants` (
  `slug`,
  `name`,
  `description`,
  `stat_modifiers_json`,
  `passive_summary`,
  `grant_weight`,
  `is_enabled`
)
VALUES
  (
    'basic_goblin',
    'Basic Goblin',
    'Baseline goblin stock with no splice tendency.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 0, 'precision', 0, 'resolve', 0),
    'No splice modifier.',
    60,
    1
  ),
  (
    'rat_splice',
    'Rat-Spliced',
    'Nimble scavenger instincts favor landing messy hits over standing firm.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 0, 'precision', 1, 'resolve', -1),
    '+1 Precision, -1 Resolve.',
    15,
    1
  ),
  (
    'toad_splice',
    'Toad-Spliced',
    'Stubborn bog-blooded bulk improves resistance at the cost of aim.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 2, 'precision', -1, 'resolve', 1),
    '+2 HP, +1 Resolve, -1 Precision.',
    15,
    1
  ),
  (
    'bat_splice',
    'Bat-Spliced',
    'Echo-sharp senses improve careful strikes but leave the body frailer.',
    JSON_OBJECT('attack', 1, 'defense', 0, 'max_hp', -1, 'precision', 1, 'resolve', 0),
    '+1 Attack, +1 Precision, -1 HP.',
    10,
    1
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `stat_modifiers_json` = VALUES(`stat_modifiers_json`),
  `passive_summary` = VALUES(`passive_summary`),
  `grant_weight` = VALUES(`grant_weight`),
  `is_enabled` = VALUES(`is_enabled`);

-- END MIGRATION: 63_seed_splice_variants.sql

-- BEGIN MIGRATION: 64_add_raw_chaos_currency.sql
ALTER TABLE `player_state`
  ADD COLUMN `currency_raw_chaos` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `currency_hard`;

-- END MIGRATION: 64_add_raw_chaos_currency.sql

-- BEGIN MIGRATION: 65_run_nodes_shrine_type.sql
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue','hazard','shrine') NOT NULL;

-- END MIGRATION: 65_run_nodes_shrine_type.sql

-- BEGIN MIGRATION: 66_chaos_encounter_results.sql
CREATE TABLE `chaos_encounter_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `node_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('generated','manipulated','confirmed') NOT NULL DEFAULT 'generated',
  `seed` INT UNSIGNED NOT NULL,
  `reels_json` JSON NOT NULL,
  `reward_multiplier` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `rerolled_reel_index` TINYINT UNSIGNED NULL DEFAULT NULL,
  `manipulation_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chaos_encounter_results_node` (`node_id`),
  KEY `ix_chaos_encounter_results_user_run` (`user_id`, `run_id`),
  CONSTRAINT `fk_chaos_encounter_results_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_chaos_encounter_results_run_id`
    FOREIGN KEY (`run_id`) REFERENCES `region_runs` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_chaos_encounter_results_node_id`
    FOREIGN KEY (`node_id`) REFERENCES `run_nodes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END MIGRATION: 66_chaos_encounter_results.sql

-- BEGIN MIGRATION: 67_tier_three_progression_coverage.sql
INSERT INTO `unit_types` (
  `slug`,
  `name`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `max_level`,
  `attack_per_level`,
  `defense_per_level`,
  `max_hp_per_level`,
  `promotion_level`,
  `promotion_grants_json`,
  `capstone_choices_json`
)
VALUES
  (
    'support_banner_t3',
    'Warchanter',
    'support',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 8, 'max_hp', 38, 'precision', 5, 'resolve', 8),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally', 'warcry'),
      'passives', JSON_ARRAY('battle_tempo')
    ),
    10,
    1, 2, 3,
    NULL,
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('endless_chant', 'warband_legend'))
  ),
  (
    'control_saboteur_t3',
    'Venomwright',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 5, 'max_hp', 32, 'precision', 7, 'resolve', 6),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart', 'disarming_shot'),
      'passives', JSON_ARRAY('opportunist')
    ),
    10,
    2, 1, 2,
    NULL,
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('plague_mastery', 'cruel_setup'))
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `max_level` = VALUES(`max_level`),
  `attack_per_level` = VALUES(`attack_per_level`),
  `defense_per_level` = VALUES(`defense_per_level`),
  `max_hp_per_level` = VALUES(`max_hp_per_level`),
  `promotion_level` = VALUES(`promotion_level`),
  `promotion_grants_json` = VALUES(`promotion_grants_json`),
  `capstone_choices_json` = VALUES(`capstone_choices_json`);

UPDATE `unit_types`
SET
  `promotion_level` = CASE
    WHEN RIGHT(`slug`, 3) = '_t2' THEN 10
    ELSE `promotion_level`
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('unstoppable_heap', 'skullquake'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('fortress_stance', 'last_wall'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('perfect_lane', 'apex_predator'))
    WHEN 'support_banner_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('endless_chant', 'warband_legend'))
    WHEN 'control_saboteur_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('plague_mastery', 'cruel_setup'))
    ELSE `capstone_choices_json`
  END
WHERE RIGHT(`slug`, 3) IN ('_t2', '_t3');

-- END MIGRATION: 67_tier_three_progression_coverage.sql

-- BEGIN MIGRATION: 68_run_nodes_chaos_type.sql
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue','hazard','shrine','chaos') NOT NULL;

-- END MIGRATION: 68_run_nodes_chaos_type.sql

-- BEGIN MIGRATION: 69_chaos_encounter_finalized_rewards.sql
ALTER TABLE `chaos_encounter_results`
  ADD COLUMN `finalized_rewards_json` JSON NULL AFTER `reward_multiplier`,
  ADD COLUMN `finalized_at` TIMESTAMP NULL DEFAULT NULL AFTER `manipulation_count`;

-- END MIGRATION: 69_chaos_encounter_finalized_rewards.sql

-- BEGIN MIGRATION: 70_drop_unit_dice.sql
DROP TABLE IF EXISTS `unit_dice`;

-- END MIGRATION: 70_drop_unit_dice.sql

-- BEGIN MIGRATION: 71_coalesce_unit_type_ability_sets.sql
UPDATE `unit_types`
SET `ability_set_json` = CASE `slug`
  WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
  WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
  WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
  WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
  WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
  WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('desperate_swing'), 'passives', JSON_ARRAY('counterpunch'))
  WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('crack_armor'), 'passives', JSON_ARRAY('find_the_gap'))
  WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('mark_target'), 'passives', JSON_ARRAY('treasure_sense'))
  WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('lucky_chant'), 'passives', JSON_ARRAY('attention_hog'))
  WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('poison_cloud'), 'passives', JSON_ARRAY('nerve_toxin'))
  ELSE `ability_set_json`
END
WHERE `promotion_grants_json` IS NOT NULL;

ALTER TABLE `unit_types`
  DROP COLUMN `promotion_grants_json`;

-- END MIGRATION: 71_coalesce_unit_type_ability_sets.sql

-- BEGIN MIGRATION: 72_drop_unit_type_max_equipped_dice.sql
ALTER TABLE `unit_types`
  DROP COLUMN `max_equipped_dice`;

-- END MIGRATION: 72_drop_unit_type_max_equipped_dice.sql

-- BEGIN MIGRATION: 73_generic_progression_items.sql
CREATE TABLE IF NOT EXISTS `items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(48) NOT NULL,
  `source_region_id` BIGINT UNSIGNED NULL,
  `source_family_slug` VARCHAR(64) NULL,
  `rarity` VARCHAR(32) NOT NULL DEFAULT 'common',
  `is_stackable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_visible_before_discovery` TINYINT(1) NOT NULL DEFAULT 0,
  `is_spendable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_primary_progression` TINYINT(1) NOT NULL DEFAULT 0,
  `icon_key` VARCHAR(96) NULL,
  `lore_key` VARCHAR(96) NULL,
  `meta_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_slug` (`slug`),
  KEY `ix_items_category` (`category`, `rarity`, `slug`),
  KEY `ix_items_source_region` (`source_region_id`, `category`),
  CONSTRAINT `fk_items_source_region_id` FOREIGN KEY (`source_region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_items` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `first_acquired_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `item_id`),
  KEY `ix_user_items_item_id` (`item_id`),
  CONSTRAINT `fk_user_items_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
SELECT
  'pig_ear',
  'Pig Ear',
  'A stubborn scrap of pig-kin possibility. The Wrong Machine will know what to do with it.',
  'lineage_material',
  r.`id`,
  'pigs',
  'common',
  1,
  1,
  1,
  1,
  'item_pig_ear',
  'pig_kin_material',
  JSON_OBJECT('lineage_slug', 'pig_kin', 'tutorial_material', true)
FROM `regions` r
WHERE r.`slug` = 'the_farm'
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);

INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
SELECT
  'mudking_crown_fragment',
  'Mudking Crown Fragment',
  'A boss-won catalyst heavy with farmyard authority.',
  'boss_catalyst',
  r.`id`,
  'pigs',
  'rare',
  1,
  0,
  1,
  1,
  'item_mudking_crown_fragment',
  'mudking_catalyst',
  JSON_OBJECT('lineage_slug', 'pig_kin', 'boss_catalyst', true)
FROM `regions` r
WHERE r.`slug` = 'the_farm'
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);

-- END MIGRATION: 73_generic_progression_items.sql

-- BEGIN MIGRATION: 74_seed_pig_kin_variant.sql
INSERT INTO `splice_variants` (
  `slug`,
  `name`,
  `description`,
  `stat_modifiers_json`,
  `passive_summary`,
  `grant_weight`,
  `is_enabled`
)
VALUES (
  'pig_kin',
  'Pig Kin',
  'Stubborn farmyard goblin-kin with a thicker hide and a slightly slower hand.',
  JSON_OBJECT('attack', 0, 'defense', 1, 'max_hp', 2, 'precision', -1, 'resolve', 1),
  '+1 Defense, +2 HP, +1 Resolve, -1 Precision.',
  12,
  1
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `stat_modifiers_json` = VALUES(`stat_modifiers_json`),
  `passive_summary` = VALUES(`passive_summary`),
  `grant_weight` = VALUES(`grant_weight`),
  `is_enabled` = VALUES(`is_enabled`);

-- END MIGRATION: 74_seed_pig_kin_variant.sql

-- BEGIN MIGRATION: 75_seed_healing_consumables.sql
INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
VALUES
(
  'field_poultice',
  'Field Poultice',
  'A quick wrap for patching up a wounded unit between encounters.',
  'consumable',
  NULL,
  NULL,
  'common',
  1,
  1,
  1,
  0,
  'item_field_poultice',
  'healing_consumable',
  JSON_OBJECT('effect', JSON_OBJECT('type', 'heal_run_unit_hp', 'amount', 10))
),
(
  'hearty_bone_broth',
  'Hearty Bone Broth',
  'A stronger recovery draught that brings one run unit back from the edge.',
  'consumable',
  NULL,
  NULL,
  'uncommon',
  1,
  1,
  1,
  0,
  'item_hearty_bone_broth',
  'healing_consumable',
  JSON_OBJECT('effect', JSON_OBJECT('type', 'heal_run_unit_hp', 'amount', 25))
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);

-- END MIGRATION: 75_seed_healing_consumables.sql

-- BEGIN MIGRATION: 76_seed_energy_consumables.sql
INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
VALUES
(
  'travel_ration',
  'Travel Ration',
  'A packed bite that restores a small amount of energy before the next run.',
  'consumable',
  NULL,
  NULL,
  'common',
  1,
  1,
  1,
  0,
  'item_travel_ration',
  'energy_consumable',
  JSON_OBJECT('effect', JSON_OBJECT('type', 'restore_energy', 'amount', 10))
),
(
  'sparkroot_tonic',
  'Sparkroot Tonic',
  'A sharp tonic that restores a larger amount of energy without exceeding the current cap.',
  'consumable',
  NULL,
  NULL,
  'uncommon',
  1,
  1,
  1,
  0,
  'item_sparkroot_tonic',
  'energy_consumable',
  JSON_OBJECT('effect', JSON_OBJECT('type', 'restore_energy', 'amount', 25))
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);

-- END MIGRATION: 76_seed_energy_consumables.sql

-- BEGIN MIGRATION: 77_unit_type_precision_resolve_growth.sql
ALTER TABLE `unit_types`
  ADD COLUMN `precision_per_level` INT NOT NULL DEFAULT 1 AFTER `max_hp_per_level`,
  ADD COLUMN `resolve_per_level` INT NOT NULL DEFAULT 1 AFTER `precision_per_level`;

-- END MIGRATION: 77_unit_type_precision_resolve_growth.sql

-- BEGIN MIGRATION: 78_run_pattern_catalog_storage.sql
CREATE TABLE `run_pattern_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `status` ENUM('draft','enabled','disabled') NOT NULL DEFAULT 'draft',
  `definition_json` JSON NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_pattern_definitions_slug_version` (`slug`, `version`),
  UNIQUE KEY `uq_run_pattern_definitions_content_hash` (`content_hash`),
  KEY `ix_run_pattern_definitions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_pattern_region_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern_definition_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `generator_version` VARCHAR(32) NOT NULL,
  `base_weight` INT UNSIGNED NOT NULL,
  `allowed_phase` ENUM('start','spine','branch','cap','boss_approach','terminal') NOT NULL,
  `min_depth` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_depth` INT UNSIGNED NULL,
  `max_per_run` INT UNSIGNED NULL,
  `cooldown_patterns` INT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `weight_modifiers_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_pattern_region_rules` (`pattern_definition_id`, `region_id`, `generator_version`, `allowed_phase`, `min_depth`),
  KEY `ix_run_pattern_region_rules_region_phase` (`region_id`, `generator_version`, `allowed_phase`, `enabled`),
  CONSTRAINT `fk_run_pattern_region_rules_pattern`
    FOREIGN KEY (`pattern_definition_id`) REFERENCES `run_pattern_definitions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_pattern_region_rules_region`
    FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_generation_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `generator_version` VARCHAR(32) NOT NULL,
  `profile_version` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `bounds_json` JSON NOT NULL,
  `budgets_json` JSON NOT NULL,
  `requirements_json` JSON NOT NULL,
  `retry_policy_json` JSON NOT NULL,
  `weight_policy_json` JSON NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_generation_profiles_region_version` (`region_id`, `generator_version`, `profile_version`),
  UNIQUE KEY `uq_run_generation_profiles_content_hash` (`content_hash`),
  KEY `ix_run_generation_profiles_enabled` (`region_id`, `generator_version`, `enabled`),
  CONSTRAINT `fk_run_generation_profiles_region`
    FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `region_runs`
  ADD COLUMN `generator_version` VARCHAR(32) NULL AFTER `seed`,
  ADD COLUMN `generation_profile_version` INT UNSIGNED NULL AFTER `generator_version`,
  ADD COLUMN `pattern_catalog_hash` CHAR(64) NULL AFTER `generation_profile_version`,
  ADD COLUMN `generation_attempt` INT UNSIGNED NULL AFTER `pattern_catalog_hash`,
  ADD COLUMN `generation_summary_json` JSON NULL AFTER `generation_attempt`,
  ADD KEY `ix_region_runs_generator_version` (`region_id`, `generator_version`);

-- END MIGRATION: 78_run_pattern_catalog_storage.sql

-- BEGIN MIGRATION: 79_seed_pattern_v2_catalog.sql
SET @v2_mountain_start = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_start_cluster',
  'version', 1,
  'status', 'enabled',
  'width', 3,
  'height', 3,
  'cost', 4,
  'tags', JSON_ARRAY('start', 'mountain'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'easy_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('key', 'minor_loot', 'type', 'loot', 'quality', 'minor')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'poor_shrine', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'start_node', 'type', 'combat', 'difficulty', 'easy', 'role', 'start'), JSON_OBJECT('key', 'mountain_start_dialogue', 'type', 'dialogue', 'dialogue_id', 'mountain_start'), NULL)
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'start_node', 'to', 'mountain_start_dialogue'),
    JSON_OBJECT('from', 'mountain_start_dialogue', 'to', 'poor_shrine'),
    JSON_OBJECT('from', 'poor_shrine', 'to', 'easy_combat'),
    JSON_OBJECT('from', 'easy_combat', 'to', 'minor_loot')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 2, 'direction', 'right'),
    JSON_OBJECT('row', 1, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_start_cluster', 1, 'enabled', @v2_mountain_start, SHA2(CAST(@v2_mountain_start AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_mountain_braid = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_braided_combat',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 3,
  'cost', 7,
  'tags', JSON_ARRAY('mountain', 'combat', 'braid'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'combat_a', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_b', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_c', 'type', 'combat', 'difficulty', 'hard')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'good_loot', 'type', 'loot', 'quality', 'good'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'hazard', 'type', 'hazard', 'quality', 'poor'), NULL, JSON_OBJECT('key', 'shrine', 'type', 'shrine', 'quality', 'poor'), NULL, JSON_OBJECT('key', 'chaos', 'type', 'chaos'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'combat_a', 'to', 'combat_b', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'combat_b', 'to', 'combat_c', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'combat_a', 'to', 'good_loot'),
    JSON_OBJECT('from', 'good_loot', 'to', 'rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'hazard', 'to', 'shrine'),
    JSON_OBJECT('from', 'shrine', 'to', 'chaos')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 1, 'col', 3, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_braided_combat', 1, 'enabled', @v2_mountain_braid, SHA2(CAST(@v2_mountain_braid AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_general_loot_connector = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_general_loot_connector',
  'version', 1,
  'status', 'enabled',
  'width', 2,
  'height', 1,
  'cost', 1,
  'tags', JSON_ARRAY('general', 'loot', 'connector'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'poor_loot', 'type', 'loot', 'quality', 'poor'), JSON_OBJECT('type', 'connector'))
  ),
  'connections', JSON_ARRAY(),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_general_loot_connector', 1, 'enabled', @v2_general_loot_connector, SHA2(CAST(@v2_general_loot_connector AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_mountain_boss_exit = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_boss_exit',
  'version', 1,
  'status', 'enabled',
  'width', 2,
  'height', 3,
  'cost', 2,
  'tags', JSON_ARRAY('terminal', 'mountain', 'boss'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'exit', 'type', 'exit')),
    JSON_ARRAY(JSON_OBJECT('key', 'boss', 'type', 'boss'), JSON_OBJECT('type', 'connector')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'reward_loot', 'type', 'loot', 'quality', 'good'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'boss', 'to', 'exit', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 1))),
    JSON_OBJECT('from', 'boss', 'to', 'reward_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 1)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_boss_exit', 1, 'enabled', @v2_mountain_boss_exit, SHA2(CAST(@v2_mountain_boss_exit AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 100, 'start', 0, 0, 1, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_start_cluster' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 80, 'spine', 1, 5, 3, 1, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_braided_combat' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 30, 'spine', 1, 5, 2, 1, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_general_loot_connector' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 100, 'terminal', 5, 8, 1, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_boss_exit' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

SET @v2_mountain_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 1,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 11, 'min_row', 0, 'max_row', 5, 'target_width', 9, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 18, 'target', 24, 'max', 30, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 6, 'max', 8, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 7, 'target', 9, 'max', 11, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 5, 'target', 8, 'max', 12, 'hard', true),
    'reward_nodes', JSON_OBJECT('min', 2, 'target', 3, 'max', 5, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 1, 'target', 2, 'max', 4, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest'),
    'required_tags', JSON_ARRAY('start', 'mountain', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.35, 'prefer_socket_reuse', 1.2)
);

INSERT INTO `run_generation_profiles` (
  `region_id`,
  `generator_version`,
  `profile_version`,
  `enabled`,
  `bounds_json`,
  `budgets_json`,
  `requirements_json`,
  `retry_policy_json`,
  `weight_policy_json`,
  `content_hash`
)
SELECT
  r.`id`,
  'pattern-v2',
  1,
  1,
  JSON_EXTRACT(@v2_mountain_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_profile AS CHAR), 256)
FROM `regions` r
WHERE r.`slug` = 'mountains'
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `bounds_json` = VALUES(`bounds_json`),
  `budgets_json` = VALUES(`budgets_json`),
  `requirements_json` = VALUES(`requirements_json`),
  `retry_policy_json` = VALUES(`retry_policy_json`),
  `weight_policy_json` = VALUES(`weight_policy_json`),
  `content_hash` = VALUES(`content_hash`);

-- END MIGRATION: 79_seed_pattern_v2_catalog.sql

-- BEGIN MIGRATION: 80_fix_pattern_v2_perimeter_exits.sql
UPDATE `run_pattern_definitions`
SET
  `definition_json` = JSON_SET(
    `definition_json`,
    '$.exits[1]',
    JSON_OBJECT('row', 2, 'col', 1, 'direction', 'down')
  ),
  `content_hash` = SHA2(CAST(JSON_SET(
    `definition_json`,
    '$.exits[1]',
    JSON_OBJECT('row', 2, 'col', 1, 'direction', 'down')
  ) AS CHAR), 256)
WHERE `slug` = 'v2_mountain_start_cluster'
  AND `version` = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.schema_version')) = 'pattern-v2'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].row')) = '1'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].col')) = '1';

UPDATE `run_pattern_definitions`
SET
  `definition_json` = JSON_REMOVE(`definition_json`, '$.exits[1]'),
  `content_hash` = SHA2(CAST(JSON_REMOVE(`definition_json`, '$.exits[1]') AS CHAR), 256)
WHERE `slug` = 'v2_mountain_braided_combat'
  AND `version` = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.schema_version')) = 'pattern-v2'
  AND JSON_LENGTH(JSON_EXTRACT(`definition_json`, '$.exits')) = 3
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].row')) = '1'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].col')) = '3';

-- END MIGRATION: 80_fix_pattern_v2_perimeter_exits.sql

-- BEGIN MIGRATION: 81_seed_pattern_v2_dense_mountain_tiles.sql
SET @v2_mountain_dense_braid = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_dense_braid',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 5,
  'cost', 14,
  'tags', JSON_ARRAY('mountain', 'combat', 'braid', 'dense'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'combat_top', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'hard_top', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'loot_top', 'type', 'loot', 'quality', 'good')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'shrine_top', 'type', 'shrine', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest_top', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'combat_mid', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'hard_mid', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'chaos_mid', 'type', 'chaos')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'loot_low', 'type', 'loot', 'quality', 'minor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'shrine_low', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'hazard_low', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_low', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest_low', 'type', 'rest'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'combat_top', 'to', 'hard_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'hard_top', 'to', 'loot_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'combat_top', 'to', 'shrine_top'),
    JSON_OBJECT('from', 'shrine_top', 'to', 'rest_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'shrine_top', 'to', 'hard_mid'),
    JSON_OBJECT('from', 'rest_top', 'to', 'chaos_mid'),
    JSON_OBJECT('from', 'combat_mid', 'to', 'hard_mid', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'hard_mid', 'to', 'chaos_mid', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 3))),
    JSON_OBJECT('from', 'combat_mid', 'to', 'loot_low'),
    JSON_OBJECT('from', 'hard_mid', 'to', 'shrine_low'),
    JSON_OBJECT('from', 'loot_low', 'to', 'shrine_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2))),
    JSON_OBJECT('from', 'shrine_low', 'to', 'rest_low'),
    JSON_OBJECT('from', 'hazard_low', 'to', 'combat_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 1))),
    JSON_OBJECT('from', 'combat_low', 'to', 'rest_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 3)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 4, 'col', 4, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_dense_braid', 1, 'enabled', @v2_mountain_dense_braid, SHA2(CAST(@v2_mountain_dense_braid AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 140, 'spine', 1, 5, 2, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_dense_braid' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

UPDATE `run_pattern_region_rules` rpr
INNER JOIN `run_pattern_definitions` rpd ON rpd.`id` = rpr.`pattern_definition_id`
INNER JOIN `regions` r ON r.`id` = rpr.`region_id`
SET
  rpr.`base_weight` = 40,
  rpr.`max_per_run` = 1
WHERE r.`slug` = 'mountains'
  AND rpr.`generator_version` = 'pattern-v2'
  AND rpr.`allowed_phase` = 'spine'
  AND rpd.`slug` = 'v2_mountain_braided_combat'
  AND rpd.`version` = 1;

UPDATE `run_pattern_region_rules` rpr
INNER JOIN `run_pattern_definitions` rpd ON rpd.`id` = rpr.`pattern_definition_id`
INNER JOIN `regions` r ON r.`id` = rpr.`region_id`
SET
  rpr.`base_weight` = 10,
  rpr.`max_per_run` = 1
WHERE r.`slug` = 'mountains'
  AND rpr.`generator_version` = 'pattern-v2'
  AND rpr.`allowed_phase` = 'spine'
  AND rpd.`slug` = 'v2_general_loot_connector'
  AND rpd.`version` = 1;

SET @v2_mountain_dense_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 2,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 15, 'min_row', 0, 'max_row', 5, 'target_width', 14, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 24, 'target', 32, 'max', 38, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 10, 'target', 14, 'max', 15, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 7, 'target', 11, 'max', 14, 'hard', true),
    'reward_nodes', JSON_OBJECT('min', 3, 'target', 5, 'max', 8, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 2, 'target', 4, 'max', 6, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest'),
    'required_tags', JSON_ARRAY('start', 'mountain', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.7, 'prefer_socket_reuse', 1.2)
);

INSERT INTO `run_generation_profiles` (
  `region_id`,
  `generator_version`,
  `profile_version`,
  `enabled`,
  `bounds_json`,
  `budgets_json`,
  `requirements_json`,
  `retry_policy_json`,
  `weight_policy_json`,
  `content_hash`
)
SELECT
  r.`id`,
  'pattern-v2',
  2,
  1,
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_dense_profile AS CHAR), 256)
FROM `regions` r
WHERE r.`slug` = 'mountains'
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `bounds_json` = VALUES(`bounds_json`),
  `budgets_json` = VALUES(`budgets_json`),
  `requirements_json` = VALUES(`requirements_json`),
  `retry_policy_json` = VALUES(`retry_policy_json`),
  `weight_policy_json` = VALUES(`weight_policy_json`),
  `content_hash` = VALUES(`content_hash`);

-- END MIGRATION: 81_seed_pattern_v2_dense_mountain_tiles.sql

-- BEGIN MIGRATION: 82_compact_mountains_pattern_v2_profile.sql
SET @v2_mountain_compact_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 3,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 15, 'min_row', 0, 'max_row', 5, 'target_width', 16, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 24, 'target', 24, 'max', 32, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 4, 'max', 5, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 5, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 10, 'target', 16, 'max', 20, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 7, 'target', 10, 'max', 14, 'hard', true),
    'reward_nodes', JSON_OBJECT('min', 3, 'target', 5, 'max', 8, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 2, 'target', 4, 'max', 6, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest'),
    'required_tags', JSON_ARRAY('start', 'mountain', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.8, 'prefer_socket_reuse', 1.2)
);

INSERT INTO `run_generation_profiles` (
  `region_id`,
  `generator_version`,
  `profile_version`,
  `enabled`,
  `bounds_json`,
  `budgets_json`,
  `requirements_json`,
  `retry_policy_json`,
  `weight_policy_json`,
  `content_hash`
)
SELECT
  r.`id`,
  'pattern-v2',
  3,
  1,
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_compact_profile AS CHAR), 256)
FROM `regions` r
WHERE r.`slug` = 'mountains'
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `bounds_json` = VALUES(`bounds_json`),
  `budgets_json` = VALUES(`budgets_json`),
  `requirements_json` = VALUES(`requirements_json`),
  `retry_policy_json` = VALUES(`retry_policy_json`),
  `weight_policy_json` = VALUES(`weight_policy_json`),
  `content_hash` = VALUES(`content_hash`);

-- END MIGRATION: 82_compact_mountains_pattern_v2_profile.sql

-- BEGIN MIGRATION: 99_finalize.sql
-- Dice Goblins â€” MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)

-- Re-enable FK checks if you disabled them
SET FOREIGN_KEY_CHECKS=1;

-- END MIGRATION: 99_finalize.sql
