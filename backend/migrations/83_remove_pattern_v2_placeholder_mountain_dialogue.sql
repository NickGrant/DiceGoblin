SET @v2_mountain_start = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_start_cluster',
  'version', 1,
  'status', 'enabled',
  'width', 3,
  'height', 3,
  'cost', 4,
  'tags', JSON_ARRAY('start', 'mountain'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'easy_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('key', 'minor_loot', 'type', 'loot', 'quality', 'minor')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'poor_shrine', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'start_node', 'type', 'combat', 'difficulty', 'easy', 'role', 'start'), JSON_OBJECT('type', 'connector'), NULL)
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'start_node', 'to', 'poor_shrine', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'poor_shrine', 'to', 'easy_combat'),
    JSON_OBJECT('from', 'easy_combat', 'to', 'minor_loot')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 2, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 1, 'direction', 'down')
  )
);

UPDATE `run_pattern_definitions`
SET
  `definition_json` = @v2_mountain_start,
  `content_hash` = SHA2(CAST(@v2_mountain_start AS CHAR), 256)
WHERE `slug` = 'v2_mountain_start_cluster'
  AND `version` = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.schema_version')) = 'pattern-v2';
