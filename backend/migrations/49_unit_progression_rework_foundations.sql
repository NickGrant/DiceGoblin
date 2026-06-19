ALTER TABLE `unit_types`
  ADD COLUMN `promotion_level` INT NULL AFTER `max_level`,
  ADD COLUMN `promotion_grants_json` JSON NULL AFTER `ability_set_json`,
  ADD COLUMN `capstone_choices_json` JSON NULL AFTER `promotion_grants_json`;

CREATE TABLE `unit_instance_capstone_choices` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `source_unit_type_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `source_unit_type_id`),
  KEY `ix_unit_instance_capstone_choices_source_unit_type_id` (`source_unit_type_id`),
  CONSTRAINT `fk_unit_instance_capstone_choices_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_instance_capstone_choices_source_unit_type_id`
    FOREIGN KEY (`source_unit_type_id`) REFERENCES `unit_types`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
