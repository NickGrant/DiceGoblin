CREATE TABLE IF NOT EXISTS `splice_variants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NOT NULL,
  `stat_modifiers_json` JSON NOT NULL,
  `passive_summary` VARCHAR(255) NOT NULL DEFAULT '',
  `grant_weight` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_splice_variants_slug` (`slug`),
  KEY `ix_splice_variants_enabled_weight` (`is_enabled`, `grant_weight`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `splice_variants` (
  `slug`,
  `name`,
  `description`,
  `stat_modifiers_json`,
  `passive_summary`,
  `grant_weight`,
  `is_enabled`
)
VALUES
  (
    'basic_goblin',
    'Basic Goblin',
    'Baseline goblin stock with no splice tendency.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 0, 'precision', 0, 'resolve', 0),
    'No splice modifier.',
    60,
    1
  ),
  (
    'rat_splice',
    'Rat-Spliced',
    'Nimble scavenger instincts favor landing messy hits over standing firm.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 0, 'precision', 1, 'resolve', -1),
    '+1 Precision, -1 Resolve.',
    15,
    1
  ),
  (
    'toad_splice',
    'Toad-Spliced',
    'Stubborn bog-blooded bulk improves resistance at the cost of aim.',
    JSON_OBJECT('attack', 0, 'defense', 0, 'max_hp', 2, 'precision', -1, 'resolve', 1),
    '+2 HP, +1 Resolve, -1 Precision.',
    15,
    1
  ),
  (
    'bat_splice',
    'Bat-Spliced',
    'Echo-sharp senses improve careful strikes but leave the body frailer.',
    JSON_OBJECT('attack', 1, 'defense', 0, 'max_hp', -1, 'precision', 1, 'resolve', 0),
    '+1 Attack, +1 Precision, -1 HP.',
    10,
    1
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `stat_modifiers_json` = VALUES(`stat_modifiers_json`),
  `passive_summary` = VALUES(`passive_summary`),
  `grant_weight` = VALUES(`grant_weight`),
  `is_enabled` = VALUES(`is_enabled`);
