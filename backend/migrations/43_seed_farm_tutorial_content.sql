INSERT INTO `regions` (
  `slug`,
  `name`,
  `theme`,
  `recommended_level`,
  `energy_cost`,
  `is_enabled`
)
VALUES
  ('the_farm', 'The Farm', 'farm', 1, 3, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `theme` = VALUES(`theme`),
  `recommended_level` = VALUES(`recommended_level`),
  `energy_cost` = VALUES(`energy_cost`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `enemy_templates` (
  `slug`,
  `name`,
  `tier`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `xp_reward`,
  `tags_json`
)
VALUES
  (
    'mudwrestler',
    'Mudwrestler',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 2, 'max_hp', 16),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee'), 'passives', JSON_ARRAY()),
    8,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'mudslinger',
    'Mudslinger',
    1,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 1, 'max_hp', 14),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_ranged'), 'passives', JSON_ARRAY()),
    8,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'grunt', 'damage_profile', 'ranged')
  ),
  (
    'mudking',
    'Mudking',
    2,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 4, 'max_hp', 30),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'), 'passives', JSON_ARRAY('thick_hide')),
    16,
    JSON_OBJECT('faction', 'pigs', 'archetype', 'boss', 'damage_profile', 'melee')
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tier` = VALUES(`tier`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `xp_reward` = VALUES(`xp_reward`),
  `tags_json` = VALUES(`tags_json`);

INSERT INTO `encounter_templates` (
  `slug`,
  `region_id`,
  `difficulty_rating`,
  `description`,
  `enemy_set_json`,
  `reward_profile_json`
)
VALUES
  (
    'the_farm_mud_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'A pair of pigs lurches out of the muck, giving the warband its first real skirmish.',
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Pigs',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'mudwrestler', 'pos', JSON_OBJECT('x', 2, 'y', 1)),
            JSON_OBJECT('enemy_template_slug', 'mudslinger', 'pos', JSON_OBJECT('x', 0, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'the_farm_loot_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'A crate of feed and spare gear sits untouched beside the fence line.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'loot', 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'the_farm_rest_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    1,
    'The warband catches its breath at a dry patch of hay before the final push.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'type', 'rest')
  ),
  (
    'the_farm_mud_boss_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'the_farm' LIMIT 1),
    2,
    'The Mudking snorts, stamps, and charges to defend the whole sty.',
    JSON_OBJECT(
      'version', 2,
      'teams', JSON_ARRAY(
        JSON_OBJECT(
          'team_id', 'A',
          'label', 'Pigs',
          'units', JSON_ARRAY(
            JSON_OBJECT('enemy_template_slug', 'mudking', 'pos', JSON_OBJECT('x', 2, 'y', 1))
          )
        )
      )
    ),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_boss_loot', 'rolls', 1)
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `description` = VALUES(`description`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);
