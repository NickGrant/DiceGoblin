-- Dice Goblins — MySQL Schema (MVP)
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
