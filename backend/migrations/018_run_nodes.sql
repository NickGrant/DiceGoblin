CREATE TABLE IF NOT EXISTS run_nodes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  node_index INT NOT NULL,
  node_type ENUM('combat','loot','rest','static','boss') NOT NULL,
  status ENUM('locked','available','cleared') NOT NULL DEFAULT 'locked',
  encounter_template_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_run_nodes_run_index (run_id, node_index),
  KEY idx_run_nodes_run_status (run_id, status),
  KEY idx_run_nodes_encounter (encounter_template_id),
  CONSTRAINT fk_run_nodes_run
    FOREIGN KEY (run_id) REFERENCES region_runs(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_run_nodes_encounter
    FOREIGN KEY (encounter_template_id) REFERENCES encounter_templates(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
