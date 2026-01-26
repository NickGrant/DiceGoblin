INSERT INTO `unit_types` (
  `slug`,
  `name`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `max_level`,
  `attack_per_level`,
  `defense_per_level`,
  `max_hp_per_level`
)
VALUES
  (
    'frontline_bruiser_t1',
    'Bruiser',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 3, 'max_hp', 22),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    6,
    1, 1, 2
  ),
  (
    'frontline_bruiser_t2',
    'Enforcer',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 7, 'defense', 5, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    1, 1, 3
  ),
  (
    'frontline_bruiser_t3',
    'Juggernaut',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 9, 'defense', 7, 'max_hp', 40),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    14,
    1, 2, 4
  ),

  (
    'frontline_guardian_t1',
    'Guardian',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 5, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    6,
    1, 2, 2
  ),
  (
    'frontline_guardian_t2',
    'Bulwark',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 7, 'max_hp', 32),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    1, 2, 3
  ),
  (
    'frontline_guardian_t3',
    'Ironwall',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 10, 'max_hp', 44),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    14,
    1, 3, 3
  ),

  (
    'backline_marksman_t1',
    'Marksman',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 18),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    6,
    2, 1, 2
  ),
  (
    'backline_marksman_t2',
    'Deadeye',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 3, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    10,
    2, 1, 2
  ),
  (
    'backline_marksman_t3',
    'Sharpshot',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 32),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    14,
    3, 1, 2
  ),

  (
    'support_banner_t1',
    'Bannerbearer',
    'support',
    JSON_OBJECT('version', 1, 'attack', 2, 'defense', 4, 'max_hp', 20),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    8,
    1, 2, 2
  ),
  (
    'support_banner_t2',
    'Warcaller',
    'support',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    12,
    1, 2, 3
  ),

  (
    'control_saboteur_t1',
    'Saboteur',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 3, 'max_hp', 18),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart'),
      'passives', JSON_ARRAY()
    ),
    8,
    2, 1, 2
  ),
  (
    'control_saboteur_t2',
    'Trickshot',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 4, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart'),
      'passives', JSON_ARRAY()
    ),
    12,
    2, 1, 3
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `max_level` = VALUES(`max_level`),
  `attack_per_level` = VALUES(`attack_per_level`),
  `defense_per_level` = VALUES(`defense_per_level`),
  `max_hp_per_level` = VALUES(`max_hp_per_level`);
