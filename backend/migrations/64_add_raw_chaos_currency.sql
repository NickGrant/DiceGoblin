ALTER TABLE `player_state`
  ADD COLUMN `currency_raw_chaos` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `currency_hard`;
