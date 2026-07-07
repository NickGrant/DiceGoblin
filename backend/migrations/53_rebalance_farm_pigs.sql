UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'wrestle'),
    'passives', JSON_ARRAY()
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'wrestle')
WHERE `slug` = 'mudwrestler';

UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_ranged', 'mud_sling'),
    'passives', JSON_ARRAY()
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_ranged', 'mud_sling')
WHERE `slug` = 'mudslinger';

UPDATE `enemy_templates`
SET
  `ability_set_json` = JSON_OBJECT(
    'version', 1,
    'actives', JSON_ARRAY('basic_attack_melee', 'wrestle', 'mud_slam'),
    'passives', JSON_ARRAY('thick_hide')
  ),
  `equipped_abilities_json` = JSON_ARRAY('basic_attack_melee', 'wrestle', 'mud_slam')
WHERE `slug` = 'mudking';
