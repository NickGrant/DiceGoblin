INSERT INTO `unit_types` (
  `slug`,
  `name`,
  `role`,
  `base_stats_json`,
  `ability_set_json`,
  `max_level`,
  `attack_per_level`,
  `defense_per_level`,
  `max_hp_per_level`,
  `promotion_level`,
  `promotion_grants_json`,
  `capstone_choices_json`
)
VALUES
  (
    'support_banner_t3',
    'Warchanter',
    'support',
    JSON_OBJECT('version', 1, 'attack', 4, 'defense', 8, 'max_hp', 38, 'precision', 5, 'resolve', 8),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_melee', 'bolster_ally', 'warcry'),
      'passives', JSON_ARRAY('battle_tempo')
    ),
    10,
    1, 2, 3,
    NULL,
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('endless_chant', 'warband_legend'))
  ),
  (
    'control_saboteur_t3',
    'Venomwright',
    'utility',
    JSON_OBJECT('version', 1, 'attack', 8, 'defense', 5, 'max_hp', 32, 'precision', 7, 'resolve', 6),
    JSON_OBJECT(
      'version', 1,
      'actives', JSON_ARRAY('basic_attack_ranged', 'sleep_dart', 'disarming_shot'),
      'passives', JSON_ARRAY('opportunist')
    ),
    10,
    2, 1, 2,
    NULL,
    JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY()),
    JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('plague_mastery', 'cruel_setup'))
  )
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `base_stats_json` = VALUES(`base_stats_json`),
  `ability_set_json` = VALUES(`ability_set_json`),
  `max_level` = VALUES(`max_level`),
  `attack_per_level` = VALUES(`attack_per_level`),
  `defense_per_level` = VALUES(`defense_per_level`),
  `max_hp_per_level` = VALUES(`max_hp_per_level`),
  `promotion_level` = VALUES(`promotion_level`),
  `promotion_grants_json` = VALUES(`promotion_grants_json`),
  `capstone_choices_json` = VALUES(`capstone_choices_json`);

UPDATE `unit_types`
SET
  `promotion_level` = CASE
    WHEN RIGHT(`slug`, 3) = '_t2' THEN 10
    ELSE `promotion_level`
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('unstoppable_heap', 'skullquake'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('fortress_stance', 'last_wall'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('perfect_lane', 'apex_predator'))
    WHEN 'support_banner_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('endless_chant', 'warband_legend'))
    WHEN 'control_saboteur_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('plague_mastery', 'cruel_setup'))
    ELSE `capstone_choices_json`
  END
WHERE RIGHT(`slug`, 3) IN ('_t2', '_t3');
