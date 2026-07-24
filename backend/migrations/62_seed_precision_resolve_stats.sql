UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'frontline_bruiser_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_bruiser_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 7)
WHERE `slug` = 'frontline_bruiser_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 6)
WHERE `slug` = 'frontline_guardian_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 7)
WHERE `slug` = 'frontline_guardian_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 3, '$.resolve', 8)
WHERE `slug` = 'frontline_guardian_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 8, '$.resolve', 4)
WHERE `slug` = 'backline_marksman_t3';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'support_banner_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 7)
WHERE `slug` = 'support_banner_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'control_saboteur_t1';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'control_saboteur_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_pit_fighter_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frontline_shieldbreaker_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'backline_trapper_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'support_mascot_t2';

UPDATE `unit_types`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'control_plaguehand_t2';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'kobold_skirmisher';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 4, '$.resolve', 6)
WHERE `slug` = 'kobold_shieldbearer';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 4)
WHERE `slug` = 'kobold_sharpshooter';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 7, '$.resolve', 5)
WHERE `slug` = 'kobold_warchief';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frogman_bruiser';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'frogman_spearhunter';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 6)
WHERE `slug` = 'frogman_wardrummer';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 8)
WHERE `slug` = 'frogman_bog_tyrant';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 5)
WHERE `slug` = 'mudwrestler';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 6, '$.resolve', 4)
WHERE `slug` = 'mudslinger';

UPDATE `enemy_templates`
SET `base_stats_json` = JSON_SET(`base_stats_json`, '$.precision', 5, '$.resolve', 7)
WHERE `slug` = 'mudking';
