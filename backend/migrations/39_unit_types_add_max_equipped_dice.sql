-- Migration: unit definition cap for equipped dice.
ALTER TABLE `unit_types`
  ADD COLUMN `max_equipped_dice` INT NOT NULL DEFAULT 2 AFTER `max_level`;

