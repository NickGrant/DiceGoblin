CREATE TABLE IF NOT EXISTS dice_instance_affixes (
  dice_instance_id BIGINT UNSIGNED NOT NULL,
  affix_definition_id BIGINT UNSIGNED NOT NULL,
  value DECIMAL(10,3) NOT NULL,
  PRIMARY KEY (dice_instance_id, affix_definition_id),
  KEY idx_dice_affixes_affix (affix_definition_id),
  CONSTRAINT fk_dice_affixes_dice
    FOREIGN KEY (dice_instance_id) REFERENCES dice_instances(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_dice_affixes_def
    FOREIGN KEY (affix_definition_id) REFERENCES affix_definitions(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
