CREATE TABLE `user_grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `grant_slug` VARCHAR(64) NOT NULL,
  `meta_json` JSON NULL,
  `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_grants_user_id_grant_slug` (`user_id`, `grant_slug`),
  KEY `ix_user_grants_user_id` (`user_id`),
  CONSTRAINT `fk_user_grants_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
