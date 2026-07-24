ALTER TABLE `users`
  MODIFY COLUMN `discord_id` VARCHAR(32) NULL,
  ADD COLUMN `local_email` VARCHAR(255) NULL AFTER `discord_id`,
  ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `local_email`,
  ADD UNIQUE KEY `uq_users_local_email` (`local_email`);
