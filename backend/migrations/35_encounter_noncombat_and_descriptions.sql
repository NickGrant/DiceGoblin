-- Add encounter description field + seed non-combat encounters (Milestone 1)
-- Compatible with MySQL versions that do NOT support: ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...

-- 1) Conditionally add player-facing description text (idempotent)
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'encounter_templates'
    AND COLUMN_NAME = 'description'
);

SET @ddl := IF(
  @col_exists = 0,
  'ALTER TABLE `encounter_templates` ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '''' AFTER `difficulty_rating`;',
  'SELECT 1;'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Backfill descriptions for existing encounters (from 34_seed_encounter_templates.sql)
UPDATE `encounter_templates`
SET `description` = CASE
  WHEN `slug` LIKE 'mountains\\_kobold\\_boss\\_%' THEN
    'A warhorn screams through the crags. The kobold command has taken the field.'
  WHEN `slug` LIKE 'mountains\\_kobold\\_combat\\_%' THEN
    'Loose stones shift underfoot as a kobold warband scrambles into position.'
  WHEN `slug` LIKE 'swamps\\_frogman\\_boss\\_%' THEN
    'The swamp goes still. Something immense rises from the black water.'
  WHEN `slug` LIKE 'swamps\\_frogman\\_combat\\_%' THEN
    'Wet reeds part and frogmen emerge—quiet, patient, and hard to kill.'
  ELSE `description`
END
WHERE `description` = '';

-- 3) Seed non-combat encounters: 3 loot + 2 rest per biome
-- Uses enemy_set_json v2 with empty teams.

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `description`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  -- MOUNTAINS (Kobolds) — LOOT x3
  (
    'mountains_kobold_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    'Before you lies a pile of bones and scraps. Underneath, something glints—salvage worth keeping.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_loot_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    'A collapsed supply crate is wedged between rocks. Most of it is ruined, but not all.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_loot_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    3,
    'You find a scorched campsite and a half-buried satchel. Whatever happened here, it ended fast.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),

  -- MOUNTAINS (Kobolds) — REST x2
  (
    'mountains_kobold_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    'A sheltered ledge offers a moment to breathe. You patch gear and steady your hands.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),
  (
    'mountains_kobold_rest_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    'Warm air rises from a crack in the stone. It is not safe, but it is quiet—for now.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),

  -- SWAMPS (Frogmen) — LOOT x3
  (
    'swamps_frogman_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    'A waterlogged bundle hangs from a dead branch. Inside: salvage, wrapped tight against the muck.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_loot_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    'You pry open a half-sunk chest. The hinges scream, but the contents are still usable.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_loot_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    3,
    'Something is tangled in the reeds—gear left behind in a hurry. You take what you can.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),

  -- SWAMPS (Frogmen) — REST x2
  (
    'swamps_frogman_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    'You find a dry patch of ground and hold still long enough to recover. The swamp watches.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  ),
  (
    'swamps_frogman_rest_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    'A ring of standing stones breaks the wind and the insects. You rest, but do not sleep.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest', 'effect', 'recover')
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `description` = VALUES(`description`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);
