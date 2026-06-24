-- Rebalance unit drop rates to reduce inventory flooding from combat rewards.

UPDATE `loot_tables`
SET `entries_json` = JSON_SET(`entries_json`, '$.drops.units.chance', 0.05)
WHERE `slug` IN ('kobold_basic_loot', 'frogman_basic_loot');

UPDATE `loot_tables`
SET `entries_json` = JSON_SET(`entries_json`, '$.drops.units.chance', 0.12)
WHERE `slug` IN ('kobold_boss_loot', 'frogman_boss_loot');
