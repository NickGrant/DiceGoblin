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
    'chaos_treasure_scavenger',
    'Chaos Treasure Scavenger',
    1,
    'support',
    JSON_OBJECT('version', 1, 'attack', 1, 'defense', 0, 'max_hp', 6, 'precision', 1, 'resolve', 1),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee'), 'passives', JSON_ARRAY()),
    2,
    JSON_OBJECT('faction', 'chaos', 'archetype', 'treasure', 'damage_profile', 'melee')
  ),
  (
    'chaos_faultbrute',
    'Chaos Faultbrute',
    3,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 10, 'defense', 8, 'max_hp', 58, 'precision', 5, 'resolve', 7),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'), 'passives', JSON_ARRAY('thick_hide')),
    35,
    JSON_OBJECT('faction', 'chaos', 'archetype', 'elite', 'damage_profile', 'melee')
  ),
  (
    'chaos_glass_cannon',
    'Chaos Glass Cannon',
    3,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 14, 'defense', 1, 'max_hp', 24, 'precision', 8, 'resolve', 4),
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'), 'passives', JSON_ARRAY('sharpshooter')),
    35,
    JSON_OBJECT('faction', 'chaos', 'archetype', 'elite', 'damage_profile', 'ranged')
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
    'chaos_treasure_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mystic_cave' LIMIT 1),
    1,
    'A barely hostile treasure crew tumbles out of the Wrong Machine wake.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY(JSON_OBJECT('team_id', 'A', 'label', 'Treasure Crew', 'units', JSON_ARRAY(
      JSON_OBJECT('enemy_template_slug', 'chaos_treasure_scavenger', 'pos', JSON_OBJECT('x', 2, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'chaos_treasure_scavenger', 'pos', JSON_OBJECT('x', 2, 'y', 2))
    )))),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_chaos_elite_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    5,
    'A mountain fault opens into a machine-made elite formation.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY(JSON_OBJECT('team_id', 'A', 'label', 'Faultline Elite', 'units', JSON_ARRAY(
      JSON_OBJECT('enemy_template_slug', 'chaos_faultbrute', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
      JSON_OBJECT('enemy_template_slug', 'chaos_glass_cannon', 'pos', JSON_OBJECT('x', 2, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 2))
    )))),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_chaos_elite_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    5,
    'The swamp buckles around a machine-made elite formation.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY(JSON_OBJECT('team_id', 'A', 'label', 'Mire Fault Elite', 'units', JSON_ARRAY(
      JSON_OBJECT('enemy_template_slug', 'chaos_faultbrute', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
      JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'chaos_glass_cannon', 'pos', JSON_OBJECT('x', 2, 'y', 2))
    )))),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  ),
  (
    'mountains_kobold_chaos_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'mountains' LIMIT 1),
    4,
    'A chaos-skewed kobold formation spills across the mountain route.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY(JSON_OBJECT('team_id', 'A', 'label', 'Kobold Chaos Band', 'units', JSON_ARRAY(
      JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'kobold_warchief', 'pos', JSON_OBJECT('x', 1, 'y', 1)),
      JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher', 'pos', JSON_OBJECT('x', 2, 'y', 2))
    )))),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'kobold_basic_loot', 'rolls', 1)
  ),
  (
    'swamps_frogman_chaos_combat_1',
    (SELECT `id` FROM `regions` WHERE `slug` = 'swamps' LIMIT 1),
    4,
    'A chaos-skewed frogman formation pulls the route into the muck.',
    JSON_OBJECT('version', 2, 'teams', JSON_ARRAY(JSON_OBJECT('team_id', 'A', 'label', 'Frogman Chaos Court', 'units', JSON_ARRAY(
      JSON_OBJECT('enemy_template_slug', 'frogman_bog_tyrant', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
      JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
      JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer', 'pos', JSON_OBJECT('x', 1, 'y', 2)),
      JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 2, 'y', 1))
    )))),
    JSON_OBJECT('version', 1, 'loot_table_slug', 'frogman_basic_loot', 'rolls', 1)
  )
ON DUPLICATE KEY UPDATE
  `region_id` = VALUES(`region_id`),
  `difficulty_rating` = VALUES(`difficulty_rating`),
  `description` = VALUES(`description`),
  `enemy_set_json` = VALUES(`enemy_set_json`),
  `reward_profile_json` = VALUES(`reward_profile_json`);
