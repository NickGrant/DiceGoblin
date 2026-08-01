CREATE TABLE IF NOT EXISTS `user_codex_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `entry_type` VARCHAR(32) NOT NULL,
  `entry_key` VARCHAR(128) NOT NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'unknown',
  `metadata_json` JSON NULL,
  `discovered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_codex_entries_user_type_key` (`user_id`, `entry_type`, `entry_key`),
  KEY `ix_user_codex_entries_user_type` (`user_id`, `entry_type`),
  CONSTRAINT `fk_user_codex_entries_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT `user_id`, 'feature', `unlock_key`, 'feature_unlock', `created_at`
FROM `user_unlocks`
WHERE `unlock_namespace` = 'feature';

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT `user_id`, 'unit_type', `unlock_key`, 'unit_type_unlock', `created_at`
FROM `user_unlocks`
WHERE `unlock_namespace` = 'unit_type';

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT ui.`user_id`, 'unit_type', ut.`slug`, 'owned_unit', MIN(ui.`created_at`)
FROM `unit_instances` ui
JOIN `unit_types` ut ON ut.`id` = ui.`unit_type_id`
GROUP BY ui.`user_id`, ut.`slug`;

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT `user_id`, 'kin', `unlock_key`, 'lineage_unlock', `created_at`
FROM `user_unlocks`
WHERE `unlock_namespace` = 'lineage';

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT `user_id`, 'kin', `splice_variant_slug`, 'owned_unit', MIN(`created_at`)
FROM `unit_instances`
WHERE `splice_variant_slug` IS NOT NULL AND `splice_variant_slug` <> ''
GROUP BY `user_id`, `splice_variant_slug`;

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT `user_id`, 'lore', `unlock_key`, 'dialogue', `created_at`
FROM `user_unlocks`
WHERE `unlock_namespace` = 'dialogue';

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT rr.`user_id`, 'biome', r.`slug`, 'completed_run', MIN(COALESCE(rr.`ended_at`, rr.`updated_at`, rr.`created_at`))
FROM `region_runs` rr
JOIN `regions` r ON r.`id` = rr.`region_id`
WHERE rr.`status` = 'completed'
GROUP BY rr.`user_id`, r.`slug`;

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT di.`user_id`, 'affix', ad.`slug`, 'owned_die', MIN(di.`created_at`)
FROM `dice_instances` di
JOIN `dice_instance_affixes` dia ON dia.`dice_instance_id` = di.`id`
JOIN `affix_definitions` ad ON ad.`id` = dia.`affix_definition_id`
GROUP BY di.`user_id`, ad.`slug`;

INSERT IGNORE INTO `user_codex_entries` (`user_id`, `entry_type`, `entry_key`, `source`, `discovered_at`)
SELECT ui.`user_id`, 'item', i.`slug`, 'owned_item', ui.`first_acquired_at`
FROM `user_items` ui
JOIN `items` i ON i.`id` = ui.`item_id`
WHERE ui.`quantity` > 0;
