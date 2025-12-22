CREATE TABLE IF NOT EXISTS battle_rewards (
  battle_id BIGINT UNSIGNED NOT NULL,
  xp_total INT NOT NULL DEFAULT 0,
  currency_soft INT NOT NULL DEFAULT 0,
  rewards_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (battle_id),
  CONSTRAINT fk_battle_rewards_battle
    FOREIGN KEY (battle_id) REFERENCES battles(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
