UPDATE `unit_types` ut
SET `ability_set_json` = JSON_SET(
  COALESCE(ut.`ability_set_json`, JSON_OBJECT('version', 1, 'actives', JSON_ARRAY(), 'passives', JSON_ARRAY())),
  '$.actives',
  (
    SELECT COALESCE(JSON_ARRAYAGG(`ability_id`), JSON_ARRAY())
    FROM (
      SELECT `ability_id`
      FROM (
        SELECT `ability_id`, MIN(`sort_key`) AS `sort_key`
        FROM (
          SELECT current_actives.`ability_id`, current_actives.`ord` AS `sort_key`
          FROM JSON_TABLE(
            COALESCE(JSON_EXTRACT(ut.`ability_set_json`, '$.actives'), JSON_ARRAY()),
            '$[*]' COLUMNS (`ord` FOR ORDINALITY, `ability_id` VARCHAR(64) PATH '$')
          ) current_actives
          UNION ALL
          SELECT grant_actives.`ability_id`, 1000 + grant_actives.`ord` AS `sort_key`
          FROM JSON_TABLE(
            COALESCE(JSON_EXTRACT(ut.`promotion_grants_json`, '$.actives'), JSON_ARRAY()),
            '$[*]' COLUMNS (`ord` FOR ORDINALITY, `ability_id` VARCHAR(64) PATH '$')
          ) grant_actives
        ) merged_actives
        WHERE `ability_id` IS NOT NULL AND `ability_id` <> ''
        GROUP BY `ability_id`
      ) deduped_actives
      ORDER BY `sort_key` ASC
    ) ordered_actives
  ),
  '$.passives',
  (
    SELECT COALESCE(JSON_ARRAYAGG(`ability_id`), JSON_ARRAY())
    FROM (
      SELECT `ability_id`
      FROM (
        SELECT `ability_id`, MIN(`sort_key`) AS `sort_key`
        FROM (
          SELECT current_passives.`ability_id`, current_passives.`ord` AS `sort_key`
          FROM JSON_TABLE(
            COALESCE(JSON_EXTRACT(ut.`ability_set_json`, '$.passives'), JSON_ARRAY()),
            '$[*]' COLUMNS (`ord` FOR ORDINALITY, `ability_id` VARCHAR(64) PATH '$')
          ) current_passives
          UNION ALL
          SELECT grant_passives.`ability_id`, 1000 + grant_passives.`ord` AS `sort_key`
          FROM JSON_TABLE(
            COALESCE(JSON_EXTRACT(ut.`promotion_grants_json`, '$.passives'), JSON_ARRAY()),
            '$[*]' COLUMNS (`ord` FOR ORDINALITY, `ability_id` VARCHAR(64) PATH '$')
          ) grant_passives
        ) merged_passives
        WHERE `ability_id` IS NOT NULL AND `ability_id` <> ''
        GROUP BY `ability_id`
      ) deduped_passives
      ORDER BY `sort_key` ASC
    ) ordered_passives
  )
)
WHERE `promotion_grants_json` IS NOT NULL;

ALTER TABLE `unit_types`
  DROP COLUMN `promotion_grants_json`;
