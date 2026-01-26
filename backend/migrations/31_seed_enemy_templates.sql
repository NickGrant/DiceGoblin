-- Seed initial enemy templates (Milestone 1)
-- Factions: kobolds, frogmen
-- Uses existing AbilityRegistry ability IDs only.

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
    'kobold_skirmisher',
    'Kobold Skirmisher',
    1,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 16),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    10,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'grunt', 'damage_profile', 'ranged')
  ),
  (
    'kobold_shieldbearer',
    'Kobold Shieldbearer',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'shield_up'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'kobold_sharpshooter',
    'Kobold Sharpshooter',
    2,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 9, 'defense', 3, 'max_hp', 22),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    15,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'elite', 'damage_profile', 'ranged')
  ),
  (
    'kobold_warchief',
    'Kobold Warchief',
    3,
    'backline',
    JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 40),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'aimed_shot'),
      'passives', JSON_ARRAY('sharpshooter')
    ),
    30,
    JSON_OBJECT('faction', 'kobolds', 'archetype', 'boss', 'damage_profile', 'ranged')
  ),

  (
    'frogman_bruiser',
    'Frogman Bruiser',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 5, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    10,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'frogman_spearhunter',
    'Frogman Spearhunter',
    1,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 4, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY()
    ),
    10,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'grunt', 'damage_profile', 'melee')
  ),
  (
    'frogman_wardrummer',
    'Frogman Wardrummer',
    2,
    'support',
    JSON_OBJECT('version', 1, 'attack', 3, 'defense', 5, 'max_hp', 26),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally'),
      'passives', JSON_ARRAY()
    ),
    15,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'elite', 'utility', 'buff')
  ),
  (
    'frogman_bog_tyrant',
    'Bog Tyrant',
    3,
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 7, 'max_hp', 50),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'heavy_strike'),
      'passives', JSON_ARRAY('thick_hide')
    ),
    30,
    JSON_OBJECT('faction', 'frogmen', 'archetype', 'boss', 'damage_profile', 'melee', 'theme', 'attrition')
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `tier` = VALUES(`tier`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `xp_reward` = VALUES(`xp_reward`),
  `tags_json` = VALUES(`tags_json`);
