-- Dice Goblins — MySQL Schema (MVP)
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
