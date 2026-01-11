-- Dice Goblins — MySQL Schema (MVP)
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
