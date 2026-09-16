<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Combat;

use DiceGoblins\Application\Commands\CombatConfigurationException;
use DiceGoblins\Application\Commands\CombatResolutionIntegrityException;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Combat\Vnext\CombatRules;
use DiceGoblins\Content\CombatSnapshotNormalizer;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\Battles\CombatFormationPosition;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use InvalidArgumentException;
use Throwable;

/** Assembles one locked authoritative run/Warband/content snapshot outside the kernel. */
final class CombatSnapshotAssembler
{
  private readonly CombatSnapshotNormalizer $normalizer;

  public function __construct(
    private readonly ContentRegistry $content,
    private readonly SquadRepository $squads,
    private readonly UnitDetailQuery $unitDetails,
    private readonly WarbandDiceRepository $dice,
    private readonly BaseLevelStatResolver $stats = new BaseLevelStatResolver(),
  ) {
    $this->normalizer = new CombatSnapshotNormalizer($content);
  }

  /** @param list<array<string,mixed>> $runUnits */
  public function assemble(
    int $userId,
    int $squadId,
    array $runUnits,
    string $encounterId,
    string $seed,
  ): AssembledCombat {
    try {
      $formation = $this->squads->listFormationRowsForSquad($squadId, true);
      if ($formation === [] || $runUnits === []) throw new CombatResolutionIntegrityException('Run participation is empty.');
      $runHp = [];
      foreach ($runUnits as $row) {
        $unitId = (int)($row['unit_id'] ?? 0);
        if ((int)($row['run_id'] ?? 0) < 1 || $unitId < 1 || isset($runHp[$unitId]) || !is_int($row['current_hp'])) {
          throw new CombatResolutionIntegrityException('Run participation state is invalid.');
        }
        $runHp[$unitId] = $row['current_hp'];
      }

      $players = [];
      $manifest = [];
      $allDiceIds = [];
      $seenUnits = [];
      foreach ($formation as $row) {
        $unitId = (int)($row['unit_id'] ?? 0);
        $position = (int)($row['position'] ?? -1);
        if ($unitId < 1 || isset($seenUnits[$unitId]) || !array_key_exists($unitId, $runHp)
          || (int)($row['unit_user_id'] ?? 0) !== $userId || ($row['lifecycle_status'] ?? null) !== 'active') {
          throw new CombatResolutionIntegrityException('Squad formation and run participation disagree.');
        }
        $seenUnits[$unitId] = true;
        $detail = $this->unitDetails->execute($userId, $unitId, true);
        if ($detail['ability_loadout'] === []) throw new CombatConfigurationException('Participating unit loadout is empty.');
        $type = $this->content->unitType((string)$detail['unit_type_id']);
        $resolved = $this->stats->resolve($type['base_stats'], $type['growth_per_level'], (int)$detail['level']);
        $currentHp = $runHp[$unitId];
        if ($currentHp < 0 || $currentHp > $resolved->hp) {
          throw new CombatResolutionIntegrityException('Persisted run HP is outside the resolved unit boundary.');
        }

        $bindings = [];
        foreach ($detail['dice_bindings'] as $binding) {
          $dieId = (int)$binding['dice_instance_id'];
          if ($dieId < 1 || isset($allDiceIds[$dieId])) throw new CombatConfigurationException('Physical combat die identity is duplicated.');
          $allDiceIds[$dieId] = true;
          $bindings[$binding['ability_id']][(int)$binding['slot_index']] = $dieId;
        }
        $diceRows = $this->dice->listActiveByIdsForUser($userId, array_values(array_map('intval', array_column($detail['dice_bindings'], 'dice_instance_id'))), true);
        $diceById = [];
        foreach ($diceRows as $die) $diceById[(int)$die['id']] = $die;

        $typeAbilities = array_fill_keys($type['ability_ids'], true);
        $owned = array_fill_keys($detail['owned_ability_ids'], true);
        $active = [];
        foreach ($detail['ability_loadout'] as $equipped) {
          $abilityId = (string)$equipped['ability_id'];
          if (!isset($owned[$abilityId], $typeAbilities[$abilityId])) throw new CombatConfigurationException('Equipped ability is not available to the current unit type.');
          $definition = $this->content->ability($abilityId);
          if (($definition['kind'] ?? null) !== 'active' || !in_array($definition['handler_id'], CombatRules::ACTIVE_HANDLERS, true)) {
            throw new CombatConfigurationException('Equipped ability is unsupported by combat.');
          }
          $normalizedDice = [];
          for ($slot = 0; $slot < (int)$definition['dice_slot_count']; $slot++) {
            $dieId = $bindings[$abilityId][$slot] ?? null;
            if ($dieId === null || !isset($diceById[$dieId])) throw new CombatConfigurationException('Equipped ability dice are incomplete.');
            $die = $diceById[$dieId];
            $normalizedDice[] = $this->normalizer->normalizeDie('die_i' . $dieId, (int)$die['size'], (string)$die['profile_id']);
          }
          if (count($bindings[$abilityId] ?? []) !== (int)$definition['dice_slot_count']) {
            throw new CombatConfigurationException('Equipped ability dice slots are invalid.');
          }
          $active[] = $this->normalizer->ability($abilityId, $normalizedDice);
        }

        $passives = [];
        foreach ($type['ability_ids'] as $abilityId) {
          if (!isset($owned[$abilityId])) continue;
          $definition = $this->content->ability($abilityId);
          if (($definition['kind'] ?? null) !== 'passive') continue;
          $passive = $this->normalizer->passive($abilityId);
          CombatRules::validatePassive($passive['handler_id'], $passive['config']);
          $passives[] = $passive;
        }

        $key = 'player_p' . $position;
        $players[] = ['key' => $key, 'side' => 'player', 'position' => CombatFormationPosition::fromSquadPosition($position),
          'stats' => $resolved->toArray(), 'max_hp' => $resolved->hp, 'current_hp' => $currentHp,
          'active_abilities' => $active, 'passive_abilities' => $passives, 'statuses' => []];
        $manifest[] = ['combatant_key' => $key, 'side' => 'player', 'unit_id' => $unitId,
          'unit_type_id' => (string)$detail['unit_type_id'], 'enemy_unit_type_id' => null,
          'display_name' => (string)$detail['display_name'], 'art_key' => (string)$type['art_key']];
      }
      if (array_diff_key($runHp, $seenUnits) !== [] || array_diff_key($seenUnits, $runHp) !== []) {
        throw new CombatResolutionIntegrityException('Squad formation and run participation disagree.');
      }

      $globalBindings = $this->dice->listBindingsForDiceIds(array_keys($allDiceIds), true);
      $bindingCounts = [];
      foreach ($globalBindings as $binding) $bindingCounts[(int)$binding['dice_instance_id']] = ($bindingCounts[(int)$binding['dice_instance_id']] ?? 0) + 1;
      foreach (array_keys($allDiceIds) as $dieId) {
        if (($bindingCounts[$dieId] ?? 0) !== 1) throw new CombatConfigurationException('Physical combat die binding is invalid.');
      }

      $encounter = $this->content->encounter($encounterId);
      foreach ($encounter['combatants'] as $slot) {
        $enemy = $this->content->enemyUnitType($slot['enemy_unit_type_id']);
        $manifest[] = ['combatant_key' => $slot['key'], 'side' => 'enemy', 'unit_id' => null,
          'unit_type_id' => null, 'enemy_unit_type_id' => $slot['enemy_unit_type_id'],
          'display_name' => $enemy['display_name'], 'art_key' => $enemy['art_key']];
      }
      return new AssembledCombat($this->normalizer->forEncounter($seed, $players, $encounterId), $manifest);
    } catch (CombatConfigurationException|CombatResolutionIntegrityException $e) {
      throw $e;
    } catch (WarbandIntegrityException|InvalidArgumentException $e) {
      throw new CombatConfigurationException('Participating combat configuration is invalid.', 0, $e);
    } catch (ContentValidationException $e) {
      throw new CombatResolutionIntegrityException('Participating combat content is unavailable.', 0, $e);
    } catch (Throwable $e) {
      throw new CombatResolutionIntegrityException('Participating combat state is unavailable.', 0, $e);
    }
  }
}
