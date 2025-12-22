CREATE TABLE IF NOT EXISTS user_region_items (
  user_id BIGINT UNSIGNED NOT NULL,
  region_item_id BIGINT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, region_item_id),
  CONSTRAINT fk_user_region_items_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_user_region_items_region_item
    FOREIGN KEY (region_item_id) REFERENCES region_items(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
