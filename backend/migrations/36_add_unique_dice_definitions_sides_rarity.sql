ALTER TABLE `dice_definitions`
  ADD UNIQUE KEY `uq_dice_definitions_sides_rarity` (`sides`, `rarity`);
