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
