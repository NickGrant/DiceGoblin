CREATE TABLE IF NOT EXISTS squad_units (
  squad_id BIGINT UNSIGNED NOT NULL,
  unit_instance_id BIGINT UNSIGNED NOT NULL,
  added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (squad_id, unit_instance_id),
  KEY idx_squad_units_unit (unit_instance_id),
  CONSTRAINT fk_squad_units_squad
    FOREIGN KEY (squad_id) REFERENCES squads(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_squad_units_unit_instance
    FOREIGN KEY (unit_instance_id) REFERENCES unit_instances(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
