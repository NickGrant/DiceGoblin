UPDATE `encounter_templates`
SET
  `difficulty_rating` = 1,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 2,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_2';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 3,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Warband',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 2, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_combat_3';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 5,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Kobold Command',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'kobold_shieldbearer', 'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'kobold_sharpshooter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'kobold_skirmisher',   'pos', JSON_OBJECT('x', 2, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'kobold_warchief',     'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'mountains_kobold_boss_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 1,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_1';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 2,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_2';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 3,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Frogman Hunting Party',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_combat_3';

UPDATE `encounter_templates`
SET
  `difficulty_rating` = 5,
  `enemy_set_json` = JSON_OBJECT(
    'version', 2,
    'teams', JSON_ARRAY(
      JSON_OBJECT(
        'team_id', 'A',
        'label', 'Bog Court',
        'units', JSON_ARRAY(
          JSON_OBJECT('enemy_template_slug', 'frogman_bruiser',     'pos', JSON_OBJECT('x', 0, 'y', 0)),
          JSON_OBJECT('enemy_template_slug', 'frogman_bog_tyrant',  'pos', JSON_OBJECT('x', 0, 'y', 1)),
          JSON_OBJECT('enemy_template_slug', 'frogman_spearhunter', 'pos', JSON_OBJECT('x', 1, 'y', 2)),
          JSON_OBJECT('enemy_template_slug', 'frogman_wardrummer',  'pos', JSON_OBJECT('x', 2, 'y', 1))
        )
      )
    )
  )
WHERE `slug` = 'swamps_frogman_boss_1';
