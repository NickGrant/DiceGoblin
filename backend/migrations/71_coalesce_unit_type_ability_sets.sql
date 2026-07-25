UPDATE `unit_types`
SET `ability_set_json` = CASE `slug`
  WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
  WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
  WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
  WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
  WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
  WHEN 'frontline_pit_fighter_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('desperate_swing'), 'passives', JSON_ARRAY('counterpunch'))
  WHEN 'frontline_shieldbreaker_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('crack_armor'), 'passives', JSON_ARRAY('find_the_gap'))
  WHEN 'backline_trapper_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('mark_target'), 'passives', JSON_ARRAY('treasure_sense'))
  WHEN 'support_mascot_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('lucky_chant'), 'passives', JSON_ARRAY('attention_hog'))
  WHEN 'control_plaguehand_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('poison_cloud'), 'passives', JSON_ARRAY('nerve_toxin'))
  ELSE `ability_set_json`
END
WHERE `promotion_grants_json` IS NOT NULL;

ALTER TABLE `unit_types`
  DROP COLUMN `promotion_grants_json`;
