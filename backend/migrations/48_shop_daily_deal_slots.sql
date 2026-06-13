ALTER TABLE `shop_daily_deals`
  ADD COLUMN `deal_slot` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `shop_date`;

ALTER TABLE `shop_daily_deals`
  DROP INDEX `uq_shop_daily_deals_user_date`,
  ADD UNIQUE KEY `uq_shop_daily_deals_user_date_slot` (`user_id`, `shop_date`, `deal_slot`);
