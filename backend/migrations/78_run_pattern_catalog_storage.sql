CREATE TABLE `run_pattern_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `status` ENUM('draft','enabled','disabled') NOT NULL DEFAULT 'draft',
  `definition_json` JSON NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_pattern_definitions_slug_version` (`slug`, `version`),
  UNIQUE KEY `uq_run_pattern_definitions_content_hash` (`content_hash`),
  KEY `ix_run_pattern_definitions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_pattern_region_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern_definition_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `generator_version` VARCHAR(32) NOT NULL,
  `base_weight` INT UNSIGNED NOT NULL,
  `allowed_phase` ENUM('start','spine','branch','cap','boss_approach','terminal') NOT NULL,
  `min_depth` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_depth` INT UNSIGNED NULL,
  `max_per_run` INT UNSIGNED NULL,
  `cooldown_patterns` INT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `weight_modifiers_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_pattern_region_rules` (`pattern_definition_id`, `region_id`, `generator_version`, `allowed_phase`, `min_depth`),
  KEY `ix_run_pattern_region_rules_region_phase` (`region_id`, `generator_version`, `allowed_phase`, `enabled`),
  CONSTRAINT `fk_run_pattern_region_rules_pattern`
    FOREIGN KEY (`pattern_definition_id`) REFERENCES `run_pattern_definitions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_run_pattern_region_rules_region`
    FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `run_generation_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `generator_version` VARCHAR(32) NOT NULL,
  `profile_version` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `bounds_json` JSON NOT NULL,
  `budgets_json` JSON NOT NULL,
  `requirements_json` JSON NOT NULL,
  `retry_policy_json` JSON NOT NULL,
  `weight_policy_json` JSON NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_generation_profiles_region_version` (`region_id`, `generator_version`, `profile_version`),
  UNIQUE KEY `uq_run_generation_profiles_content_hash` (`content_hash`),
  KEY `ix_run_generation_profiles_enabled` (`region_id`, `generator_version`, `enabled`),
  CONSTRAINT `fk_run_generation_profiles_region`
    FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `region_runs`
  ADD COLUMN `generator_version` VARCHAR(32) NULL AFTER `seed`,
  ADD COLUMN `generation_profile_version` INT UNSIGNED NULL AFTER `generator_version`,
  ADD COLUMN `pattern_catalog_hash` CHAR(64) NULL AFTER `generation_profile_version`,
  ADD COLUMN `generation_attempt` INT UNSIGNED NULL AFTER `pattern_catalog_hash`,
  ADD COLUMN `generation_summary_json` JSON NULL AFTER `generation_attempt`,
  ADD KEY `ix_region_runs_generator_version` (`region_id`, `generator_version`);
