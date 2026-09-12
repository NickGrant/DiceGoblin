<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Application\WarbandContentGuard;
use DiceGoblins\Application\WarbandIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use PDO;
use Throwable;

final class ProvisionWarbandFixtureCommand
{
  private readonly WarbandContentGuard $content;

  public function __construct(
    private readonly PDO $pdo,
    private readonly WarbandFixtureRepository $repository,
    ContentRegistry $content,
    private readonly ?Closure $beforeCommit = null,
  ) {
    $this->content = new WarbandContentGuard($content);
  }

  /** @return array<string,mixed> */
  public function execute(int $userId): array
  {
    $units = $this->unitDefinitions();
    $dice = $this->diceDefinitions();
    $squads = $this->squadDefinitions();
    $this->validateDefinitions($units, $dice, $squads);

    try {
      $this->pdo->beginTransaction();
      $this->repository->lockUserState($userId);
      if ($this->repository->hasCrossOwnerRelationships($userId)) {
        throw new WarbandIntegrityException('Fixture replacement found cross-owner Warband state.');
      }
      $this->repository->replaceOwnedWarband($userId);

      $unitIds = [];
      foreach ($units as $key => $unit) {
        $unitId = $this->repository->insertUnit(
          $userId,
          $unit['unit_type_id'],
          $unit['kin_id'],
          $unit['display_name'],
          $unit['level'],
          $unit['xp'],
        );
        $unitIds[$key] = $unitId;
        foreach ($unit['promotions'] as $promotion) {
          $this->repository->insertPromotion($unitId, $promotion[0], $promotion[1]);
        }
        foreach ($unit['owned_ability_ids'] as $abilityId) {
          $this->repository->insertOwnedAbility($unitId, $abilityId);
        }
        foreach ($unit['ability_loadout'] as $order => $abilityId) {
          $this->repository->insertLoadoutAbility($unitId, $abilityId, $order);
        }
      }

      $dieIds = [];
      foreach ($dice as $key => $die) {
        $dieIds[$key] = $this->repository->insertDie($userId, $die['size'], $die['profile_id']);
      }

      foreach ($units as $key => $unit) {
        foreach ($unit['bindings'] as $binding) {
          $this->repository->insertDiceBinding($unitIds[$key], $binding[0], $binding[1], $dieIds[$binding[2]]);
        }
      }

      $squadIds = [];
      foreach ($squads as $key => $squad) {
        $squadId = $this->repository->insertSquad($userId, $squad['name']);
        $squadIds[$key] = $squadId;
        foreach ($squad['formation'] as $position => $unitKey) {
          $this->repository->insertSquadUnit($squadId, $unitIds[$unitKey], $position);
        }
      }
      $playerRevision = $this->repository->setActiveSquadAndIncrementRevision($userId, $squadIds['raiders']);

      if ($this->beforeCommit !== null) {
        ($this->beforeCommit)();
      }

      $this->pdo->commit();
      return [
        'mode' => 'replace_owned_warband',
        'unit_ids' => array_map('strval', $unitIds),
        'dice_ids' => array_map('strval', $dieIds),
        'squad_ids' => array_map('strval', $squadIds),
        'active_squad_id' => (string)$squadIds['raiders'],
        'player_revision' => $playerRevision,
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }

  /** @return array<string,array<string,mixed>> */
  private function unitDefinitions(): array
  {
    return [
      'bruiser' => $this->unit('unit_type.bruiser', 'kin.goblin', 'Grub', 3, 120,
        ['ability.basic_attack_melee', 'ability.heavy_strike', 'ability.thick_hide'],
        ['ability.basic_attack_melee', 'ability.heavy_strike'],
        [['ability.basic_attack_melee', 0, 'bruiser_basic'], ['ability.heavy_strike', 0, 'bruiser_heavy']]),
      'guardian' => $this->unit('unit_type.guardian', 'kin.pig', 'Moss', 2, 45,
        ['ability.basic_attack_melee', 'ability.shield_up', 'ability.thick_hide'],
        ['ability.shield_up', 'ability.basic_attack_melee'],
        [['ability.shield_up', 0, 'guardian_shield'], ['ability.basic_attack_melee', 0, 'guardian_basic']]),
      'marksman' => $this->unit('unit_type.marksman', 'kin.goblin', 'Nix', 4, 210,
        ['ability.basic_attack_ranged', 'ability.aimed_shot', 'ability.sharpshooter'],
        ['ability.basic_attack_ranged', 'ability.aimed_shot'],
        [['ability.basic_attack_ranged', 0, 'marksman_basic'], ['ability.aimed_shot', 0, 'marksman_aimed']]),
      'bannerbearer' => $this->unit('unit_type.bannerbearer', 'kin.pig', 'Rattle', 2, 70,
        ['ability.basic_attack_melee', 'ability.bolster_ally'],
        ['ability.bolster_ally', 'ability.basic_attack_melee'],
        [['ability.bolster_ally', 0, 'banner_bolster'], ['ability.basic_attack_melee', 0, 'banner_basic']]),
      'saboteur' => $this->unit('unit_type.saboteur', 'kin.goblin', 'Soot', 5, 360,
        ['ability.basic_attack_ranged', 'ability.sleep_dart'],
        ['ability.sleep_dart', 'ability.basic_attack_ranged'],
        [['ability.sleep_dart', 0, 'saboteur_sleep_a'], ['ability.sleep_dart', 1, 'saboteur_sleep_b'], ['ability.basic_attack_ranged', 0, 'saboteur_basic']]),
      'enforcer' => $this->unit('unit_type.enforcer', 'kin.pig', 'Knuckles', 6, 525,
        ['ability.basic_attack_melee', 'ability.heavy_strike', 'ability.thick_hide', 'ability.skullcrack', 'ability.menacing_follow_through'],
        ['ability.skullcrack', 'ability.heavy_strike', 'ability.basic_attack_melee'],
        [['ability.skullcrack', 0, 'enforcer_skullcrack'], ['ability.heavy_strike', 0, 'enforcer_heavy'], ['ability.basic_attack_melee', 0, 'enforcer_basic']],
        [['unit_type.bruiser', 'unit_type.enforcer']]),
    ];
  }

  /** @return array<string,array{size:int,profile_id:string}> */
  private function diceDefinitions(): array
  {
    return [
      'bruiser_basic' => ['size' => 6, 'profile_id' => 'dice_profile.cardboard_plain'],
      'bruiser_heavy' => ['size' => 10, 'profile_id' => 'dice_profile.cardboard_striking'],
      'guardian_shield' => ['size' => 8, 'profile_id' => 'dice_profile.cardboard_guarding'],
      'guardian_basic' => ['size' => 6, 'profile_id' => 'dice_profile.wood_plain'],
      'marksman_basic' => ['size' => 8, 'profile_id' => 'dice_profile.wood_precise'],
      'marksman_aimed' => ['size' => 12, 'profile_id' => 'dice_profile.bone_executioner'],
      'banner_bolster' => ['size' => 10, 'profile_id' => 'dice_profile.wood_bulwark'],
      'banner_basic' => ['size' => 6, 'profile_id' => 'dice_profile.cardboard_plain'],
      'saboteur_sleep_a' => ['size' => 4, 'profile_id' => 'dice_profile.cardboard_plain'],
      'saboteur_sleep_b' => ['size' => 20, 'profile_id' => 'dice_profile.bone_explosive'],
      'saboteur_basic' => ['size' => 8, 'profile_id' => 'dice_profile.wood_precise'],
      'enforcer_skullcrack' => ['size' => 12, 'profile_id' => 'dice_profile.bone_executioner'],
      'enforcer_heavy' => ['size' => 10, 'profile_id' => 'dice_profile.cardboard_striking'],
      'enforcer_basic' => ['size' => 6, 'profile_id' => 'dice_profile.metal_plain'],
    ];
  }

  /** @return array<string,array{name:string,formation:array<int,string>}> */
  private function squadDefinitions(): array
  {
    return [
      'raiders' => ['name' => 'Raiders', 'formation' => [0 => 'bruiser', 1 => 'guardian', 3 => 'marksman', 4 => 'bannerbearer', 6 => 'saboteur']],
      'brawlers' => ['name' => 'Brawlers', 'formation' => [0 => 'enforcer', 4 => 'bannerbearer']],
    ];
  }

  /** @return array<string,mixed> */
  private function unit(string $type, string $kin, string $name, int $level, int $xp, array $owned, array $loadout, array $bindings, array $promotions = []): array
  {
    return ['unit_type_id' => $type, 'kin_id' => $kin, 'display_name' => $name, 'level' => $level, 'xp' => $xp,
      'owned_ability_ids' => $owned, 'ability_loadout' => $loadout, 'bindings' => $bindings, 'promotions' => $promotions];
  }

  /** @param array<string,array<string,mixed>> $units
   *  @param array<string,array{size:int,profile_id:string}> $dice
   *  @param array<string,array{name:string,formation:array<int,string>}> $squads
   */
  private function validateDefinitions(array $units, array $dice, array $squads): void
  {
    foreach ($dice as $die) {
      $profile = $this->content->diceProfile($die['profile_id']);
      $allowed = array_map('intval', is_array($profile['allowed_sizes'] ?? null) ? $profile['allowed_sizes'] : []);
      if (!in_array($die['size'], $allowed, true)) throw new WarbandIntegrityException('Fixture die size is invalid.');
    }
    $boundDice = [];
    foreach ($units as $unit) {
      $this->content->unitType($unit['unit_type_id']);
      $this->content->kin($unit['kin_id']);
      $owned = array_fill_keys($unit['owned_ability_ids'], true);
      $loaded = [];
      foreach ($unit['owned_ability_ids'] as $abilityId) $this->content->ability($abilityId);
      foreach ($unit['ability_loadout'] as $abilityId) {
        $ability = $this->content->ability($abilityId);
        if (!isset($owned[$abilityId]) || ($ability['kind'] ?? null) !== 'active') throw new WarbandIntegrityException('Fixture loadout is invalid.');
        $loaded[$abilityId] = true;
      }
      $boundSlots = [];
      foreach ($unit['bindings'] as $binding) {
        $ability = $this->content->ability($binding[0]);
        $slotKey = $binding[0] . ':' . $binding[1];
        if (!isset($loaded[$binding[0]]) || isset($boundSlots[$slotKey]) || isset($boundDice[$binding[2]])
          || $binding[1] < 0 || $binding[1] >= (int)($ability['dice_slot_count'] ?? 0) || !isset($dice[$binding[2]])) {
          throw new WarbandIntegrityException('Fixture dice binding is invalid.');
        }
        $boundSlots[$slotKey] = true;
        $boundDice[$binding[2]] = true;
      }
      foreach (array_keys($loaded) as $abilityId) {
        $slotCount = (int)$this->content->ability($abilityId)['dice_slot_count'];
        for ($slot = 0; $slot < $slotCount; $slot++) {
          if (!isset($boundSlots[$abilityId . ':' . $slot])) throw new WarbandIntegrityException('Fixture dice binding is incomplete.');
        }
      }
      foreach ($unit['promotions'] as $promotion) {
        $this->content->unitType($promotion[0]);
        $this->content->unitType($promotion[1]);
      }
    }
    foreach ($squads as $squad) {
      foreach ($squad['formation'] as $position => $unitKey) {
        if ($position < 0 || $position > 8 || !isset($units[$unitKey])) throw new WarbandIntegrityException('Fixture squad formation is invalid.');
      }
    }
  }
}
