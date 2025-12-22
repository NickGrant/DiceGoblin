CREATE TABLE IF NOT EXISTS run_edges (
  run_id BIGINT UNSIGNED NOT NULL,
  from_node_id BIGINT UNSIGNED NOT NULL,
  to_node_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (run_id, from_node_id, to_node_id),
  KEY idx_run_edges_from (from_node_id),
  KEY idx_run_edges_to (to_node_id),
  CONSTRAINT fk_run_edges_run
    FOREIGN KEY (run_id) REFERENCES region_runs(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_run_edges_from
    FOREIGN KEY (from_node_id) REFERENCES run_nodes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_run_edges_to
    FOREIGN KEY (to_node_id) REFERENCES run_nodes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
