<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Combat\Engine\DeterministicRunNodeResolver;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class DeterministicRunNodeResolverFormationIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run deterministic resolver footprint integration tests.';
  }

  public function testResolveAppliesBackRowMeleeModifierToLargeEnemyFootprints(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 67676767);

    $enemySlug = 'qa-large-boss-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-combat-footprint-' . bin2hex(random_bytes(4));
    $unitTypeSlug = 'qa-melee-unit-' . bin2hex(random_bytes(4));
    $encounterId = null;
    $unitTypeId = null;
    $unitId = null;

    try {
      $unitTypeId = $this->insertUnitType(
        $unitTypeSlug,
        [
          'attack' => 12,
          'defense' => 2,
          'max_hp' => 24,
        ],
        ['basic_attack_melee']
      );
      $unitId = $this->insertUnitInstance($userId, $unitTypeId);
      $this->insertTeamUnitRow($teamId, $unitId);
      $this->insertRunUnitStateRow($runId, $unitId, 20);

      $this->insertEnemyTemplate(
        $enemySlug,
        [
          'attack' => 1,
          'defense' => 0,
          'max_hp' => 80,
          'formation' => ['w' => 2, 'h' => 2],
        ],
        ['basic_attack_melee']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [[
              'enemy_template_slug' => $enemySlug,
              'pos' => ['x' => 1, 'y' => 0],
            ]],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '67676767'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $meta = is_array($log['meta'] ?? null) ? $log['meta'] : [];
      $participants = is_array($meta['participants'] ?? null) ? $meta['participants'] : [];
      $enemyUnits = is_array($participants['enemy'] ?? null) ? $participants['enemy'] : [];
      $this->assertSame(2, (int)($enemyUnits[0]['formation']['w'] ?? 0));
      $this->assertSame(2, (int)($enemyUnits[0]['formation']['h'] ?? 0));

      $events = is_array($log['events'] ?? null) ? $log['events'] : [];
      $playerAction = $this->firstActionEventForSide($events, 'player');
      $this->assertIsArray($playerAction, 'Expected at least one player action event.');
      $this->assertSame($enemySlug, (string)($playerAction['target_enemy_slug'] ?? ''));
      $this->assertStringContainsString('position x0.99', (string)($playerAction['affix_outcome'] ?? ''));
    } finally {
      $this->cleanupResolverFixture($runId, $teamId, $unitId, $unitTypeId, $encounterId, [$enemySlug]);
    }
  }

  public function testResolveUsesFootprintBackEdgeForBackTargetPreference(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 68686868);

    $wideEnemySlug = 'qa-wide-target-' . bin2hex(random_bytes(4));
    $skirmisherSlug = 'qa-mid-target-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-back-target-' . bin2hex(random_bytes(4));
    $unitTypeSlug = 'qa-ranged-unit-' . bin2hex(random_bytes(4));
    $encounterId = null;
    $unitTypeId = null;
    $unitId = null;

    try {
      $unitTypeId = $this->insertUnitType(
        $unitTypeSlug,
        [
          'attack' => 12,
          'defense' => 1,
          'max_hp' => 20,
        ],
        ['basic_attack_ranged']
      );
      $unitId = $this->insertUnitInstance($userId, $unitTypeId);
      $this->insertTeamUnitRow($teamId, $unitId);
      $this->insertRunUnitStateRow($runId, $unitId, 20);

      $this->insertEnemyTemplate(
        $wideEnemySlug,
        [
          'attack' => 1,
          'defense' => 0,
          'max_hp' => 80,
          'formation' => ['w' => 3, 'h' => 1],
        ],
        ['basic_attack_melee']
      );
      $this->insertEnemyTemplate(
        $skirmisherSlug,
        [
          'attack' => 1,
          'defense' => 0,
          'max_hp' => 80,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['basic_attack_melee']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [
              [
                'enemy_template_slug' => $wideEnemySlug,
                'pos' => ['x' => 2, 'y' => 0],
              ],
              [
                'enemy_template_slug' => $skirmisherSlug,
                'pos' => ['x' => 1, 'y' => 2],
              ],
            ],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '68686868'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $events = is_array($log['events'] ?? null) ? $log['events'] : [];
      $playerAction = null;
      foreach ($events as $event) {
        if (
          is_array($event)
          && (string)($event['type'] ?? '') === 'action'
          && (string)($event['side'] ?? '') === 'player'
          && (string)($event['actor_unit_instance_id'] ?? '') === (string)$unitId
          && (string)($event['ability_id'] ?? '') === 'basic_attack_ranged'
        ) {
          $playerAction = $event;
          break;
        }
      }

      $this->assertIsArray($playerAction, 'Expected a player ranged action event.');
      $this->assertSame($wideEnemySlug, (string)($playerAction['target_enemy_slug'] ?? ''));
    } finally {
      $this->cleanupResolverFixture($runId, $teamId, $unitId, $unitTypeId, $encounterId, [$wideEnemySlug, $skirmisherSlug]);
    }
  }

  public function testResolveTargetsAlliesForSupportAbilitiesWithoutDealingDamage(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 69696969);

    $supportUnitSlug = 'qa-support-unit-' . bin2hex(random_bytes(4));
    $frontlineUnitSlug = 'qa-frontline-unit-' . bin2hex(random_bytes(4));
    $enemySlug = 'qa-support-target-enemy-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-support-target-encounter-' . bin2hex(random_bytes(4));
    $supportUnitTypeId = null;
    $frontlineUnitTypeId = null;
    $supportUnitId = null;
    $frontlineUnitId = null;
    $encounterId = null;

    try {
      $supportUnitTypeId = $this->insertUnitType(
        $supportUnitSlug,
        [
          'attack' => 5,
          'defense' => 2,
          'max_hp' => 20,
        ],
        ['bolster_ally']
      );
      $frontlineUnitTypeId = $this->insertUnitType(
        $frontlineUnitSlug,
        [
          'attack' => 9,
          'defense' => 2,
          'max_hp' => 24,
        ],
        ['basic_attack_melee']
      );

      $supportUnitId = $this->insertUnitInstance($userId, $supportUnitTypeId);
      $frontlineUnitId = $this->insertUnitInstance($userId, $frontlineUnitTypeId);
      $this->insertTeamUnitRow($teamId, $supportUnitId);
      $this->insertTeamUnitRow($teamId, $frontlineUnitId);
      $this->insertRunUnitStateRow($runId, $supportUnitId, 20);
      $this->insertRunUnitStateRow($runId, $frontlineUnitId, 8);

      $this->insertEnemyTemplate(
        $enemySlug,
        [
          'attack' => 5,
          'defense' => 1,
          'max_hp' => 20,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['shield_up']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [[
              'enemy_template_slug' => $enemySlug,
              'pos' => ['x' => 2, 'y' => 1],
            ]],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '69696969'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $events = is_array($log['events'] ?? null) ? $log['events'] : [];

      $supportAction = null;
      foreach ($events as $event) {
        if (
          is_array($event)
          && (string)($event['type'] ?? '') === 'action'
          && (string)($event['side'] ?? '') === 'player'
          && (string)($event['actor_unit_instance_id'] ?? '') === (string)$supportUnitId
          && (string)($event['ability_id'] ?? '') === 'bolster_ally'
        ) {
          $supportAction = $event;
          break;
        }
      }

      $this->assertIsArray($supportAction, 'Expected a bolster ally action event.');
      $this->assertSame((string)$frontlineUnitId, (string)($supportAction['target_unit_instance_id'] ?? ''));
      $this->assertArrayNotHasKey('target_enemy_slug', $supportAction);
      $this->assertSame(0, (int)($supportAction['damage'] ?? -1));
      $this->assertGreaterThan(0, (int)($supportAction['target_hp_after'] ?? 0));
      $this->assertSame('buffed', (string)($supportAction['outcome'] ?? ''));
      $this->assertSame('bolstered', (string)($supportAction['status_applied'] ?? ''));
      $this->assertEqualsWithDelta(0.21, (float)($supportAction['status_params']['defense_pct'] ?? 0.0), 0.0001);
      $this->assertStringContainsString('bolstered applied', (string)($supportAction['ability_outcome'] ?? ''));
    } finally {
      $this->cleanupResolverFixture(
        $runId,
        $teamId,
        $supportUnitId,
        $supportUnitTypeId,
        $encounterId,
        [$enemySlug]
      );
      if ($frontlineUnitId !== null) {
        $this->pdo->prepare('DELETE FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?')->execute([$runId, $frontlineUnitId]);
        $this->pdo->prepare('DELETE FROM `team_units` WHERE `team_id` = ? AND `unit_instance_id` = ?')->execute([$teamId, $frontlineUnitId]);
        $this->pdo->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$frontlineUnitId]);
      }
      if ($frontlineUnitTypeId !== null) {
        $this->pdo->prepare('DELETE FROM `unit_types` WHERE `id` = ?')->execute([$frontlineUnitTypeId]);
      }
    }
  }

  public function testResolveAppliesEnemyPassiveStatBonusesAndPassiveStatuses(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 70707070);

    $unitTypeSlug = 'qa-enemy-passive-player-' . bin2hex(random_bytes(4));
    $enemySlug = 'qa-enemy-passive-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-enemy-passive-encounter-' . bin2hex(random_bytes(4));
    $unitTypeId = null;
    $unitId = null;
    $encounterId = null;

    try {
      $unitTypeId = $this->insertUnitType(
        $unitTypeSlug,
        [
          'attack' => 9,
          'defense' => 2,
          'max_hp' => 24,
        ],
        ['basic_attack_melee']
      );
      $unitId = $this->insertUnitInstance($userId, $unitTypeId);
      $this->insertTeamUnitRow($teamId, $unitId);
      $this->insertRunUnitStateRow($runId, $unitId, 24);

      $this->insertEnemyTemplate(
        $enemySlug,
        [
          'attack' => 6,
          'defense' => 4,
          'max_hp' => 30,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['basic_attack_melee'],
        ['thick_hide', 'dumb_luck']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [[
              'enemy_template_slug' => $enemySlug,
              'pos' => ['x' => 1, 'y' => 1],
            ]],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '70707070'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $meta = is_array($log['meta'] ?? null) ? $log['meta'] : [];
      $participants = is_array($meta['participants'] ?? null) ? $meta['participants'] : [];
      $enemyUnits = is_array($participants['enemy'] ?? null) ? $participants['enemy'] : [];
      $this->assertCount(1, $enemyUnits);
      $this->assertSame(6, (int)($enemyUnits[0]['attack'] ?? 0));
      $this->assertSame(6, (int)($enemyUnits[0]['defense'] ?? 0), 'thick_hide should increase enemy defense.');
      $this->assertSame(30, (int)($enemyUnits[0]['max_hp'] ?? 0));

      $events = is_array($log['events'] ?? null) ? $log['events'] : [];
      $enemyAction = $this->firstActionEventForSide($events, 'enemy');
      $this->assertIsArray($enemyAction, 'Expected at least one enemy action event.');
      $this->assertSame($enemySlug, (string)($enemyAction['actor_enemy_slug'] ?? ''));
      $this->assertGreaterThanOrEqual(0, (int)($enemyAction['damage'] ?? -1));
    } finally {
      $this->cleanupResolverFixture($runId, $teamId, $unitId, $unitTypeId, $encounterId, [$enemySlug]);
    }
  }

  public function testResolveBombTossAppliesFuseAndExplodesAtRoundEnd(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 71707070);

    $unitTypeSlug = 'qa-bomb-thrower-' . bin2hex(random_bytes(4));
    $enemySlug = 'qa-bomb-target-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-bomb-encounter-' . bin2hex(random_bytes(4));
    $unitTypeId = null;
    $unitId = null;
    $encounterId = null;

    try {
      $unitTypeId = $this->insertUnitType(
        $unitTypeSlug,
        [
          'attack' => 10,
          'defense' => 2,
          'max_hp' => 24,
        ],
        ['bomb_toss']
      );
      $unitId = $this->insertUnitInstance($userId, $unitTypeId);
      $this->insertTeamUnitRow($teamId, $unitId);
      $this->insertRunUnitStateRow($runId, $unitId, 24);

      $this->insertEnemyTemplate(
        $enemySlug,
        [
          'attack' => 1,
          'defense' => 1,
          'max_hp' => 60,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['shield_up']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [[
              'enemy_template_slug' => $enemySlug,
              'pos' => ['x' => 2, 'y' => 1],
            ]],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '71707070'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $events = is_array($log['events'] ?? null) ? $log['events'] : [];

      $bombAction = null;
      $explosionEvent = null;
      foreach ($events as $event) {
        if (
          $bombAction === null
          && is_array($event)
          && (string)($event['type'] ?? '') === 'action'
          && (string)($event['side'] ?? '') === 'player'
          && (string)($event['ability_id'] ?? '') === 'bomb_toss'
        ) {
          $bombAction = $event;
        }

        if (
          $explosionEvent === null
          && is_array($event)
          && (string)($event['type'] ?? '') === 'status_tick'
          && (string)($event['side'] ?? '') === 'enemy'
          && (string)($event['status_id'] ?? '') === 'fuse_lit'
        ) {
          $explosionEvent = $event;
        }
      }

      $this->assertIsArray($bombAction, 'Expected a bomb toss action event.');
      $this->assertSame('fuse_lit', (string)($bombAction['status_applied'] ?? ''));
      $this->assertSame(1, (int)($bombAction['status_duration_rounds'] ?? 0));

      $this->assertIsArray($explosionEvent, 'Expected fuse_lit to explode at round end.');
      $this->assertSame(1, (int)($explosionEvent['round'] ?? 0));
      $this->assertSame(20, (int)($explosionEvent['tick'] ?? 0));
      $this->assertGreaterThan(0, (int)($explosionEvent['damage'] ?? 0));
      $this->assertStringContainsString('exploded', (string)($explosionEvent['ability_outcome'] ?? ''));
    } finally {
      $this->cleanupResolverFixture($runId, $teamId, $unitId, $unitTypeId, $encounterId, [$enemySlug]);
    }
  }

  public function testResolveWrestleForcesOnlyTheNextHostileTarget(): void
  {
    $userId = $this->insertUser();
    $regionId = $this->insertRegion();
    $teamId = $this->insertTeam($userId);
    $runId = $this->insertRun($userId, $regionId, 71717171);

    $unitTypeSlug = 'qa-wrestle-player-' . bin2hex(random_bytes(4));
    $wrestlerSlug = 'qa-wrestler-' . bin2hex(random_bytes(4));
    $backlinerSlug = 'qa-backliner-' . bin2hex(random_bytes(4));
    $encounterSlug = 'qa-wrestle-encounter-' . bin2hex(random_bytes(4));
    $unitTypeId = null;
    $unitId = null;
    $encounterId = null;

    try {
      $unitTypeId = $this->insertUnitType(
        $unitTypeSlug,
        [
          'attack' => 10,
          'defense' => 2,
          'max_hp' => 26,
        ],
        ['basic_attack_ranged', 'aimed_shot']
      );
      $unitId = $this->insertUnitInstance($userId, $unitTypeId);
      $this->insertTeamUnitRow($teamId, $unitId);
      $this->insertRunUnitStateRow($runId, $unitId, 26);

      $this->insertEnemyTemplate(
        $wrestlerSlug,
        [
          'attack' => 4,
          'defense' => 2,
          'max_hp' => 80,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['basic_attack_melee', 'wrestle']
      );
      $this->insertEnemyTemplate(
        $backlinerSlug,
        [
          'attack' => 4,
          'defense' => 1,
          'max_hp' => 80,
          'formation' => ['w' => 1, 'h' => 1],
        ],
        ['basic_attack_ranged']
      );
      $encounterId = $this->insertEncounterTemplate(
        $encounterSlug,
        $regionId,
        [
          'teams' => [[
            'units' => [
              [
                'enemy_template_slug' => $wrestlerSlug,
                'pos' => ['x' => 2, 'y' => 1],
              ],
              [
                'enemy_template_slug' => $backlinerSlug,
                'pos' => ['x' => 0, 'y' => 1],
              ],
            ],
          ]],
        ]
      );

      $resolver = new DeterministicRunNodeResolver($this->pdo);
      $result = $resolver->resolve(
        $userId,
        $teamId,
        ['id' => (string)$runId, 'seed' => '71717171'],
        ['id' => '1', 'node_type' => 'combat', 'encounter_template_id' => (string)$encounterId]
      );

      $log = is_array($result['log'] ?? null) ? $result['log'] : [];
      $events = is_array($log['events'] ?? null) ? $log['events'] : [];

      $playerActions = array_values(array_filter(
        $events,
        static fn($event): bool => is_array($event)
          && (string)($event['type'] ?? '') === 'action'
          && (string)($event['side'] ?? '') === 'player'
          && (int)($event['round'] ?? 0) === 1
      ));
      $enemyWrestle = null;
      foreach ($events as $event) {
        if (
          is_array($event)
          && (string)($event['type'] ?? '') === 'action'
          && (string)($event['side'] ?? '') === 'enemy'
          && (string)($event['ability_id'] ?? '') === 'wrestle'
        ) {
          $enemyWrestle = $event;
          break;
        }
      }

      $this->assertIsArray($enemyWrestle, 'Expected an enemy wrestle action event.');
      $this->assertSame('wrestled', (string)($enemyWrestle['status_applied'] ?? ''));
      $this->assertCount(4, $playerActions);
      $this->assertSame($backlinerSlug, (string)($playerActions[0]['target_enemy_slug'] ?? ''));
      $this->assertSame($backlinerSlug, (string)($playerActions[1]['target_enemy_slug'] ?? ''));
      $this->assertSame($wrestlerSlug, (string)($playerActions[2]['target_enemy_slug'] ?? ''));
      $this->assertSame($backlinerSlug, (string)($playerActions[3]['target_enemy_slug'] ?? ''));
    } finally {
      $this->cleanupResolverFixture($runId, $teamId, $unitId, $unitTypeId, $encounterId, [$wrestlerSlug, $backlinerSlug]);
    }
  }

  /**
   * @param array<int,array<string,mixed>> $events
   * @return array<string,mixed>|null
   */
  private function firstActionEventForSide(array $events, string $side): ?array
  {
    foreach ($events as $event) {
      if (
        is_array($event)
        && (string)($event['type'] ?? '') === 'action'
        && (string)($event['side'] ?? '') === $side
      ) {
        return $event;
      }
    }

    return null;
  }

  /**
   * @param array<int,string> $enemySlugs
   */
  private function cleanupResolverFixture(
    int $runId,
    int $teamId,
    ?int $unitId,
    ?int $unitTypeId,
    ?int $encounterId,
    array $enemySlugs
  ): void {
    if ($encounterId !== null) {
      $this->pdo->prepare('DELETE FROM `encounter_templates` WHERE `id` = ?')->execute([$encounterId]);
    }
    if (count($enemySlugs) > 0) {
      $placeholders = implode(',', array_fill(0, count($enemySlugs), '?'));
      $this->pdo->prepare("DELETE FROM `enemy_templates` WHERE `slug` IN ($placeholders)")->execute($enemySlugs);
    }
    if ($unitId !== null) {
      $this->pdo->prepare('DELETE FROM `run_unit_state` WHERE `run_id` = ? AND `unit_instance_id` = ?')->execute([$runId, $unitId]);
      $this->pdo->prepare('DELETE FROM `team_units` WHERE `team_id` = ? AND `unit_instance_id` = ?')->execute([$teamId, $unitId]);
      $this->pdo->prepare('DELETE FROM `unit_instances` WHERE `id` = ?')->execute([$unitId]);
    }
    if ($unitTypeId !== null) {
      $this->pdo->prepare('DELETE FROM `unit_types` WHERE `id` = ?')->execute([$unitTypeId]);
    }
  }

  private function insertUnitInstance(int $userId, int $unitTypeId): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, 1, 1, 0, 0)
    ');
    $stmt->execute([$userId, $unitTypeId]);
    return (int)$this->pdo->lastInsertId();
  }

  private function insertTeamUnitRow(int $teamId, int $unitId): void
  {
    $stmt = $this->pdo->prepare('INSERT INTO `team_units` (`team_id`, `unit_instance_id`) VALUES (?, ?)');
    $stmt->execute([$teamId, $unitId]);
  }

  private function insertRunUnitStateRow(int $runId, int $unitId, int $currentHp): void
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `run_unit_state` (`run_id`, `unit_instance_id`, `current_hp`, `is_defeated`, `cooldowns_json`, `status_effects_json`)
      VALUES (?, ?, ?, 0, ?, ?)
    ');
    $stmt->execute([$runId, $unitId, $currentHp, '{}', '[]']);
  }

  /**
   * @param array<string,mixed> $baseStats
   * @param array<int,string> $activeAbilityIds
   */
  private function insertUnitType(string $slug, array $baseStats, array $activeAbilityIds): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `unit_types` (
        `slug`,
        `name`,
        `role`,
        `base_stats_json`,
        `ability_set_json`,
        `max_level`,
        `max_equipped_dice`,
        `attack_per_level`,
        `defense_per_level`,
        `max_hp_per_level`
      )
      VALUES (?, ?, ?, ?, ?, 10, 2, 1, 1, 2)
    ');
    $stmt->execute([
      $slug,
      $slug,
      'test',
      json_encode($baseStats, JSON_UNESCAPED_SLASHES),
      json_encode(['actives' => $activeAbilityIds], JSON_UNESCAPED_SLASHES),
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  /**
   * @param array<string,mixed> $baseStats
   * @param array<int,string> $equippedAbilityIds
   * @param array<int,string> $passiveAbilityIds
   */
  private function insertEnemyTemplate(
    string $slug,
    array $baseStats,
    array $equippedAbilityIds,
    array $passiveAbilityIds = []
  ): void
  {
    $abilitySet = [
      'actives' => $equippedAbilityIds,
      'passives' => $passiveAbilityIds,
    ];

    if ($this->schemaHasColumn('enemy_templates', 'equipped_abilities_json')) {
      $stmt = $this->pdo->prepare('
        INSERT INTO `enemy_templates` (
          `slug`,
          `name`,
          `tier`,
          `role`,
          `base_stats_json`,
          `ability_set_json`,
          `equipped_abilities_json`,
          `xp_reward`,
          `tags_json`
        )
        VALUES (?, ?, 1, ?, ?, ?, ?, 10, ?)
      ');
      $stmt->execute([
        $slug,
        $slug,
        'test',
        json_encode($baseStats, JSON_UNESCAPED_SLASHES),
        json_encode($abilitySet, JSON_UNESCAPED_SLASHES),
        json_encode($equippedAbilityIds, JSON_UNESCAPED_SLASHES),
        json_encode(['archetype' => 'qa'], JSON_UNESCAPED_SLASHES),
      ]);
      return;
    }

    $stmt = $this->pdo->prepare('
      INSERT INTO `enemy_templates` (
        `slug`,
        `name`,
        `tier`,
        `role`,
        `base_stats_json`,
        `ability_set_json`,
        `xp_reward`,
        `tags_json`
      )
      VALUES (?, ?, 1, ?, ?, ?, 10, ?)
    ');
    $stmt->execute([
      $slug,
      $slug,
      'test',
      json_encode($baseStats, JSON_UNESCAPED_SLASHES),
      json_encode($abilitySet, JSON_UNESCAPED_SLASHES),
      json_encode(['archetype' => 'qa'], JSON_UNESCAPED_SLASHES),
    ]);
  }

  /**
   * @param array<string,mixed> $enemySet
   */
  private function insertEncounterTemplate(string $slug, int $regionId, array $enemySet): int
  {
    $stmt = $this->pdo->prepare('
      INSERT INTO `encounter_templates` (
        `slug`,
        `region_id`,
        `difficulty_rating`,
        `description`,
        `enemy_set_json`,
        `reward_profile_json`
      )
      VALUES (?, ?, 1, ?, ?, ?)
    ');
    $stmt->execute([
      $slug,
      $regionId,
      'QA footprint combat encounter',
      json_encode($enemySet, JSON_UNESCAPED_SLASHES),
      json_encode([], JSON_UNESCAPED_SLASHES),
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  private function schemaHasColumn(string $table, string $column): bool
  {
    $stmt = $this->pdo->prepare('
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);
    return ((int)$stmt->fetchColumn()) > 0;
  }
}
