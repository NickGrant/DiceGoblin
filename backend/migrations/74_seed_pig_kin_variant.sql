INSERT INTO `splice_variants` (
  `slug`,
  `name`,
  `description`,
  `stat_modifiers_json`,
  `passive_summary`,
  `grant_weight`,
  `is_enabled`
)
VALUES (
  'pig_kin',
  'Pig Kin',
  'Stubborn farmyard goblin-kin with a thicker hide and a slightly slower hand.',
  JSON_OBJECT('attack', 0, 'defense', 1, 'max_hp', 2, 'precision', -1, 'resolve', 1),
  '+1 Defense, +2 HP, +1 Resolve, -1 Precision.',
  12,
  1
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `stat_modifiers_json` = VALUES(`stat_modifiers_json`),
  `passive_summary` = VALUES(`passive_summary`),
  `grant_weight` = VALUES(`grant_weight`),
  `is_enabled` = VALUES(`is_enabled`);
