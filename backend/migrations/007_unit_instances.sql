CREATE TABLE IF NOT EXISTS unit_instances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  unit_type_id BIGINT UNSIGNED NOT NULL,
  tier INT NOT NULL DEFAULT 1,
  level INT NOT NULL DEFAULT 1,
  xp INT NOT NULL DEFAULT 0,
  locked TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_unit_instances_user (user_id),
  KEY idx_unit_instances_user_type (user_id, unit_type_id),
  KEY idx_unit_instances_user_tier_level (user_id, tier, level),
  CONSTRAINT fk_unit_instances_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_unit_instances_unit_type
    FOREIGN KEY (unit_type_id) REFERENCES unit_types(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
