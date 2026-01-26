-- Seed region items (Milestone 1)
-- Ensures required regions exist: mountains, swamps
-- Region items:
-- - mountains: roc_egg
-- - swamps: gator_head

-- 1) Ensure regions exist (FK dependency for region_items.region_id)
INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('mountains', 'Mountains', 'mountain', 1, 5, 1),
  ('swamps',    'Swamps',    'swamp',    1, 5, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);

-- 2) Seed region items (boss-drop items)
INSERT INTO `region_items` (
  `region_id`,
  `slug`,
  `name`
)
VALUES
  (
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    'roc_egg',
    'Roc Egg'
  ),
  (
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    'gator_head',
    'Gator Head'
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `name` = VALUES(`name`);
