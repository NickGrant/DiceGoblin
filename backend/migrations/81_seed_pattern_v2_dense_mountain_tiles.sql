SET @v2_mountain_dense_braid = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_dense_braid',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 5,
  'cost', 14,
  'tags', JSON_ARRAY('mountain', 'combat', 'braid', 'dense'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'combat_top', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'hard_top', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'loot_top', 'type', 'loot', 'quality', 'good')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'shrine_top', 'type', 'shrine', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest_top', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'combat_mid', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'hard_mid', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'chaos_mid', 'type', 'chaos')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'loot_low', 'type', 'loot', 'quality', 'minor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'shrine_low', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'hazard_low', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_low', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest_low', 'type', 'rest'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'combat_top', 'to', 'hard_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'hard_top', 'to', 'loot_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'combat_top', 'to', 'shrine_top'),
    JSON_OBJECT('from', 'shrine_top', 'to', 'rest_top', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'shrine_top', 'to', 'hard_mid'),
    JSON_OBJECT('from', 'rest_top', 'to', 'chaos_mid'),
    JSON_OBJECT('from', 'combat_mid', 'to', 'hard_mid', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'hard_mid', 'to', 'chaos_mid', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 3))),
    JSON_OBJECT('from', 'combat_mid', 'to', 'loot_low'),
    JSON_OBJECT('from', 'hard_mid', 'to', 'shrine_low'),
    JSON_OBJECT('from', 'loot_low', 'to', 'shrine_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2))),
    JSON_OBJECT('from', 'shrine_low', 'to', 'rest_low'),
    JSON_OBJECT('from', 'hazard_low', 'to', 'combat_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 1))),
    JSON_OBJECT('from', 'combat_low', 'to', 'rest_low', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 3)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 4, 'col', 4, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_dense_braid', 1, 'enabled', @v2_mountain_dense_braid, SHA2(CAST(@v2_mountain_dense_braid AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

INSERT INTO `run_pattern_region_rules` (
  `pattern_definition_id`,
  `region_id`,
  `generator_version`,
  `base_weight`,
  `allowed_phase`,
  `min_depth`,
  `max_depth`,
  `max_per_run`,
  `cooldown_patterns`,
  `enabled`,
  `weight_modifiers_json`
)
SELECT p.`id`, r.`id`, 'pattern-v2', 140, 'spine', 1, 5, 2, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_dense_braid' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

UPDATE `run_pattern_region_rules` rpr
INNER JOIN `run_pattern_definitions` rpd ON rpd.`id` = rpr.`pattern_definition_id`
INNER JOIN `regions` r ON r.`id` = rpr.`region_id`
SET
  rpr.`base_weight` = 40,
  rpr.`max_per_run` = 1
WHERE r.`slug` = 'mountains'
  AND rpr.`generator_version` = 'pattern-v2'
  AND rpr.`allowed_phase` = 'spine'
  AND rpd.`slug` = 'v2_mountain_braided_combat'
  AND rpd.`version` = 1;

UPDATE `run_pattern_region_rules` rpr
INNER JOIN `run_pattern_definitions` rpd ON rpd.`id` = rpr.`pattern_definition_id`
INNER JOIN `regions` r ON r.`id` = rpr.`region_id`
SET
  rpr.`base_weight` = 10,
  rpr.`max_per_run` = 1
WHERE r.`slug` = 'mountains'
  AND rpr.`generator_version` = 'pattern-v2'
  AND rpr.`allowed_phase` = 'spine'
  AND rpd.`slug` = 'v2_general_loot_connector'
  AND rpd.`version` = 1;

SET @v2_mountain_dense_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 2,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 15, 'min_row', 0, 'max_row', 5, 'target_width', 14, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 24, 'target', 32, 'max', 38, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 10, 'target', 14, 'max', 15, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 7, 'target', 11, 'max', 14, 'hard', true),
    'reward_nodes', JSON_OBJECT('min', 3, 'target', 5, 'max', 8, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 2, 'target', 4, 'max', 6, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest'),
    'required_tags', JSON_ARRAY('start', 'mountain', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.7, 'prefer_socket_reuse', 1.2)
);

INSERT INTO `run_generation_profiles` (
  `region_id`,
  `generator_version`,
  `profile_version`,
  `enabled`,
  `bounds_json`,
  `budgets_json`,
  `requirements_json`,
  `retry_policy_json`,
  `weight_policy_json`,
  `content_hash`
)
SELECT
  r.`id`,
  'pattern-v2',
  2,
  1,
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_dense_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_dense_profile AS CHAR), 256)
FROM `regions` r
WHERE r.`slug` = 'mountains'
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `bounds_json` = VALUES(`bounds_json`),
  `budgets_json` = VALUES(`budgets_json`),
  `requirements_json` = VALUES(`requirements_json`),
  `retry_policy_json` = VALUES(`retry_policy_json`),
  `weight_policy_json` = VALUES(`weight_policy_json`),
  `content_hash` = VALUES(`content_hash`);
