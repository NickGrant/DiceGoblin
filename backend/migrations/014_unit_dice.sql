CREATE TABLE IF NOT EXISTS unit_dice (
  unit_instance_id BIGINT UNSIGNED NOT NULL,
  dice_instance_id BIGINT UNSIGNED NOT NULL,
  slot_index INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (unit_instance_id, dice_instance_id),
  UNIQUE KEY uniq_unit_dice_slot (unit_instance_id, slot_index),
  KEY idx_unit_dice_dice (dice_instance_id),
  CONSTRAINT fk_unit_dice_unit
    FOREIGN KEY (unit_instance_id) REFERENCES unit_instances(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_unit_dice_dice
    FOREIGN KEY (dice_instance_id) REFERENCES dice_instances(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
