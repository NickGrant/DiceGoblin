INSERT INTO `dice_definitions` (
  `sides`,
  `rarity`,
  `slot_capacity`
)
VALUES
  (4,  'common',   0),
  (6,  'common',   0),
  (8,  'common',   0),
  (10, 'common',   0),

  (4,  'uncommon', 1),
  (6,  'uncommon', 1),
  (8,  'uncommon', 1),
  (10, 'uncommon', 1),

  (4,  'rare',     2),
  (6,  'rare',     2),
  (8,  'rare',     2),
  (10, 'rare',     2)
ON DUPLICATE KEY UPDATE
  `slot_capacity` = VALUES(`slot_capacity`);
