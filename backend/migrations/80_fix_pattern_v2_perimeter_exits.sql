UPDATE `run_pattern_definitions`
SET
  `definition_json` = JSON_SET(
    `definition_json`,
    '$.exits[1]',
    JSON_OBJECT('row', 2, 'col', 1, 'direction', 'down')
  ),
  `content_hash` = SHA2(CAST(JSON_SET(
    `definition_json`,
    '$.exits[1]',
    JSON_OBJECT('row', 2, 'col', 1, 'direction', 'down')
  ) AS CHAR), 256)
WHERE `slug` = 'v2_mountain_start_cluster'
  AND `version` = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.schema_version')) = 'pattern-v2'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].row')) = '1'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].col')) = '1';

UPDATE `run_pattern_definitions`
SET
  `definition_json` = JSON_REMOVE(`definition_json`, '$.exits[1]'),
  `content_hash` = SHA2(CAST(JSON_REMOVE(`definition_json`, '$.exits[1]') AS CHAR), 256)
WHERE `slug` = 'v2_mountain_braided_combat'
  AND `version` = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.schema_version')) = 'pattern-v2'
  AND JSON_LENGTH(JSON_EXTRACT(`definition_json`, '$.exits')) = 3
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].row')) = '1'
  AND JSON_UNQUOTE(JSON_EXTRACT(`definition_json`, '$.exits[1].col')) = '3';
