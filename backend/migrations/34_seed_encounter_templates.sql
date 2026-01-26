-- Seed encounter templates (Milestone 1)
-- Position-aware enemy layout schema:
-- enemy_set_json.version = 2
-- enemy_set_json.teams[] = { team_id, label, units[] }
-- units[] = { enemy_template_slug, pos: { x, y } }
--
-- Regions assumed to exist with slugs: 'mountains', 'swamps'

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  -- =========================
  -- MOUNTAINS (Kobolds)
  -- =========================

  (
    'mountains_kobold_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    1,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_combat_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    2,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_combat_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    3,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Warband',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    5,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Kobold Command',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'kobold_warchief',     'pos', JSON_OBJECT('x', 2, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_boss_loot', 'rolls', 1)
  ),

  -- =========================
  -- SWAMPS (Frogmen)
  -- =========================

  (
    'swamps_frogman_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    1,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_combat_2',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    2,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_combat_3',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    3,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Frogman Hunting Party',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    5,
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Bog Court',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'frogman_bog_tyrant', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 0)),
            JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',    'pos', JSON_OBJECT('x', 0, 'y', 2)),
            JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_boss_loot', 'rolls', 1)
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);
