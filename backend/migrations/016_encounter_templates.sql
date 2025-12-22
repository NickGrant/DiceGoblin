CREATE TABLE IF NOT EXISTS encounter_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(64) NOT NULL,
  region_id BIGINT UNSIGNED NULL,
  difficulty_rating INT NOT NULL DEFAULT 1,
  enemy_set_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_encounter_templates_slug (slug),
  KEY idx_encounter_templates_region (region_id),
  CONSTRAINT fk_encounter_templates_region
    FOREIGN KEY (region_id) REFERENCES regions(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
