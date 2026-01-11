-- Dice Goblins — MySQL Schema (MVP)
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
