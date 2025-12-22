CREATE TABLE IF NOT EXISTS battles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  node_id BIGINT UNSIGNED NOT NULL,
  squad_id BIGINT UNSIGNED NOT NULL,
  rules_version VARCHAR(32) NOT NULL DEFAULT 'combat_v1',
  seed BIGINT UNSIGNED NOT NULL,
  status ENUM('completed','claimed') NOT NULL DEFAULT 'completed',
  outcome ENUM('victory','defeat') NOT NULL,
  ticks INT NOT NULL,
  rounds INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_battles_run_node (run_id, node_id),
  KEY idx_battles_user_created (user_id, created_at),
  KEY idx_battles_squad (squad_id),
  CONSTRAINT fk_battles_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_battles_run
    FOREIGN KEY (run_id) REFERENCES region_runs(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_battles_node
    FOREIGN KEY (node_id) REFERENCES run_nodes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_battles_squad
    FOREIGN KEY (squad_id) REFERENCES squads(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
