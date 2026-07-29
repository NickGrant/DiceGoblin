SET @v2_mountain_compact_profile = JSON_OBJECT(
  'region_slug', 'mountains',
  'generator_version', 'pattern-v2',
  'profile_version', 3,
  'enabled', true,
  'bounds', JSON_OBJECT('min_col', 0, 'max_col', 15, 'min_row', 0, 'max_row', 5, 'target_width', 16, 'target_height', 5),
  'budgets', JSON_OBJECT(
    'cost', JSON_OBJECT('min', 24, 'target', 24, 'max', 32, 'hard', true),
    'pattern_instances', JSON_OBJECT('min', 4, 'target', 4, 'max', 5, 'hard', true),
    'occupied_rows', JSON_OBJECT('min', 5, 'target', 5, 'max', 6, 'hard', true),
    'occupied_columns', JSON_OBJECT('min', 10, 'target', 16, 'max', 20, 'hard', true),
    'combat_nodes', JSON_OBJECT('min', 7, 'target', 10, 'max', 14, 'hard', true),
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
  'weight_policy', JSON_OBJECT('prefer_new_rows', 1.5, 'prefer_compact_width', 1.8, 'prefer_socket_reuse', 1.2)
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
  3,
  1,
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.bounds'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.budgets'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.requirements'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.retry_policy'),
  JSON_EXTRACT(@v2_mountain_compact_profile, '$.weight_policy'),
  SHA2(CAST(@v2_mountain_compact_profile AS CHAR), 256)
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
