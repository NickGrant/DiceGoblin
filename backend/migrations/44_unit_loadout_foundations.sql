ALTER TABLE `unit_instances`
  ADD COLUMN `display_name` VARCHAR(80) NULL AFTER `unit_type_id`;

CREATE TABLE `unit_instance_unlocked_abilities` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `source_tier` INT NOT NULL DEFAULT 1,
  `source_unit_type_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `ability_id`),
  KEY `ix_unit_instance_unlocked_abilities_source_unit_type_id` (`source_unit_type_id`),
  CONSTRAINT `fk_unit_instance_unlocked_abilities_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_instance_unlocked_abilities_source_unit_type_id`
    FOREIGN KEY (`source_unit_type_id`) REFERENCES `unit_types`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_instance_equipped_abilities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `equip_order` INT NOT NULL,
  `speed_cost` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_instance_equipped_abilities_unit_order` (`unit_instance_id`, `equip_order`),
  KEY `ix_unit_instance_equipped_abilities_unit_ability` (`unit_instance_id`, `ability_id`),
  CONSTRAINT `fk_unit_instance_equipped_abilities_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_ability_dice` (
  `unit_instance_id` BIGINT UNSIGNED NOT NULL,
  `ability_id` VARCHAR(64) NOT NULL,
  `slot_index` INT NOT NULL,
  `dice_instance_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_instance_id`, `ability_id`, `slot_index`),
  UNIQUE KEY `uq_unit_ability_dice_dice_instance_id` (`dice_instance_id`),
  CONSTRAINT `fk_unit_ability_dice_unit_instance_id`
    FOREIGN KEY (`unit_instance_id`) REFERENCES `unit_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_unit_ability_dice_dice_instance_id`
    FOREIGN KEY (`dice_instance_id`) REFERENCES `dice_instances`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `enemy_templates`
  ADD COLUMN `equipped_abilities_json` JSON NULL AFTER `ability_set_json`;
