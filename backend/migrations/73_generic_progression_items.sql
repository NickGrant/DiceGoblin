CREATE TABLE IF NOT EXISTS `items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(48) NOT NULL,
  `source_region_id` BIGINT UNSIGNED NULL,
  `source_family_slug` VARCHAR(64) NULL,
  `rarity` VARCHAR(32) NOT NULL DEFAULT 'common',
  `is_stackable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_visible_before_discovery` TINYINT(1) NOT NULL DEFAULT 0,
  `is_spendable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_primary_progression` TINYINT(1) NOT NULL DEFAULT 0,
  `icon_key` VARCHAR(96) NULL,
  `lore_key` VARCHAR(96) NULL,
  `meta_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_slug` (`slug`),
  KEY `ix_items_category` (`category`, `rarity`, `slug`),
  KEY `ix_items_source_region` (`source_region_id`, `category`),
  CONSTRAINT `fk_items_source_region_id` FOREIGN KEY (`source_region_id`) REFERENCES `regions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_items` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `first_acquired_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `item_id`),
  KEY `ix_user_items_item_id` (`item_id`),
  CONSTRAINT `fk_user_items_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
SELECT
  'pig_ear',
  'Pig Ear',
  'A stubborn scrap of pig-kin possibility. The Wrong Machine will know what to do with it.',
  'lineage_material',
  r.`id`,
  'pigs',
  'common',
  1,
  1,
  1,
  1,
  'item_pig_ear',
  'pig_kin_material',
  JSON_OBJECT('lineage_slug', 'pig_kin', 'tutorial_material', true)
FROM `regions` r
WHERE r.`slug` = 'the_farm'
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);

INSERT INTO `items` (
  `slug`,
  `name`,
  `description`,
  `category`,
  `source_region_id`,
  `source_family_slug`,
  `rarity`,
  `is_stackable`,
  `is_visible_before_discovery`,
  `is_spendable`,
  `is_primary_progression`,
  `icon_key`,
  `lore_key`,
  `meta_json`
)
SELECT
  'mudking_crown_fragment',
  'Mudking Crown Fragment',
  'A boss-won catalyst heavy with farmyard authority.',
  'boss_catalyst',
  r.`id`,
  'pigs',
  'rare',
  1,
  0,
  1,
  1,
  'item_mudking_crown_fragment',
  'mudking_catalyst',
  JSON_OBJECT('lineage_slug', 'pig_kin', 'boss_catalyst', true)
FROM `regions` r
WHERE r.`slug` = 'the_farm'
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `source_region_id` = VALUES(`source_region_id`),
  `source_family_slug` = VALUES(`source_family_slug`),
  `rarity` = VALUES(`rarity`),
  `is_stackable` = VALUES(`is_stackable`),
  `is_visible_before_discovery` = VALUES(`is_visible_before_discovery`),
  `is_spendable` = VALUES(`is_spendable`),
  `is_primary_progression` = VALUES(`is_primary_progression`),
  `icon_key` = VALUES(`icon_key`),
  `lore_key` = VALUES(`lore_key`),
  `meta_json` = VALUES(`meta_json`);
