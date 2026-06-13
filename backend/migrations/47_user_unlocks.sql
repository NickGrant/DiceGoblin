CREATE TABLE `user_unlocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `unlock_namespace` VARCHAR(64) NOT NULL,
  `unlock_key` VARCHAR(128) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_unlocks_user_namespace_key` (`user_id`, `unlock_namespace`, `unlock_key`),
  KEY `ix_user_unlocks_user_namespace` (`user_id`, `unlock_namespace`),
  CONSTRAINT `fk_user_unlocks_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
);
