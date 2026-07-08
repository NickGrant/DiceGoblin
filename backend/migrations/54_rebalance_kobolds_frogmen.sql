UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 3, 'defense', 6, 'max_hp', 28),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'taunting_guard'),
    'passives', JSON_ARRAY('shield_set', 'wall_of_scrap', 'unmoving')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'taunting_guard')
WHERE `slug` = 'kobold_shieldbearer';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 6, 'defense', 2, 'max_hp', 18),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('bomb_toss', 'basic_attack_ranged'),
    'passives', JSON_ARRAY('sharpshooter')
  ),
  `equipped_abilities_json` = JSON_ARRAY('bomb_toss', 'basic_attack_ranged')
WHERE `slug` = 'kobold_skirmisher';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 9, 'defense', 3, 'max_hp', 22),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_ranged', 'disarming_shot', 'aimed_shot'),
    'passives', JSON_ARRAY('sharpshooter', 'clean_shot')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_ranged', 'disarming_shot', 'aimed_shot')
WHERE `slug` = 'kobold_sharpshooter';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 11, 'defense', 4, 'max_hp', 42),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('bomb_toss', 'basic_attack_ranged', 'aimed_shot'),
    'passives', JSON_ARRAY('sharpshooter', 'patient_aim', 'dumb_luck')
  ),
  `equipped_abilities_json` = JSON_ARRAY('bomb_toss', 'basic_attack_ranged', 'aimed_shot')
WHERE `slug` = 'kobold_warchief';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 4, 'defense', 5, 'max_hp', 30),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'bog_splash'),
    'passives', JSON_ARRAY('thick_hide', 'brawl_hardened')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'bog_splash')
WHERE `slug` = 'frogman_bruiser';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 7, 'defense', 3, 'max_hp', 24),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'reed_spear', 'heavy_strike'),
    'passives', JSON_ARRAY('find_the_gap')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'reed_spear', 'heavy_strike')
WHERE `slug` = 'frogman_spearhunter';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 4, 'defense', 4, 'max_hp', 28),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'swamp_holler'),
    'passives', JSON_ARRAY('chant_of_violence', 'morale_goblin')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'swamp_holler')
WHERE `slug` = 'frogman_wardrummer';

UPDATE `enemy_templates`
SET
  `base_stats_json` = JSON_OBJECT('version', 1, 'attack', 8, 'defense', 7, 'max_hp', 54),
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'bog_splash', 'skullcrack'),
    'passives', JSON_ARRAY('thick_hide', 'crowd_favorite')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'bog_splash', 'skullcrack')
WHERE `slug` = 'frogman_bog_tyrant';
