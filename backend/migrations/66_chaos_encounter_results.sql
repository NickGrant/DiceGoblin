CREATE TABLE `chaos_encounter_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `node_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('generated','manipulated','confirmed') NOT NULL DEFAULT 'generated',
  `seed` INT UNSIGNED NOT NULL,
  `reels_json` JSON NOT NULL,
  `reward_multiplier` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `rerolled_reel_index` TINYINT UNSIGNED NULL DEFAULT NULL,
  `manipulation_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chaos_encounter_results_node` (`node_id`),
  KEY `ix_chaos_encounter_results_user_run` (`user_id`, `run_id`),
  CONSTRAINT `fk_chaos_encounter_results_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_chaos_encounter_results_run_id`
    FOREIGN KEY (`run_id`) REFERENCES `region_runs` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_chaos_encounter_results_node_id`
    FOREIGN KEY (`node_id`) REFERENCES `run_nodes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
