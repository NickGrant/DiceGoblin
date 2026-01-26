-- Seed initial loot tables (Milestone 1)
-- Table shape: loot_tables(slug, tier ENUM('t1','t2'), entries_json JSON)
-- Locked loot schema stored in entries_json:
--   version, description, drops.currency.soft(min/max), drops.dice(chance/rolls/choices[]),
--   drops.units(chance/pool[]), drops.region_items[] (boss only)

INSERT INTO `loot_tables` (
  `slug`,
  `tier`,
  `entries_json`
)
VALUES
  (
    'kobold_basic_loot',
    't1',
    JSON_OBJECT(
      'version', 1,
      'description', 'Standard rewards for kobold combat encounters. Dice and unit drops are optional; no region items.',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 8, 'max', 12)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.4,
          'rolls', 1,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 4, 'weight', 40),
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 30),
            JSON_OBJECT('material', 'wood',      'sides', 4, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.1,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'backline_marksman_t1', 'weight', 70),
            JSON_OBJECT('unit_type_slug', 'support_banner_t1',    'weight', 25),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',  'weight', 5)
          )
        )
      )
    )
  ),
  (
    'kobold_boss_loot',
    't2',
    JSON_OBJECT(
      'version', 1,
      'description', 'Boss rewards for kobold encounters. Includes mountain region item (Roc Egg).',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 25, 'max', 35)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.85,
          'rolls', 2,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 8, 'weight', 25),
            JSON_OBJECT('material', 'bone',      'sides', 6, 'weight', 10),
            JSON_OBJECT('material', 'bone',      'sides', 8, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.25,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'backline_marksman_t1', 'weight', 60),
            JSON_OBJECT('unit_type_slug', 'support_banner_t1',    'weight', 30),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',  'weight', 10)
          )
        ),
        'region_items', JSON_ARRAY(
          JSON_OBJECT('slug', 'roc_egg', 'chance', 0.4)
        )
      )
    )
  ),
  (
    'frogman_basic_loot',
    't1',
    JSON_OBJECT(
      'version', 1,
      'description', 'Standard rewards for frogman combat encounters. Dice and unit drops are optional; no region items.',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 8, 'max', 12)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.4,
          'rolls', 1,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 4, 'weight', 35),
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 4, 'weight', 20),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.1,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'frontline_bruiser_t1',  'weight', 70),
            JSON_OBJECT('unit_type_slug', 'frontline_guardian_t1', 'weight', 25),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',   'weight', 5)
          )
        )
      )
    )
  ),
  (
    'frogman_boss_loot',
    't2',
    JSON_OBJECT(
      'version', 1,
      'description', 'Boss rewards for frogman encounters. Includes swamp region item (Gator Head).',
      'drops', JSON_OBJECT(
        'currency', JSON_OBJECT(
          'soft', JSON_OBJECT('min', 25, 'max', 35)
        ),
        'dice', JSON_OBJECT(
          'chance', 0.85,
          'rolls', 2,
          'choices', JSON_ARRAY(
            JSON_OBJECT('material', 'cardboard', 'sides', 6, 'weight', 15),
            JSON_OBJECT('material', 'wood',      'sides', 6, 'weight', 35),
            JSON_OBJECT('material', 'wood',      'sides', 8, 'weight', 25),
            JSON_OBJECT('material', 'bone',      'sides', 6, 'weight', 15),
            JSON_OBJECT('material', 'bone',      'sides', 8, 'weight', 10)
          )
        ),
        'units', JSON_OBJECT(
          'chance', 0.25,
          'pool', JSON_ARRAY(
            JSON_OBJECT('unit_type_slug', 'frontline_bruiser_t1',  'weight', 60),
            JSON_OBJECT('unit_type_slug', 'frontline_guardian_t1', 'weight', 30),
            JSON_OBJECT('unit_type_slug', 'control_saboteur_t1',   'weight', 10)
          )
        ),
        'region_items', JSON_ARRAY(
          JSON_OBJECT('slug', 'gator_head', 'chance', 0.4)
        )
      )
    )
  )
ON DUPLICATE KEY UPDATE
  `tier` = VALUES(`tier`),
  `entries_json` = VALUES(`entries_json`);
