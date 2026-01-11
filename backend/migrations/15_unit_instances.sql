-- Dice Goblins — MySQL Schema (MVP)
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
