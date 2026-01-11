-- Dice Goblins — MySQL Schema (MVP)
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
