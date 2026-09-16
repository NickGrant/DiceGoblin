-- Dice Goblins vNext fresh database baseline (MySQL 8.0+).
-- Authored gameplay content belongs in Git-tracked JSON, not SQL.

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` VARCHAR(128) NOT NULL,
  `avatar_url` VARCHAR(255) NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_local_credentials` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_local_credentials_email` (`email`),
  CONSTRAINT `fk_user_local_credentials_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_external_identities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL,
  `provider_user_id` VARCHAR(128) NOT NULL,
  `provider_email` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_external_identity_provider_user` (`provider`, `provider_user_id`),
  KEY `ix_external_identity_user` (`user_id`),
  CONSTRAINT `fk_user_external_identities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
  KEY `ix_password_reset_tokens_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_state` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `teeth` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `raw_chaos` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `energy_current` INT UNSIGNED NOT NULL,
  `energy_last_regen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active_squad_id` BIGINT UNSIGNED NULL,
  `player_revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `ix_user_state_active_squad` (`active_squad_id`),
  CONSTRAINT `fk_user_state_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idempotency_requests` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `idempotency_key` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `result_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `idempotency_key`),
  KEY `ix_idempotency_requests_created` (`created_at`),
  CONSTRAINT `fk_idempotency_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_instances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `unit_type_id` VARCHAR(128) NOT NULL,
  `kin_id` VARCHAR(128) NOT NULL,
  `display_name` VARCHAR(128) NOT NULL,
  `level` INT UNSIGNED NOT NULL DEFAULT 1,
  `xp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `lifecycle_status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_unit_instances_user_status` (`user_id`, `lifecycle_status`),
  KEY `ix_unit_instances_user_type` (`user_id`, `unit_type_id`),
  CONSTRAINT `chk_unit_instances_level` CHECK (`level` >= 1),
  CONSTRAINT `fk_unit_instances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_promotions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `from_unit_type_id` VARCHAR(128) NOT NULL,
  `to_unit_type_id` VARCHAR(128) NOT NULL,
  `promoted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_unit_promotions_unit_time` (`unit_id`, `promoted_at`, `id`),
  CONSTRAINT `fk_unit_promotions_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_abilities` (
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(128) NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`, `ability_id`),
  CONSTRAINT `fk_unit_abilities_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_ability_loadout` (
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(128) NOT NULL,
  `equip_order` SMALLINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`, `equip_order`),
  KEY `ix_unit_ability_loadout_owned_ability` (`unit_id`, `ability_id`),
  CONSTRAINT `fk_unit_ability_loadout_owned_ability`
    FOREIGN KEY (`unit_id`, `ability_id`) REFERENCES `unit_abilities` (`unit_id`, `ability_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dice_instances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `size` SMALLINT UNSIGNED NOT NULL,
  `profile_id` VARCHAR(128) NOT NULL,
  `lifecycle_status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dice_instances_user_status` (`user_id`, `lifecycle_status`),
  KEY `ix_dice_instances_user_profile` (`user_id`, `profile_id`),
  CONSTRAINT `chk_dice_instances_size` CHECK (`size` >= 1),
  CONSTRAINT `fk_dice_instances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_ability_dice` (
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(128) NOT NULL,
  `slot_index` SMALLINT UNSIGNED NOT NULL,
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`, `ability_id`, `slot_index`),
  UNIQUE KEY `uq_unit_ability_dice_die` (`dice_instance_id`),
  CONSTRAINT `fk_unit_ability_dice_owned_ability`
    FOREIGN KEY (`unit_id`, `ability_id`) REFERENCES `unit_abilities` (`unit_id`, `ability_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_unit_ability_dice_die`
    FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `squads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_squads_user` (`user_id`),
  CONSTRAINT `fk_squads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `squad_units` (
  `squad_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `position` TINYINT UNSIGNED NOT NULL,
  `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`squad_id`, `position`),
  UNIQUE KEY `uq_squad_units_squad_unit` (`squad_id`, `unit_id`),
  KEY `ix_squad_units_unit` (`unit_id`),
  CONSTRAINT `chk_squad_units_position` CHECK (`position` BETWEEN 0 AND 8),
  CONSTRAINT `fk_squad_units_squad` FOREIGN KEY (`squad_id`) REFERENCES `squads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_squad_units_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region_id` VARCHAR(128) NOT NULL,
  `squad_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'active' THEN `user_id` ELSE NULL END
  ) STORED,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_runs_one_active_per_user` (`active_user_id`),
  KEY `ix_runs_user_status_created` (`user_id`, `status`, `created_at`, `id`),
  KEY `ix_runs_squad_status` (`squad_id`, `status`),
  CONSTRAINT `fk_runs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_runs_squad` FOREIGN KEY (`squad_id`) REFERENCES `squads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_nodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `node_index` INT UNSIGNED NOT NULL,
  `node_type_id` VARCHAR(128) NOT NULL,
  `encounter_id` VARCHAR(128) NULL,
  `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'locked',
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `generated_metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_nodes_run_index` (`run_id`, `node_index`),
  UNIQUE KEY `uq_run_nodes_run_id` (`run_id`, `id`),
  CONSTRAINT `fk_run_nodes_run` FOREIGN KEY (`run_id`) REFERENCES `runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_edges` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `from_node_id` BIGINT UNSIGNED NOT NULL,
  `to_node_id` BIGINT UNSIGNED NOT NULL,
  `generated_metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`run_id`, `from_node_id`, `to_node_id`),
  KEY `ix_run_edges_to_node` (`run_id`, `to_node_id`),
  CONSTRAINT `chk_run_edges_distinct_endpoints` CHECK (`from_node_id` <> `to_node_id`),
  CONSTRAINT `fk_run_edges_run` FOREIGN KEY (`run_id`) REFERENCES `runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_run_edges_from_node`
    FOREIGN KEY (`run_id`, `from_node_id`) REFERENCES `run_nodes` (`run_id`, `id`) ON DELETE CASCADE,
  CONSTRAINT `fk_run_edges_to_node`
    FOREIGN KEY (`run_id`, `to_node_id`) REFERENCES `run_nodes` (`run_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_unit_state` (
  `run_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `current_hp` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`run_id`, `unit_id`),
  KEY `ix_run_unit_state_unit_run` (`unit_id`, `run_id`),
  CONSTRAINT `fk_run_unit_state_run` FOREIGN KEY (`run_id`) REFERENCES `runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_run_unit_state_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit_instances` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `user_state`
  ADD CONSTRAINT `fk_user_state_active_squad`
    FOREIGN KEY (`active_squad_id`) REFERENCES `squads` (`id`) ON DELETE SET NULL;
