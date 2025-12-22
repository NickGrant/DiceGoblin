CREATE TABLE IF NOT EXISTS squad_formation (
  squad_id BIGINT UNSIGNED NOT NULL,
  cell VARCHAR(2) NOT NULL, -- A1..C3
  unit_instance_id BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (squad_id, cell),
  KEY idx_squad_formation_unit (unit_instance_id),
  CONSTRAINT fk_squad_formation_squad
    FOREIGN KEY (squad_id) REFERENCES squads(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_squad_formation_unit_instance
    FOREIGN KEY (unit_instance_id) REFERENCES unit_instances(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
