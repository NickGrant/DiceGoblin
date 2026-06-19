UPDATE `unit_types`
SET
  `max_level` = 10,
  `promotion_level` = CASE
    WHEN `slug` LIKE '%_t3' THEN NULL
    ELSE 6
  END,
  `promotion_grants_json` = CASE `slug`
    WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('skullcrack'), 'passives', JSON_ARRAY('menacing_follow_through'))
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('taunting_guard'), 'passives', JSON_ARRAY('shield_set'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('piercing_shot'), 'passives', JSON_ARRAY('vantage_point'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
    WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('warcry'), 'passives', JSON_ARRAY('battle_tempo'))
    WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'actives', JSON_ARRAY('disarming_shot'), 'passives', JSON_ARRAY('opportunist'))
    ELSE JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())
  END,
  `capstone_choices_json` = CASE `slug`
    WHEN 'frontline_bruiser_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('brawl_hardened', 'finisher'))
    WHEN 'frontline_bruiser_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('no_mercy', 'brutal_suppression'))
    WHEN 'frontline_bruiser_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'frontline_guardian_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('bodyguard', 'hold_the_line'))
    WHEN 'frontline_guardian_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('unmoving', 'wall_of_scrap'))
    WHEN 'frontline_guardian_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'backline_marksman_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('patient_aim', 'pick_your_mark'))
    WHEN 'backline_marksman_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('kill_lane', 'armor_gap'))
    WHEN 'backline_marksman_t3' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
    WHEN 'support_banner_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('rally_rhythm', 'patch_job'))
    WHEN 'support_banner_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('chant_of_violence', 'mob_mentality'))
    WHEN 'control_saboteur_t1' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('toxic_tools', 'spiteful_reflex'))
    WHEN 'control_saboteur_t2' THEN JSON_OBJECT('version', 1, 'choices', JSON_ARRAY('disabling_hit', 'clean_shot'))
    ELSE JSON_OBJECT('version', 1, 'choices', JSON_ARRAY())
  END;
