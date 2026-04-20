UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(
  COALESCE(`base_stats_json`, JSON_OBJECT()),
  '$.formation',
  JSON_OBJECT('w', 2, 'h', 2)
)
WHERE JSON_UNQUOTE(JSON_EXTRACT(`tags_json`, '$.archetype')) = 'boss';
