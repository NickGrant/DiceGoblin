-- Dice Goblins — MySQL Schema (MVP)
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
