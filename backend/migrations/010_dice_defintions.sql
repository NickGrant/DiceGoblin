CREATE TABLE IF NOT EXISTS dice_definitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sides INT NOT NULL, -- 4,6,8,10,12
  rarity ENUM('common','uncommon','rare','very_rare') NOT NULL,
  slot_capacity INT NOT NULL, -- 0..3
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dice_def_sides (sides),
  KEY idx_dice_def_rarity (rarity)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
