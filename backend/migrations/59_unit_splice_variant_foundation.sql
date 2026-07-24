ALTER TABLE `unit_instances`
  ADD COLUMN `splice_variant_slug` VARCHAR(64) NOT NULL DEFAULT 'basic_goblin' AFTER `unit_type_id`;
