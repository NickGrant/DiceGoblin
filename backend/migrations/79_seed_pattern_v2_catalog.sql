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
    JSON_ARRAY(JSON_OBJECT('key', 'start_node', 'type', 'combat', 'difficulty', 'easy', 'role', 'start'), JSON_OBJECT('key', 'mountain_start_dialogue', 'type', 'dialogue', 'dialogue_id', 'mountain_start'), NULL)
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'start_node', 'to', 'mountain_start_dialogue'),
    JSON_OBJECT('from', 'mountain_start_dialogue', 'to', 'poor_shrine'),
    JSON_OBJECT('from', 'poor_shrine', 'to', 'easy_combat'),
    JSON_OBJECT('from', 'easy_combat', 'to', 'minor_loot')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 2, 'direction', 'right'),
    JSON_OBJECT('row', 1, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_start_cluster', 1, 'enabled', @v2_mountain_start, SHA2(CAST(@v2_mountain_start AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_mountain_braid = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_braided_combat',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 3,
  'cost', 7,
  'tags', JSON_ARRAY('mountain', 'combat', 'braid'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'combat_a', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_b', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'combat_c', 'type', 'combat', 'difficulty', 'hard')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'good_loot', 'type', 'loot', 'quality', 'good'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'rest', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'hazard', 'type', 'hazard', 'quality', 'poor'), NULL, JSON_OBJECT('key', 'shrine', 'type', 'shrine', 'quality', 'poor'), NULL, JSON_OBJECT('key', 'chaos', 'type', 'chaos'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'combat_a', 'to', 'combat_b', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'combat_b', 'to', 'combat_c', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'combat_a', 'to', 'good_loot'),
    JSON_OBJECT('from', 'good_loot', 'to', 'rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'hazard', 'to', 'shrine'),
    JSON_OBJECT('from', 'shrine', 'to', 'chaos')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 1, 'col', 3, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_braided_combat', 1, 'enabled', @v2_mountain_braid, SHA2(CAST(@v2_mountain_braid AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_general_loot_connector = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_general_loot_connector',
  'version', 1,
  'status', 'enabled',
  'width', 2,
  'height', 1,
  'cost', 1,
  'tags', JSON_ARRAY('general', 'loot', 'connector'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'poor_loot', 'type', 'loot', 'quality', 'poor'), JSON_OBJECT('type', 'connector'))
  ),
  'connections', JSON_ARRAY(),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_general_loot_connector', 1, 'enabled', @v2_general_loot_connector, SHA2(CAST(@v2_general_loot_connector AS CHAR), 256))
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `definition_json` = VALUES(`definition_json`),
  `content_hash` = VALUES(`content_hash`);

SET @v2_mountain_boss_exit = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_mountain_boss_exit',
  'version', 1,
  'status', 'enabled',
  'width', 2,
  'height', 3,
  'cost', 2,
  'tags', JSON_ARRAY('terminal', 'mountain', 'boss'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'exit', 'type', 'exit')),
    JSON_ARRAY(JSON_OBJECT('key', 'boss', 'type', 'boss'), JSON_OBJECT('type', 'connector')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'reward_loot', 'type', 'loot', 'quality', 'good'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'boss', 'to', 'exit', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 1))),
    JSON_OBJECT('from', 'boss', 'to', 'reward_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 1)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 1, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES ('v2_mountain_boss_exit', 1, 'enabled', @v2_mountain_boss_exit, SHA2(CAST(@v2_mountain_boss_exit AS CHAR), 256))
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
SELECT p.`id`, r.`id`, 'pattern-v2', 100, 'start', 0, 0, 1, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_start_cluster' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

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
SELECT p.`id`, r.`id`, 'pattern-v2', 80, 'spine', 1, 5, 3, 1, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_braided_combat' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

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
SELECT p.`id`, r.`id`, 'pattern-v2', 30, 'spine', 1, 5, 2, 1, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_general_loot_connector' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

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
SELECT p.`id`, r.`id`, 'pattern-v2', 100, 'terminal', 5, 8, 1, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'mountains'
WHERE p.`slug` = 'v2_mountain_boss_exit' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

SET @v2_mountain_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 1,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 11, 'min_row', 0, 'max_row', 5, 'target_width', 9, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 18, 'target', 24, 'max', 30, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 6, 'max', 8, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 4, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 7, 'target', 9, 'max', 11, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 5, 'target', 8, 'max', 12, 'hard', true),
    'reward_nodes', JSON_OBJECT('min', 2, 'target', 3, 'max', 5, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 1, 'target', 2, 'max', 4, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest'),
    'required_tags', JSON_ARRAY('start', 'mountain', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.35, 'prefer_socket_reuse', 1.2)
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
  1,
  1,
  JSON_EXTRACT(@v2_mountain_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_profile AS CHAR), 256)
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
