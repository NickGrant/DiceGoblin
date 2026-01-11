-- Dice Goblins — MySQL Schema (MVP)
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
