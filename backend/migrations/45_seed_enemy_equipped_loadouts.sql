UPDATE `enemy_templates`
SET `equipped_abilities_json` = JSON_OBJECT(
  'version', 1,
  'equipped', COALESCE(JSON_EXTRACT(`ability_set_json`, '$.actives'), JSON_ARRAY())
)
WHERE `equipped_abilities_json` IS NULL;
