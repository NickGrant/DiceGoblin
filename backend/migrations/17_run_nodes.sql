-- Dice Goblins — MySQL Schema (MVP)
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
