ALTER TABLE `unit_types`
  ADD COLUMN `precision_per_level` INT NOT NULL DEFAULT 1 AFTER `max_hp_per_level`,
  ADD COLUMN `resolve_per_level` INT NOT NULL DEFAULT 1 AFTER `precision_per_level`;
