SET @v2_swamp_start = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_swamp_start_marsh',
  'version', 1,
  'status', 'enabled',
  'width', 4,
  'height', 4,
  'cost', 6,
  'tags', JSON_ARRAY('start', 'swamp', 'marsh'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'upper_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'upper_loot', 'type', 'loot', 'quality', 'minor')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'bog_shrine', 'type', 'shrine', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'dry_rest', 'type', 'rest')),
    JSON_ARRAY(JSON_OBJECT('key', 'start_node', 'type', 'combat', 'difficulty', 'easy', 'role', 'start'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'marsh_combat', 'type', 'combat', 'difficulty', 'easy'), NULL),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'mire_hazard', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'mud_loot', 'type', 'loot', 'quality', 'minor'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'start_node', 'to', 'bog_shrine'),
    JSON_OBJECT('from', 'start_node', 'to', 'mire_hazard'),
    JSON_OBJECT('from', 'start_node', 'to', 'marsh_combat', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'bog_shrine', 'to', 'upper_combat'),
    JSON_OBJECT('from', 'bog_shrine', 'to', 'dry_rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'upper_combat', 'to', 'upper_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 2))),
    JSON_OBJECT('from', 'marsh_combat', 'to', 'dry_rest'),
    JSON_OBJECT('from', 'marsh_combat', 'to', 'mud_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2))),
    JSON_OBJECT('from', 'mire_hazard', 'to', 'mud_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 3, 'direction', 'right'),
    JSON_OBJECT('row', 1, 'col', 3, 'direction', 'right'),
    JSON_OBJECT('row', 3, 'col', 3, 'direction', 'right')
  )
);

SET @v2_swamp_braid = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_swamp_broad_braid',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 5,
  'cost', 15,
  'tags', JSON_ARRAY('swamp', 'combat', 'braid', 'broad'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'upper_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'upper_loot', 'type', 'loot', 'quality', 'good'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'upper_hard', 'type', 'combat', 'difficulty', 'hard')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'upper_shrine', 'type', 'shrine', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'upper_rest', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'mid_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'mid_hazard', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'mid_chaos', 'type', 'chaos')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'low_loot', 'type', 'loot', 'quality', 'minor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'low_shrine', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'deep_hazard', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'deep_combat', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'deep_rest', 'type', 'rest'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'upper_combat', 'to', 'upper_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'upper_loot', 'to', 'upper_hard', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'upper_combat', 'to', 'upper_shrine'),
    JSON_OBJECT('from', 'upper_shrine', 'to', 'upper_rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'upper_shrine', 'to', 'mid_hazard'),
    JSON_OBJECT('from', 'upper_rest', 'to', 'mid_chaos'),
    JSON_OBJECT('from', 'mid_combat', 'to', 'mid_hazard', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'mid_hazard', 'to', 'mid_chaos', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 3))),
    JSON_OBJECT('from', 'mid_combat', 'to', 'low_loot'),
    JSON_OBJECT('from', 'mid_hazard', 'to', 'low_shrine'),
    JSON_OBJECT('from', 'low_loot', 'to', 'low_shrine', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2))),
    JSON_OBJECT('from', 'low_shrine', 'to', 'deep_rest'),
    JSON_OBJECT('from', 'deep_hazard', 'to', 'deep_combat', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 1))),
    JSON_OBJECT('from', 'deep_combat', 'to', 'deep_rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 3)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 4, 'col', 4, 'direction', 'right')
  )
);

SET @v2_swamp_pressure = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_swamp_pressure_fork',
  'version', 1,
  'status', 'enabled',
  'width', 5,
  'height', 5,
  'cost', 12,
  'tags', JSON_ARRAY('swamp', 'hazard', 'recovery', 'fork'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(JSON_OBJECT('key', 'top_hazard', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'top_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'top_loot', 'type', 'loot', 'quality', 'good')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'top_shrine', 'type', 'shrine', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'top_rest', 'type', 'rest'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'mid_combat', 'type', 'combat', 'difficulty', 'hard'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'mid_chaos', 'type', 'chaos'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'mid_hazard', 'type', 'hazard', 'quality', 'poor')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'low_loot', 'type', 'loot', 'quality', 'minor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'low_shrine', 'type', 'shrine', 'quality', 'poor'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'deep_combat', 'type', 'combat', 'difficulty', 'easy'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'deep_hazard', 'type', 'hazard', 'quality', 'poor'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'deep_rest', 'type', 'rest'))
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'top_hazard', 'to', 'top_combat', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 1))),
    JSON_OBJECT('from', 'top_combat', 'to', 'top_loot', 'through', JSON_ARRAY(JSON_OBJECT('row', 0, 'col', 3))),
    JSON_OBJECT('from', 'top_hazard', 'to', 'top_shrine'),
    JSON_OBJECT('from', 'top_shrine', 'to', 'top_rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 2))),
    JSON_OBJECT('from', 'top_rest', 'to', 'mid_hazard'),
    JSON_OBJECT('from', 'mid_combat', 'to', 'mid_chaos', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 1))),
    JSON_OBJECT('from', 'mid_chaos', 'to', 'mid_hazard', 'through', JSON_ARRAY(JSON_OBJECT('row', 2, 'col', 3))),
    JSON_OBJECT('from', 'mid_combat', 'to', 'low_loot'),
    JSON_OBJECT('from', 'mid_chaos', 'to', 'low_shrine'),
    JSON_OBJECT('from', 'low_loot', 'to', 'low_shrine', 'through', JSON_ARRAY(JSON_OBJECT('row', 3, 'col', 2))),
    JSON_OBJECT('from', 'low_shrine', 'to', 'deep_rest'),
    JSON_OBJECT('from', 'deep_combat', 'to', 'deep_hazard', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 1))),
    JSON_OBJECT('from', 'deep_hazard', 'to', 'deep_rest', 'through', JSON_ARRAY(JSON_OBJECT('row', 4, 'col', 3)))
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 0, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 2, 'col', 4, 'direction', 'right'),
    JSON_OBJECT('row', 4, 'col', 4, 'direction', 'right')
  )
);

SET @v2_swamp_terminal = JSON_OBJECT(
  'schema_version', 'pattern-v2',
  'slug', 'v2_swamp_boss_exit',
  'version', 1,
  'status', 'enabled',
  'width', 3,
  'height', 3,
  'cost', 3,
  'tags', JSON_ARRAY('terminal', 'swamp', 'boss'),
  'grid', JSON_ARRAY(
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'boss_reward', 'type', 'loot', 'quality', 'great'), NULL),
    JSON_ARRAY(JSON_OBJECT('key', 'bog_tyrant', 'type', 'boss'), JSON_OBJECT('type', 'connector'), JSON_OBJECT('key', 'exit_node', 'type', 'exit')),
    JSON_ARRAY(NULL, JSON_OBJECT('key', 'last_rest', 'type', 'rest'), NULL)
  ),
  'connections', JSON_ARRAY(
    JSON_OBJECT('from', 'bog_tyrant', 'to', 'exit_node', 'through', JSON_ARRAY(JSON_OBJECT('row', 1, 'col', 1))),
    JSON_OBJECT('from', 'bog_tyrant', 'to', 'boss_reward'),
    JSON_OBJECT('from', 'boss_reward', 'to', 'exit_node'),
    JSON_OBJECT('from', 'bog_tyrant', 'to', 'last_rest'),
    JSON_OBJECT('from', 'last_rest', 'to', 'exit_node')
  ),
  'exits', JSON_ARRAY(
    JSON_OBJECT('row', 1, 'col', 2, 'direction', 'right')
  )
);

INSERT INTO `run_pattern_definitions` (`slug`, `version`, `status`, `definition_json`, `content_hash`)
VALUES
  ('v2_swamp_start_marsh', 1, 'enabled', @v2_swamp_start, SHA2(CAST(@v2_swamp_start AS CHAR), 256)),
  ('v2_swamp_broad_braid', 1, 'enabled', @v2_swamp_braid, SHA2(CAST(@v2_swamp_braid AS CHAR), 256)),
  ('v2_swamp_pressure_fork', 1, 'enabled', @v2_swamp_pressure, SHA2(CAST(@v2_swamp_pressure AS CHAR), 256)),
  ('v2_swamp_boss_exit', 1, 'enabled', @v2_swamp_terminal, SHA2(CAST(@v2_swamp_terminal AS CHAR), 256))
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
INNER JOIN `regions` r ON r.`slug` = 'swamps'
WHERE p.`slug` = 'v2_swamp_start_marsh' AND p.`version` = 1
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
SELECT p.`id`, r.`id`, 'pattern-v2', 90, 'spine', 1, 7, 2, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'swamps'
WHERE p.`slug` = 'v2_swamp_broad_braid' AND p.`version` = 1
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
SELECT p.`id`, r.`id`, 'pattern-v2', 110, 'spine', 1, 7, 2, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'swamps'
WHERE p.`slug` = 'v2_swamp_pressure_fork' AND p.`version` = 1
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
SELECT p.`id`, r.`id`, 'pattern-v2', 100, 'terminal', 6, 9, 1, 0, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'swamps'
WHERE p.`slug` = 'v2_swamp_boss_exit' AND p.`version` = 1
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
SELECT p.`id`, r.`id`, 'pattern-v2', 15, 'spine', 1, 7, 1, 1, 1, JSON_OBJECT()
FROM `run_pattern_definitions` p
INNER JOIN `regions` r ON r.`slug` = 'swamps'
WHERE p.`slug` = 'v2_general_loot_connector' AND p.`version` = 1
ON DUPLICATE KEY UPDATE
  `base_weight` = VALUES(`base_weight`),
  `max_depth` = VALUES(`max_depth`),
  `max_per_run` = VALUES(`max_per_run`),
  `cooldown_patterns` = VALUES(`cooldown_patterns`),
  `enabled` = VALUES(`enabled`),
  `weight_modifiers_json` = VALUES(`weight_modifiers_json`);

SET @v2_swamp_profile = JSON_OBJECT(
  'region_slug', 'swamps',
  'generator_version', 'pattern-v2',
  'profile_version', 1,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 18, 'min_row', 0, 'max_row', 5, 'target_width', 18, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 27, 'target', 33, 'max', 39, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 5, 'max', 5, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 5, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 13, 'target', 17, 'max', 20, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 8, 'target', 12, 'max', 18, 'hard', true),
    'hazard_nodes', JSON_OBJECT('min', 3, 'target', 5, 'max', 8, 'hard', false),
    'reward_nodes', JSON_OBJECT('min', 4, 'target', 6, 'max', 10, 'hard', false),
    'recovery_nodes', JSON_OBJECT('min', 3, 'target', 5, 'max', 8, 'hard', false)
  ),
  'requirements', JSON_OBJECT(
    'required_node_types', JSON_ARRAY('boss', 'exit', 'rest', 'chaos', 'hazard'),
    'required_tags', JSON_ARRAY('start', 'swamp', 'terminal'),
    'external_connections', 'mostly_left_to_right',
    'connector_cells_create_edges_only', true
  ),
  'retry_policy', JSON_OBJECT('candidate_retries', 30, 'generation_attempts', 5),
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.7, 'prefer_compact_width', 1.6, 'prefer_socket_reuse', 1.3)
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
  JSON_EXTRACT(@v2_swamp_profile, '$.bounds'),
  JSON_EXTRACT(@v2_swamp_profile, '$.budgets'),
  JSON_EXTRACT(@v2_swamp_profile, '$.requirements'),
  JSON_EXTRACT(@v2_swamp_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_swamp_profile, '$.weight_policy'),
  SHA2(CAST(@v2_swamp_profile AS CHAR), 256)
FROM `regions` r
WHERE r.`slug` = 'swamps'
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `bounds_json` = VALUES(`bounds_json`),
  `budgets_json` = VALUES(`budgets_json`),
  `requirements_json` = VALUES(`requirements_json`),
  `retry_policy_json` = VALUES(`retry_policy_json`),
  `weight_policy_json` = VALUES(`weight_policy_json`),
  `content_hash` = VALUES(`content_hash`);
