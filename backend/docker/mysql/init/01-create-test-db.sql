CREATE DATABASE IF NOT EXISTS `goblin_test`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'dice_test'@'%' IDENTIFIED BY 'dicepass_test';
GRANT ALL PRIVILEGES ON `goblin_test`.* TO 'dice_test'@'%';
FLUSH PRIVILEGES;
