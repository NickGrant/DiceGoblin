ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue') NOT NULL;

INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('mystic_cave', 'Mystic Cave', 'mystic_cave', 1, 0, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);
