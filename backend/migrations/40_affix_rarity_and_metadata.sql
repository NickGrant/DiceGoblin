ALTER TABLE `dice_definitions`
  MODIFY COLUMN `rarity` ENUM('common','uncommon','rare','epic','legendary') NOT NULL;

ALTER TABLE `affix_definitions`
  ADD COLUMN `rarity` ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common' AFTER `name`,
  ADD COLUMN `behavior_kind` ENUM('passive','triggered') NOT NULL DEFAULT 'passive' AFTER `op`,
  ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '' AFTER `max_value`,
  ADD KEY `ix_affix_definitions_rarity` (`rarity`);
