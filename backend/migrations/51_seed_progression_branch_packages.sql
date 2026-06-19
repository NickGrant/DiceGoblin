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
    'frontline_pit_fighter_t2',
    'Pit Fighter',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 4, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('desperate_swing'),
      'passives', JSON_ARRAY('counterpunch')
    ),
    10,
    2, 1, 3
  ),
  (
    'frontline_shieldbreaker_t2',
    'Shieldbreaker',
    'frontline',
    JSON_OBJECT('version', 1, 'attack', 6, 'defense', 6, 'max_hp', 30),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('crack_armor'),
      'passives', JSON_ARRAY('find_the_gap')
    ),
    10,
    1, 2, 3
  ),
  (
    'backline_trapper_t2',
    'Trapper',
    'backline',
    JSON_OBJECT('version', 1, 'attack', 7, 'defense', 3, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('mark_target'),
      'passives', JSON_ARRAY('treasure_sense')
    ),
    10,
    2, 1, 2
  ),
  (
    'support_mascot_t2',
    'Mascot',
    'support',
    JSON_OBJECT('version', 1, 'attack', 2, 'defense', 5, 'max_hp', 28),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('lucky_chant'),
      'passives', JSON_ARRAY('attention_hog')
    ),
    10,
    1, 2, 3
  ),
  (
    'control_plaguehand_t2',
    'Plaguehand',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 5, 'defense', 4, 'max_hp', 24),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('poison_cloud'),
      'passives', JSON_ARRAY('nerve_toxin')
    ),
    10,
    2, 1, 2
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

UPDATE `unit_types`
SET `ability_set_json` = CASE `slug`
  WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
  WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
  WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
  WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
  WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
  ELSE `ability_set_json`
END
WHERE `slug` IN (
  'frontline_bruiser_t2',
  'frontline_guardian_t2',
  'backline_marksman_t2',
  'support_banner_t2',
  'control_saboteur_t2'
);

UPDATE `unit_types`
SET
  `promotion_level` = CASE
    WHEN RIGHT(`slug`, 3) = '_t2' THEN 6
    ELSE `promotion_level`
  END,
  `promotion_grants_json` = CASE `slug`
    WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('desperate_swing'), 'passives', JSON_ARRAY('counterpunch'))
    WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('crack_armor'), 'passives', JSON_ARRAY('find_the_gap'))
    WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('mark_target'), 'passives', JSON_ARRAY('treasure_sense'))
    WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('lucky_chant'), 'passives', JSON_ARRAY('attention_hog'))
    WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('poison_cloud'), 'passives', JSON_ARRAY('nerve_toxin'))
    ELSE `promotion_grants_json`
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('last_goblin_standing', 'crowd_favorite'))
    WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('shatter_plate', 'break_open'))
    WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('exposed_weaknesses', 'barbed_mark'))
    WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('dumb_luck', 'morale_goblin'))
    WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('lingering_cloud', 'sickly_weakness'))
    ELSE `capstone_choices_json`
  END
WHERE `slug` IN (
  'frontline_pit_fighter_t2',
  'frontline_shieldbreaker_t2',
  'backline_trapper_t2',
  'support_mascot_t2',
  'control_plaguehand_t2'
);
