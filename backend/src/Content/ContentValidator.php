<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

use DiceGoblins\Support\ClientSafeInteger;

use DiceGoblins\Combat\Vnext\CombatRules;
use InvalidArgumentException;

final class ContentValidator
{
  private const ID_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/';
  private const HANDLER_PATTERN = '/^[a-z][a-z0-9_]*$/';
  private const STAT_FIELDS = ['hp', 'attack', 'defense', 'precision', 'resolve'];
  private const SUPPORTED_DIE_SIZES = [4, 6, 8, 10, 12, 20];
  private const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
  private const RUN_GENERATION_ALGORITHMS = ['fixed_graph_v1'];
  private const LOCAL_NODE_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';
  private const WRONG_MACHINE_ACCESS_UNLOCK_ID = 'unlock.capability.wrong_machine_access';

  /** @param list<array{path: string, document: mixed}> $documents
   *  @return array<string, array<string, mixed>>
   */
  public function validate(array $documents): array
  {
    $definitions = [];
    foreach ($documents as $source) {
      $path = $source['path'];
      $document = $source['document'];
      if (!is_array($document) || array_is_list($document) || array_keys($document) !== ['definitions'] || !is_array($document['definitions']) || !array_is_list($document['definitions'])) {
        throw new ContentValidationException("Content file '{$path}' must be an object containing only a definitions array.");
      }

      foreach ($document['definitions'] as $offset => $definition) {
        $location = "{$path} definitions[{$offset}]";
        if (!is_array($definition) || array_is_list($definition)) {
          throw new ContentValidationException("{$location} must be an object.");
        }
        $id = $definition['id'] ?? null;
        if (!is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1) {
          throw new ContentValidationException("{$location} has an invalid stable id.");
        }
        if (isset($definitions[$id])) {
          throw new ContentValidationException("Duplicate stable id '{$id}'.");
        }
        $this->validateDefinition($definition, $location);
        $definitions[$id] = $definition;
      }
    }

    if ($definitions === []) {
      throw new ContentValidationException('Canonical content must contain at least one definition.');
    }
    $this->validateReferences($definitions);
    ksort($definitions, SORT_STRING);
    return $definitions;
  }

  /** @param array<string, mixed> $definition */
  private function validateDefinition(array $definition, string $location): void
  {
    $type = $definition['type'] ?? null;
    if (!is_string($type)) {
      throw new ContentValidationException("{$location} must have a string type.");
    }

    match ($type) {
      'gameplay_config' => $this->validateGameplayConfig($definition, $location),
      'region' => $this->validateRegion($definition, $location),
      'kin' => $this->validateKin($definition, $location),
      'unit_type' => $this->validateUnitType($definition, $location),
      'enemy_unit_type' => $this->validateEnemyUnitType($definition, $location),
      'encounter' => $this->validateEncounter($definition, $location),
      'ability' => $this->validateAbility($definition, $location),
      'dice_material' => $this->validateDiceMaterial($definition, $location),
      'dice_aspect' => $this->validateDiceAspect($definition, $location),
      'dice_profile' => $this->validateDiceProfile($definition, $location),
      'run_node_type' => $this->validateRunNodeType($definition, $location),
      'run_generation' => $this->validateRunGeneration($definition, $location),
      'unlock' => $this->validateUnlock($definition, $location),
      'capability' => $this->validateCapability($definition, $location),
      'academy_upgrade' => $this->validateAcademyUpgrade($definition, $location),
      'unit_promotion' => $this->validateUnitPromotion($definition, $location),
      'reconstruction_recipe' => $this->validateReconstructionRecipe($definition, $location),
      'event' => $this->validateEvent($definition, $location),
      'reward_definition' => $this->validateRewardDefinition($definition, $location),
      'item' => $this->validateItem($definition, $location),
      'shop_offer' => $this->validateShopOffer($definition, $location),
      default => throw new ContentValidationException("{$location} has unsupported type '{$type}'."),
    };
  }

  /** @param array<string, array<string, mixed>> $definitions */
  private function validateReferences(array $definitions): void
  {
    $config = $definitions['config.gameplay'] ?? null;
    if (!is_array($config)) {
      throw new ContentValidationException("Required definition 'config.gameplay' is missing.");
    }
    $regionId = (string)$config['starting_region_id'];
    if (!isset($definitions[$regionId]) || ($definitions[$regionId]['type'] ?? null) !== 'region') {
      throw new ContentValidationException("config.gameplay starting_region_id references missing region '{$regionId}'.");
    }

    $academyGrants = [];
    $academyByGrant = [];
    $academyEvents = [];
    $academyRewards = [];
    foreach ($definitions as $id => $definition) {
      if (($definition['type'] ?? null) === 'academy_upgrade') {
        $grant = $definition['grant_unlock_id'];
        if (isset($academyGrants[$grant])) throw new ContentValidationException("{$id} duplicates Academy grant '{$grant}'.");
        $academyGrants[$grant] = true;
        $academyByGrant[$grant] = $id;
        $unlock = $this->requireReferenceType($definitions, $id, 'grant_unlock_id', $grant, 'unlock');
        $target = $this->requireReferenceType($definitions, $grant, 'target_id', $unlock['target_id'], $unlock['target_type']);
        $category = $definition['category'];
        if (($category === 'unit_type' && $unlock['target_type'] !== 'unit_type')
          || ($category === 'energy' && ($unlock['target_type'] !== 'capability' || ($target['kind'] ?? null) !== 'energy_normal_max'))
          || ($category === 'dice' && ($unlock['target_type'] !== 'capability' || ($target['kind'] ?? null) !== 'max_acquirable_die_size'))) {
          throw new ContentValidationException("{$id} category does not match its grant target.");
        }
        $eventId = $definition['event_id'];
        if (isset($academyEvents[$eventId])) throw new ContentValidationException("{$id} shares Academy event '{$eventId}'.");
        $academyEvents[$eventId] = true;
        $event = $this->requireReferenceType($definitions, $id, 'event_id', $eventId, 'event');
        if (isset($academyRewards[$event['reward_definition_id']])) {
          throw new ContentValidationException("{$id} shares Academy reward '{$event['reward_definition_id']}'.");
        }
        $academyRewards[$event['reward_definition_id']] = true;
        $reward = $this->requireReferenceType($definitions, $eventId, 'reward_definition_id', $event['reward_definition_id'], 'reward_definition');
        $entries = $reward['entries'];
        if (count($entries) !== 1 || $entries[0]['probability_basis_points'] !== 10000
          || $entries[0]['reward_type'] !== 'unlock' || ($entries[0]['config']['unlock_id'] ?? null) !== $grant) {
          throw new ContentValidationException("{$id} Academy reward must grant exactly its declared unlock.");
        }
        foreach ($definition['prerequisite_unlock_ids'] as $prerequisite) {
          $this->requireReferenceType($definitions, $id, 'prerequisite_unlock_ids', $prerequisite, 'unlock');
        }
      }
    }
    $visiting = []; $visited = [];
    $visit = function (string $id) use (&$visit, &$visiting, &$visited, $definitions, $academyByGrant): void {
      if (isset($visiting[$id])) throw new ContentValidationException("Academy prerequisite cycle at '{$id}'.");
      if (isset($visited[$id])) return;
      $visiting[$id] = true;
      foreach ($definitions[$id]['prerequisite_unlock_ids'] as $prerequisite) {
        if (isset($academyByGrant[$prerequisite])) $visit($academyByGrant[$prerequisite]);
      }
      unset($visiting[$id]); $visited[$id] = true;
    };
    foreach ($academyByGrant as $id) $visit($id);

    $recipeByGrant = [];
    foreach ($definitions as $id => $definition) {
      if (($definition['type'] ?? null) !== 'reconstruction_recipe') continue;
      $kin = $this->requireReferenceType($definitions, $id, 'kin_id', $definition['kin_id'], 'kin');
      $unlock = $this->requireReferenceType($definitions, $id, 'kin_unlock_id', $definition['kin_unlock_id'], 'unlock');
      if ($unlock['target_type'] !== 'kin' || $unlock['target_id'] !== $kin['id']) {
        throw new ContentValidationException("{$id} kin_unlock_id must restore its target Kin.");
      }
      if (isset($recipeByGrant[$unlock['id']])) throw new ContentValidationException("{$id} duplicates Kin restoration '{$unlock['id']}'.");
      $recipeByGrant[$unlock['id']] = $id;
      if (!in_array(self::WRONG_MACHINE_ACCESS_UNLOCK_ID, $definition['prerequisite_unlock_ids'], true)) {
        throw new ContentValidationException("{$id} must require Wrong Machine access.");
      }
      $machineUnlock = $this->requireReferenceType($definitions, $id, 'prerequisite_unlock_ids',
        self::WRONG_MACHINE_ACCESS_UNLOCK_ID, 'unlock');
      if ($machineUnlock['target_type'] !== 'capability'
        || $machineUnlock['target_id'] !== 'capability.wrong_machine_access') {
        throw new ContentValidationException("{$id} Wrong Machine prerequisite must target its access capability.");
      }
      foreach ($definition['prerequisite_unlock_ids'] as $prerequisite) {
        $this->requireReferenceType($definitions, $id, 'prerequisite_unlock_ids', $prerequisite, 'unlock');
      }
      foreach (['first_restoration', 'repeat_reconstruction'] as $mode) {
        foreach ($definition[$mode]['ingredients'] as $ingredient) {
          $item = $this->requireReferenceType($definitions, $id, "{$mode}.ingredients.item_id", $ingredient['item_id'], 'item');
          if ($item['category'] !== 'material' || $item['stackable'] !== true) {
            throw new ContentValidationException("{$id} {$mode} ingredient must be a stackable material.");
          }
        }
      }
    }
    $visiting = []; $visited = [];
    $visitRecipe = function (string $id) use (&$visitRecipe, &$visiting, &$visited, $definitions, $recipeByGrant): void {
      if (isset($visiting[$id])) throw new ContentValidationException("Reconstruction prerequisite cycle at '{$id}'.");
      if (isset($visited[$id])) return;
      $visiting[$id] = true;
      foreach ($definitions[$id]['prerequisite_unlock_ids'] as $prerequisite) {
        if (isset($recipeByGrant[$prerequisite])) $visitRecipe($recipeByGrant[$prerequisite]);
      }
      unset($visiting[$id]); $visited[$id] = true;
    };
    foreach ($recipeByGrant as $id) $visitRecipe($id);

    $promotionPairs = []; $promotionTargets = [];
    foreach ($definitions as $id => $definition) {
      if (($definition['type'] ?? null) !== 'unit_promotion') continue;
      $from = $this->requireReferenceType($definitions, $id, 'from_unit_type_id', $definition['from_unit_type_id'], 'unit_type');
      $to = $this->requireReferenceType($definitions, $id, 'to_unit_type_id', $definition['to_unit_type_id'], 'unit_type');
      $pair = $from['id'] . '>' . $to['id'];
      if ($from['id'] === $to['id'] || $to['tier'] !== $from['tier'] + 1 || !in_array($from['tier'], [1, 2], true)) {
        throw new ContentValidationException("{$id} must promote to the next supported tier.");
      }
      if (isset($promotionPairs[$pair])) throw new ContentValidationException("{$id} duplicates promotion path '{$pair}'.");
      $promotionPairs[$pair] = true;
      $promotionTargets[$from['id']][] = $to['id'];
    }
    $visiting = []; $visited = [];
    $visitPromotion = function (string $id) use (&$visitPromotion, &$visiting, &$visited, $promotionTargets): void {
      if (isset($visiting[$id])) throw new ContentValidationException("Unit promotion cycle at '{$id}'.");
      if (isset($visited[$id])) return;
      $visiting[$id] = true;
      foreach ($promotionTargets[$id] ?? [] as $target) $visitPromotion($target);
      unset($visiting[$id]); $visited[$id] = true;
    };
    foreach (array_keys($promotionTargets) as $id) $visitPromotion($id);

    foreach ($definitions as $id => $definition) {
      if (($definition['type'] ?? null) === 'region') {
        if (isset($definition['run_generation_id'])) {
          $generation = $this->requireReferenceType(
            $definitions, $id, 'run_generation_id', $definition['run_generation_id'], 'run_generation');
          foreach ($generation['nodes'] as $node) {
            if (!isset($node['encounter_id'])) continue;
            $encounter = $this->requireReferenceType(
              $definitions, $generation['id'], 'nodes.encounter_id', $node['encounter_id'], 'encounter');
            $encounterRegionId = $encounter['region_id'] ?? null;
            if (!is_string($encounterRegionId) || !isset($definitions[$encounterRegionId])
              || ($definitions[$encounterRegionId]['type'] ?? null) !== 'region') {
              continue;
            }
            if ($encounterRegionId !== $id) {
              throw new ContentValidationException(
                "{$generation['id']} encounter '{$node['encounter_id']}' is not compatible with region '{$id}'.");
            }
          }
        }
      }

      if (($definition['type'] ?? null) === 'unlock') {
        $this->requireReferenceType($definitions, $id, 'target_id', $definition['target_id'], $definition['target_type']);
      }

      if (($definition['type'] ?? null) === 'item' && array_key_exists('source_region_id', $definition)) {
        $this->requireReferenceType($definitions, $id, 'source_region_id', $definition['source_region_id'], 'region');
        if (array_key_exists('source_encounter_id', $definition)) {
          $encounter = $this->requireReferenceType($definitions, $id, 'source_encounter_id', $definition['source_encounter_id'], 'encounter');
          if ($encounter['region_id'] !== $definition['source_region_id']) {
            throw new ContentValidationException("{$id} source encounter is not in its source region.");
          }
        }
        foreach ($definition['victory_drops'] ?? [] as $drop) {
          foreach ($drop['enemy_unit_type_ids'] as $enemyId) {
            $this->requireReferenceType($definitions, $id, 'victory_drops.enemy_unit_type_ids', $enemyId, 'enemy_unit_type');
          }
          $encounters = isset($definition['source_encounter_id'])
            ? [$definitions[$definition['source_encounter_id']]]
            : array_filter($definitions, static fn(array $candidate): bool => ($candidate['type'] ?? null) === 'encounter'
              && $candidate['region_id'] === $definition['source_region_id'] && $candidate['kind'] === $drop['node_type']);
          $reachable = false;
          foreach ($encounters as $encounter) {
            if ($encounter['kind'] !== $drop['node_type']) continue;
            if (array_intersect($drop['enemy_unit_type_ids'], array_column($encounter['combatants'], 'enemy_unit_type_id')) !== []) {
              $reachable = true;
              break;
            }
          }
          if (!$reachable) throw new ContentValidationException("{$id} victory drop has no matching source encounter.");
        }
      }

      if (($definition['type'] ?? null) === 'event') {
        $this->requireReferenceType($definitions, $id, 'reward_definition_id', $definition['reward_definition_id'], 'reward_definition');
      }

      if (($definition['type'] ?? null) === 'reward_definition') {
        foreach ($definition['entries'] as $entry) {
          if ($entry['reward_type'] === 'unlock') {
            $this->requireReferenceType($definitions, $id, 'entries.config.unlock_id', $entry['config']['unlock_id'], 'unlock');
          }
        }
      }

      if (($definition['type'] ?? null) === 'shop_offer') {
        $grant = $definition['grant'];
        if ($grant['type'] === 'item') {
          $item = $this->requireReferenceType($definitions, $id, 'grant.item_id', $grant['item_id'], 'item');
          if (($item['stackable'] ?? null) !== true) {
            throw new ContentValidationException("{$id} grant.item_id must reference a stackable item.");
          }
        } elseif ($grant['type'] === 'die') {
          $profile = $this->requireReferenceType(
            $definitions, $id, 'grant.dice_profile_id', $grant['dice_profile_id'], 'dice_profile');
          if (!in_array($grant['size'], $profile['allowed_sizes'], true)) {
            throw new ContentValidationException("{$id} grant size is not allowed by its dice profile.");
          }
        } else {
          $unitType = $this->requireReferenceType(
            $definitions, $id, 'grant.unit_type_id', $grant['unit_type_id'], 'unit_type');
          $this->requireReferenceType($definitions, $id, 'grant.kin_id', $grant['kin_id'], 'kin');
          if (($unitType['tier'] ?? null) !== 1) {
            throw new ContentValidationException("{$id} grant.unit_type_id must reference a tier-1 unit type.");
          }
          $reachable = false;
          foreach ($definitions as $unlock) {
            if (($unlock['type'] ?? null) === 'unlock' && ($unlock['target_type'] ?? null) === 'unit_type'
              && ($unlock['target_id'] ?? null) === $grant['unit_type_id']) {
              $reachable = true; break;
            }
          }
          if (!$reachable) throw new ContentValidationException("{$id} unit grant has no authored unit-type unlock target.");
        }
      }

      if (($definition['type'] ?? null) === 'unit_type') {
        foreach ($definition['ability_ids'] as $abilityId) {
          $this->requireReferenceType($definitions, $id, 'ability_ids', $abilityId, 'ability');
        }
      }

      if (($definition['type'] ?? null) === 'enemy_unit_type') {
        foreach (['active_ability_ids' => 'active', 'passive_ability_ids' => 'passive'] as $field => $kind) {
          foreach ($definition[$field] as $abilityId) {
            $ability = $this->requireReferenceType($definitions, $id, $field, $abilityId, 'ability');
            if ($ability['kind'] !== $kind) throw new ContentValidationException("{$id} {$field} requires {$kind} abilities.");
          }
        }
        $die = $definition['virtual_ability_dice'];
        $profile = $this->requireReferenceType($definitions, $id, 'virtual_ability_dice.profile_id', $die['profile_id'], 'dice_profile');
        if ($profile['aspect_ids'] !== [] || !in_array($die['sides'], $profile['allowed_sizes'], true)) {
          throw new ContentValidationException("{$id} virtual_ability_dice must use an eligible plain profile.");
        }
      }

      if (($definition['type'] ?? null) === 'encounter') {
        $this->requireReferenceType($definitions, $id, 'region_id', $definition['region_id'], 'region');
        foreach ($definition['combatants'] as $combatant) {
          $this->requireReferenceType($definitions, $id, 'combatants.enemy_unit_type_id', $combatant['enemy_unit_type_id'], 'enemy_unit_type');
        }
      }

      if (($definition['type'] ?? null) === 'dice_profile') {
        $materialId = $definition['material_id'];
        $material = $this->requireReferenceType($definitions, $id, 'material_id', $materialId, 'dice_material');
        $aspects = [];
        foreach ($definition['aspect_ids'] as $aspectId) {
          $aspects[] = $this->requireReferenceType($definitions, $id, 'aspect_ids', $aspectId, 'dice_aspect');
        }
        $this->validateProfileSizes($id, $definition, $material, $aspects);
      }

      if (($definition['type'] ?? null) === 'run_generation') {
        foreach ($definition['nodes'] as $node) {
          $this->requireReferenceType($definitions, $id, 'nodes.node_type_id', $node['node_type_id'], 'run_node_type');
          if ($node['encounter_id'] ?? null) {
            if (!in_array($node['node_type_id'], ['run_node_type.combat', 'run_node_type.boss'], true)) {
              throw new ContentValidationException("{$id} only combat or boss nodes may reference encounters.");
            }
            $this->requireReferenceType($definitions, $id, 'nodes.encounter_id', $node['encounter_id'], 'encounter');
          }
          if ($node['event_id'] ?? null) {
            $this->requireReferenceType($definitions, $id, 'nodes.event_id', $node['event_id'], 'event');
          }
        }
        $this->validateRunGenerationConnectivity($id, $definition);
      }
    }

    $farm = $definitions['region.the_farm'] ?? null;
    if (is_array($farm)) {
      $this->validateFarmGenerationStructure($definitions[(string)$farm['run_generation_id']]);
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateGameplayConfig(array $definition, string $location): void
  {
    $this->requireExactId($definition, 'config.gameplay', $location);
    $this->requireIntegerInRange($definition, 'starting_energy', 0, 1000000, $location);
    $this->requireIntegerInRange($definition, 'energy_normal_max', 1, 1000000, $location);
    $this->requirePositiveNumber($definition, 'energy_regeneration_per_hour', $location);
    $this->requireIntegerInRange($definition, 'run_energy_cost', 1, 1000000, $location);
    $this->requireStableId($definition, 'starting_region_id', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateRegion(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'display_name', 'art_key'], ['run_generation_id'], $location);
    $this->requireNamespace($definition, 'region.', $location);
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'art_key', $location);
    if (isset($definition['run_generation_id'])) {
      $this->requireStableIdWithNamespace($definition, 'run_generation_id', 'run_generation.', $location);
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateUnlock(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'target_type', 'target_id'], [], $location);
    $this->requireNamespace($definition, 'unlock.', $location);
    $targetType = $this->requireAllowedString($definition, 'target_type', ['region', 'unit_type', 'capability', 'kin'], $location);
    $this->requireStableIdWithNamespace($definition, 'target_id', $targetType . '.', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateCapability(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'kind', 'value'], [], $location);
    $this->requireNamespace($definition, 'capability.', $location);
    $kind = $this->requireAllowedString($definition, 'kind', ['energy_normal_max', 'max_acquirable_die_size', 'feature_access'], $location);
    if ($kind === 'energy_normal_max') {
      $this->requireIntegerInRange($definition, 'value', 1, ClientSafeInteger::MAXIMUM, $location);
    } elseif ($kind === 'feature_access') {
      if (($definition['value'] ?? null) !== 1) throw new ContentValidationException("{$location} feature access value must be 1.");
    } elseif (!in_array($definition['value'] ?? null, [10, 12, 20], true)) {
      throw new ContentValidationException("{$location} has an unsupported maximum die size.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateAcademyUpgrade(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'display_name', 'description', 'category', 'price', 'grant_unlock_id', 'prerequisite_unlock_ids', 'event_id'], [], $location);
    $this->requireNamespace($definition, 'academy_upgrade.', $location);
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'description', $location);
    $this->requireAllowedString($definition, 'category', ['unit_type', 'energy', 'dice'], $location);
    $this->requireStableIdWithNamespace($definition, 'grant_unlock_id', 'unlock.', $location);
    $this->requireStableIdWithNamespace($definition, 'event_id', 'event.', $location);
    $price = $definition['price'] ?? null;
    if (!is_array($price) || array_is_list($price)) throw new ContentValidationException("{$location} price must be an object.");
    $this->requireExactFieldSet($price, ['currency_id', 'amount'], [], $location);
    $this->requireAllowedString($price, 'currency_id', ['raw_chaos'], $location);
    $this->requireIntegerInRange($price, 'amount', 1, ClientSafeInteger::MAXIMUM, $location);
    $prerequisites = $definition['prerequisite_unlock_ids'] ?? null;
    if (!is_array($prerequisites) || !array_is_list($prerequisites)) throw new ContentValidationException("{$location} prerequisites must be a list.");
    $seen = [];
    foreach ($prerequisites as $prerequisite) {
      $this->requireStableIdWithNamespace(['id' => $prerequisite], 'id', 'unlock.', $location);
      if ($prerequisite === $definition['grant_unlock_id'] || isset($seen[$prerequisite])) {
        throw new ContentValidationException("{$location} has a duplicate or self prerequisite.");
      }
      $seen[$prerequisite] = true;
    }
  }

  /** @param array<string,mixed> $definition */
  private function validateUnitPromotion(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'from_unit_type_id', 'to_unit_type_id', 'required_level', 'price'], [], $location);
    $this->requireNamespace($definition, 'unit_promotion.', $location);
    $this->requireStableIdWithNamespace($definition, 'from_unit_type_id', 'unit_type.', $location);
    $this->requireStableIdWithNamespace($definition, 'to_unit_type_id', 'unit_type.', $location);
    $this->requireIntegerInRange($definition, 'required_level', 1, ClientSafeInteger::MAXIMUM, $location);
    $price = $definition['price'] ?? null;
    if (!is_array($price) || array_is_list($price)) throw new ContentValidationException("{$location} price must be an object.");
    $this->requireExactFieldSet($price, ['currency_id', 'amount'], [], $location);
    $this->requireAllowedString($price, 'currency_id', ['raw_chaos'], $location);
    $this->requireIntegerInRange($price, 'amount', 1, ClientSafeInteger::MAXIMUM, $location);
  }

  /** @param array<string,mixed> $definition */
  private function validateReconstructionRecipe(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition,
      ['id', 'type', 'display_name', 'description', 'kin_id', 'kin_unlock_id', 'prerequisite_unlock_ids',
        'first_restoration', 'repeat_reconstruction'], [], $location);
    $this->requireNamespace($definition, 'reconstruction_recipe.', $location);
    $this->requireBoundedNonEmptyString($definition, 'display_name', 128, $location);
    $this->requireBoundedNonEmptyString($definition, 'description', 512, $location);
    $this->requireStableIdWithNamespace($definition, 'kin_id', 'kin.', $location);
    $this->requireStableIdWithNamespace($definition, 'kin_unlock_id', 'unlock.kin.', $location);
    $prerequisites = $definition['prerequisite_unlock_ids'] ?? null;
    if (!is_array($prerequisites) || !array_is_list($prerequisites))
      throw new ContentValidationException("{$location} prerequisites must be a list.");
    $seen = [];
    foreach ($prerequisites as $prerequisite) {
      $this->requireStableIdWithNamespace(['id' => $prerequisite], 'id', 'unlock.', $location);
      if ($prerequisite === $definition['kin_unlock_id'] || isset($seen[$prerequisite]))
        throw new ContentValidationException("{$location} has a duplicate or self prerequisite.");
      $seen[$prerequisite] = true;
    }
    foreach (['first_restoration' => 'random_unlocked', 'repeat_reconstruction' => 'chosen_unlocked'] as $mode => $selection) {
      $value = $definition[$mode] ?? null;
      if (!is_array($value) || array_is_list($value)) throw new ContentValidationException("{$location} {$mode} must be an object.");
      $this->requireExactFieldSet($value, ['unit_type_selection', 'price', 'ingredients'], [], "{$location} {$mode}");
      $this->requireAllowedString($value, 'unit_type_selection', [$selection], "{$location} {$mode}");
      $price = $value['price'] ?? null;
      if (!is_array($price) || array_is_list($price)) throw new ContentValidationException("{$location} {$mode} price must be an object.");
      $this->requireExactFieldSet($price, ['currency_id', 'amount'], [], "{$location} {$mode} price");
      $this->requireAllowedString($price, 'currency_id', ['raw_chaos'], "{$location} {$mode} price");
      $this->requireIntegerInRange($price, 'amount', 1, ClientSafeInteger::MAXIMUM, "{$location} {$mode} price");
      $ingredients = $value['ingredients'] ?? null;
      if (!is_array($ingredients) || !array_is_list($ingredients))
        throw new ContentValidationException("{$location} {$mode} ingredients must be a list.");
      $items = [];
      foreach ($ingredients as $ingredient) {
        if (!is_array($ingredient) || array_is_list($ingredient))
          throw new ContentValidationException("{$location} {$mode} ingredient must be an object.");
        $this->requireExactFieldSet($ingredient, ['item_id', 'quantity'], [], "{$location} {$mode} ingredient");
        $this->requireStableIdWithNamespace($ingredient, 'item_id', 'item.', "{$location} {$mode} ingredient");
        $this->requireIntegerInRange($ingredient, 'quantity', 1, ClientSafeInteger::MAXIMUM, "{$location} {$mode} ingredient");
        if (isset($items[$ingredient['item_id']])) throw new ContentValidationException("{$location} {$mode} duplicates an ingredient.");
        $items[$ingredient['item_id']] = true;
      }
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateEvent(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'reward_definition_id'], [], $location);
    $this->requireNamespace($definition, 'event.', $location);
    $this->requireStableIdWithNamespace($definition, 'reward_definition_id', 'reward_definition.', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateRewardDefinition(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'entries'], [], $location);
    $this->requireNamespace($definition, 'reward_definition.', $location);
    $entries = $definition['entries'] ?? null;
    if (!is_array($entries) || !array_is_list($entries) || $entries === []) {
      throw new ContentValidationException("{$location} field 'entries' must be a non-empty ordered list.");
    }

    $keys = [];
    foreach ($entries as $offset => $entry) {
      $entryLocation = "{$location} field 'entries[{$offset}]'";
      if (!is_array($entry) || array_is_list($entry)) {
        throw new ContentValidationException("{$entryLocation} must be an object.");
      }
      $this->requireExactFieldSet($entry, ['key', 'probability_basis_points', 'reward_type', 'config'], [], $entryLocation);
      $key = $this->requireLocalNodeKey($entry, 'key', $entryLocation);
      if (isset($keys[$key])) {
        throw new ContentValidationException("{$location} contains duplicate reward entry key '{$key}'.");
      }
      $keys[$key] = true;
      $this->requireIntegerInRange($entry, 'probability_basis_points', 1, 10000, $entryLocation);
      $rewardType = $this->requireAllowedString($entry, 'reward_type', ['currency', 'unit_xp', 'unlock'], $entryLocation);
      $config = $entry['config'] ?? null;
      if (!is_array($config) || array_is_list($config)) {
        throw new ContentValidationException("{$entryLocation} field 'config' must be an object.");
      }
      $configLocation = "{$entryLocation} field 'config'";
      if ($rewardType === 'currency') {
        $this->requireExactFieldSet($config, ['currency_id', 'amount'], [], $configLocation);
        $this->requireAllowedString($config, 'currency_id', ['teeth', 'raw_chaos'], $configLocation);
        $this->requireIntegerInRange($config, 'amount', 1, PHP_INT_MAX, $configLocation);
      } elseif ($rewardType === 'unit_xp') {
        $this->requireExactFieldSet($config, ['target_scope', 'amount'], [], $configLocation);
        $this->requireAllowedString($config, 'target_scope', ['participating_units'], $configLocation);
        $this->requireIntegerInRange($config, 'amount', 1, PHP_INT_MAX, $configLocation);
      } else {
        $this->requireExactFieldSet($config, ['unlock_id'], [], $configLocation);
        $this->requireStableIdWithNamespace($config, 'unlock_id', 'unlock.', $configLocation);
      }
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateItem(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition,
      ['id', 'type', 'display_name', 'description', 'category', 'rarity', 'icon_key', 'stackable'],
      ['effect', 'source_region_id', 'source_encounter_id', 'victory_drops'], $location);
    $this->requireNamespace($definition, 'item.', $location);
    $this->requireBoundedNonEmptyString($definition, 'display_name', 128, $location);
    $this->requireBoundedNonEmptyString($definition, 'description', 512, $location);
    $category = $this->requireAllowedString($definition, 'category', ['consumable', 'material'], $location);
    $this->requireAllowedString($definition, 'rarity', self::RARITIES, $location);
    $this->requireBoundedNonEmptyString($definition, 'icon_key', 128, $location);
    if (!is_bool($definition['stackable'] ?? null)) {
      throw new ContentValidationException("{$location} field 'stackable' must be a boolean.");
    }

    $effect = $definition['effect'] ?? null;
    if ($category === 'material') {
      if ($effect !== null) throw new ContentValidationException("{$location} material items must not define an effect.");
      if (array_key_exists('victory_drops', $definition)) {
        if (!isset($definition['source_region_id'])) throw new ContentValidationException("{$location} victory_drops requires source_region_id.");
        $drops = $definition['victory_drops'];
        if (!is_array($drops) || !array_is_list($drops) || $drops === []) {
          throw new ContentValidationException("{$location} victory_drops must be a non-empty list.");
        }
        $nodeTypes = [];
        foreach ($drops as $drop) {
          if (!is_array($drop) || array_is_list($drop)) throw new ContentValidationException("{$location} victory drop must be an object.");
          $this->requireExactFieldSet($drop, ['node_type', 'enemy_unit_type_ids', 'quantity'], [], "{$location} victory drop");
          $nodeType = $this->requireAllowedString($drop, 'node_type', ['combat', 'boss'], "{$location} victory drop");
          if (isset($nodeTypes[$nodeType])) throw new ContentValidationException("{$location} duplicates victory drop node type.");
          $nodeTypes[$nodeType] = true;
          $this->requireStableIdList($drop, 'enemy_unit_type_ids', 'enemy_unit_type.', false, "{$location} victory drop");
          $this->requireIntegerInRange($drop, 'quantity', 1, ClientSafeInteger::MAXIMUM, "{$location} victory drop");
        }
      }
      if (array_key_exists('source_encounter_id', $definition) && !array_key_exists('source_region_id', $definition)) {
        throw new ContentValidationException("{$location} source_encounter_id requires source_region_id.");
      }
      if (array_key_exists('source_region_id', $definition)) {
        $this->requireStableIdWithNamespace($definition, 'source_region_id', 'region.', $location);
      }
      if (array_key_exists('source_encounter_id', $definition)) {
        $this->requireStableIdWithNamespace($definition, 'source_encounter_id', 'encounter.', $location);
      }
      return;
    }
    if (array_key_exists('source_region_id', $definition) || array_key_exists('source_encounter_id', $definition)
      || array_key_exists('victory_drops', $definition)) {
      throw new ContentValidationException("{$location} consumable items cannot declare a material source.");
    }
    if (!is_array($effect) || array_is_list($effect)) {
      throw new ContentValidationException("{$location} consumable items must define an effect object.");
    }
    $this->requireExactFieldSet($effect, ['type', 'amount'], [], "{$location} field 'effect'");
    $this->requireAllowedString($effect, 'type', ['energy_restore', 'unit_heal'], "{$location} field 'effect'");
    $this->requireIntegerInRange(
      $effect,
      'amount',
      1,
      ClientSafeInteger::MAXIMUM,
      "{$location} field 'effect'",
    );
  }

  /** @param array<string, mixed> $definition */
  private function validateShopOffer(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'grant', 'price'], [], $location);
    $this->requireNamespace($definition, 'shop_offer.', $location);
    $grant = $definition['grant'] ?? null;
    if (!is_array($grant) || array_is_list($grant)) {
      throw new ContentValidationException("{$location} field 'grant' must be an object.");
    }
    $grantType = $this->requireAllowedString($grant, 'type', ['item', 'die', 'unit'], "{$location} field 'grant'");
    if ($grantType === 'item') {
      $this->requireExactFieldSet($grant, ['type', 'item_id', 'quantity'], [], "{$location} field 'grant'");
      $this->requireStableIdWithNamespace($grant, 'item_id', 'item.', "{$location} field 'grant'");
      $this->requireIntegerInRange(
        $grant,
        'quantity',
        1,
        ClientSafeInteger::MAXIMUM,
        "{$location} field 'grant'",
      );
    } elseif ($grantType === 'die') {
      $this->requireExactFieldSet($grant, ['type', 'dice_profile_id', 'size'], [], "{$location} field 'grant'");
      $this->requireStableIdWithNamespace($grant, 'dice_profile_id', 'dice_profile.', "{$location} field 'grant'");
      $this->requireIntegerInRange($grant, 'size', 4, 20, "{$location} field 'grant'");
      if (!in_array($grant['size'], self::SUPPORTED_DIE_SIZES, true)) {
        throw new ContentValidationException("{$location} field 'grant.size' must be a standard die size.");
      }
    } else {
      $this->requireExactFieldSet($grant, ['type', 'unit_type_id', 'kin_id'], [], "{$location} field 'grant'");
      $this->requireStableIdWithNamespace($grant, 'unit_type_id', 'unit_type.', "{$location} field 'grant'");
      $this->requireAllowedString($grant, 'kin_id', ['kin.goblin'], "{$location} field 'grant'");
    }
    $price = $definition['price'] ?? null;
    if (!is_array($price) || array_is_list($price)) {
      throw new ContentValidationException("{$location} field 'price' must be an object.");
    }
    $this->requireExactFieldSet($price, ['currency_id', 'amount'], [], "{$location} field 'price'");
    $this->requireAllowedString($price, 'currency_id', ['teeth'], "{$location} field 'price'");
    $this->requireIntegerInRange(
      $price,
      'amount',
      1,
      ClientSafeInteger::MAXIMUM,
      "{$location} field 'price'",
    );
  }

  /** @param array<string, mixed> $definition */
  private function validateRunNodeType(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'display_name', 'description', 'icon_key'], [], $location);
    $this->requireNamespace($definition, 'run_node_type.', $location);
    $this->requireBoundedNonEmptyString($definition, 'display_name', 128, $location);
    $this->requireBoundedNonEmptyString($definition, 'description', 512, $location);
    $this->requireBoundedNonEmptyString($definition, 'icon_key', 128, $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateRunGeneration(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition, ['id', 'type', 'algorithm', 'start_node_key', 'nodes', 'edges'], [], $location);
    $this->requireNamespace($definition, 'run_generation.', $location);
    $this->requireAllowedString($definition, 'algorithm', self::RUN_GENERATION_ALGORITHMS, $location);
    $this->requireLocalNodeKey($definition, 'start_node_key', $location);

    $nodes = $definition['nodes'] ?? null;
    if (!is_array($nodes) || !array_is_list($nodes) || $nodes === []) {
      throw new ContentValidationException("{$location} field 'nodes' must be a non-empty list.");
    }
    $nodeKeys = [];
    foreach ($nodes as $offset => $node) {
      $nodeLocation = "{$location} field 'nodes[{$offset}]'";
      if (!is_array($node) || array_is_list($node)) {
        throw new ContentValidationException("{$nodeLocation} must be an object.");
      }
      $this->requireExactFieldSet($node, ['key', 'node_type_id', 'position'], ['encounter_id', 'event_id'], $nodeLocation);
      $key = $this->requireLocalNodeKey($node, 'key', $nodeLocation);
      if (isset($nodeKeys[$key])) {
        throw new ContentValidationException("{$location} contains duplicate local node key '{$key}'.");
      }
      $nodeKeys[$key] = true;
      $this->requireStableIdWithNamespace($node, 'node_type_id', 'run_node_type.', $nodeLocation);
      if (array_key_exists('encounter_id', $node) && $node['encounter_id'] !== null) {
        $this->requireStableIdWithNamespace($node, 'encounter_id', 'encounter.', $nodeLocation);
      }
      if (array_key_exists('event_id', $node) && $node['event_id'] !== null) {
        $this->requireStableIdWithNamespace($node, 'event_id', 'event.', $nodeLocation);
      }
      $position = $node['position'] ?? null;
      if (!is_array($position) || array_is_list($position)) {
        throw new ContentValidationException("{$nodeLocation} field 'position' must be an object.");
      }
      $this->requireExactFieldSet($position, ['column', 'row'], [], "{$nodeLocation} field 'position'");
      $this->requireIntegerValueInRange($position['column'] ?? null, -1000, 1000, "{$nodeLocation} field 'position.column'");
      $this->requireIntegerValueInRange($position['row'] ?? null, -1000, 1000, "{$nodeLocation} field 'position.row'");
    }

    $edges = $definition['edges'] ?? null;
    if (!is_array($edges) || !array_is_list($edges) || $edges === []) {
      throw new ContentValidationException("{$location} field 'edges' must be a non-empty list.");
    }
    $seenEdges = [];
    foreach ($edges as $offset => $edge) {
      $edgeLocation = "{$location} field 'edges[{$offset}]'";
      if (!is_array($edge) || array_is_list($edge)) {
        throw new ContentValidationException("{$edgeLocation} must be an object.");
      }
      $this->requireExactFieldSet($edge, ['from', 'to'], [], $edgeLocation);
      $from = $this->requireLocalNodeKey($edge, 'from', $edgeLocation);
      $to = $this->requireLocalNodeKey($edge, 'to', $edgeLocation);
      if ($from === $to) {
        throw new ContentValidationException("{$location} edge '{$from}' -> '{$to}' must not be a self edge.");
      }
      $edgeKey = $from . "\0" . $to;
      if (isset($seenEdges[$edgeKey])) {
        throw new ContentValidationException("{$location} contains duplicate edge '{$from}' -> '{$to}'.");
      }
      $seenEdges[$edgeKey] = true;
      if (!isset($nodeKeys[$from]) || !isset($nodeKeys[$to])) {
        throw new ContentValidationException("{$location} edge '{$from}' -> '{$to}' references an unknown local node key.");
      }
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateKin(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'kin.', $location);
    $this->requirePresentation($definition, $location);
    $this->requireNonEmptyString($definition, 'trait_summary', $location);
    $this->requireStatBlock($definition, 'stat_modifiers', -1000, 1000, $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateUnitType(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'unit_type.', $location);
    $this->requirePresentation($definition, $location);
    $this->requireAllowedString($definition, 'role', ['frontline', 'backline', 'support', 'utility'], $location);
    $this->requireIntegerInRange($definition, 'tier', 1, 100, $location);
    $this->requireStatBlock($definition, 'base_stats', 0, 1000000, $location, true);
    $this->requireStatBlock($definition, 'growth_per_level', 0, 1000000, $location);
    $this->requireStableIdList($definition, 'ability_ids', 'ability.', false, $location);
  }

  /** @param array<string,mixed> $definition */
  private function validateEnemyUnitType(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition,
      ['id', 'type', 'display_name', 'description', 'art_key', 'role', 'stats', 'active_ability_ids', 'passive_ability_ids', 'virtual_ability_dice'], [], $location);
    $this->requireNamespace($definition, 'enemy_unit_type.', $location);
    $this->requirePresentation($definition, $location);
    $this->requireAllowedString($definition, 'role', ['frontline', 'backline', 'support', 'utility'], $location);
    $this->requireStatBlock($definition, 'stats', 0, 1000000, $location, true);
    $this->requireStableIdList($definition, 'active_ability_ids', 'ability.', false, $location);
    $this->requireStableIdList($definition, 'passive_ability_ids', 'ability.', true, $location);
    $die = $definition['virtual_ability_dice'] ?? null;
    if (!is_array($die) || array_is_list($die)) throw new ContentValidationException("{$location} virtual_ability_dice must be an object.");
    $this->requireExactFieldSet($die, ['sides', 'profile_id'], [], "{$location} virtual_ability_dice");
    $this->requireIntegerInRange($die, 'sides', 6, 6, "{$location} virtual_ability_dice");
    $this->requireStableIdWithNamespace($die, 'profile_id', 'dice_profile.', "{$location} virtual_ability_dice");
  }

  /** @param array<string,mixed> $definition */
  private function validateEncounter(array $definition, string $location): void
  {
    $this->requireExactFieldSet($definition,
      ['id', 'type', 'kind', 'region_id', 'display_name', 'description', 'difficulty', 'combatants'], [], $location);
    $this->requireNamespace($definition, 'encounter.', $location);
    $this->requireStableIdWithNamespace($definition, 'region_id', 'region.', $location);
    $this->requireAllowedString($definition, 'kind', ['combat', 'boss'], $location);
    $this->requireBoundedNonEmptyString($definition, 'display_name', 128, $location);
    $this->requireBoundedNonEmptyString($definition, 'description', 512, $location);
    $this->requireIntegerInRange($definition, 'difficulty', 1, 100, $location);
    $combatants = $definition['combatants'] ?? null;
    if (!is_array($combatants) || !array_is_list($combatants) || $combatants === []) {
      throw new ContentValidationException("{$location} combatants must be a non-empty list.");
    }
    $keys = $cells = [];
    foreach ($combatants as $index => $combatant) {
      $where = "{$location} combatants[{$index}]";
      if (!is_array($combatant) || array_is_list($combatant)) throw new ContentValidationException("{$where} must be an object.");
      $this->requireExactFieldSet($combatant, ['key', 'enemy_unit_type_id', 'position'], [], $where);
      $key = $this->requireLocalNodeKey($combatant, 'key', $where);
      $this->requireStableIdWithNamespace($combatant, 'enemy_unit_type_id', 'enemy_unit_type.', $where);
      $position = $combatant['position'] ?? null;
      if (!is_array($position) || array_is_list($position)) throw new ContentValidationException("{$where} position must be an object.");
      $this->requireExactFieldSet($position, ['x', 'y'], [], "{$where} position");
      $this->requireIntegerInRange($position, 'x', 0, 2, "{$where} position");
      $this->requireIntegerInRange($position, 'y', 0, 2, "{$where} position");
      $cell = $position['x'] . ':' . $position['y'];
      if (isset($keys[$key]) || isset($cells[$cell])) throw new ContentValidationException("{$location} has duplicate combatant key or occupied position.");
      $keys[$key] = $cells[$cell] = true;
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateAbility(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'ability.', $location);
    if (array_key_exists('server_only', $definition) && !is_bool($definition['server_only'])) {
      throw new ContentValidationException("{$location} field 'server_only' must be a boolean.");
    }
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'description', $location);
    $this->requireNonEmptyString($definition, 'icon_key', $location);
    $kind = $this->requireAllowedString($definition, 'kind', ['active', 'passive'], $location);
    $this->requireIntegerInRange($definition, 'dice_slot_count', $kind === 'active' ? 1 : 0, $kind === 'active' ? 8 : 0, $location);
    if ($kind === 'active') {
      $this->requireIntegerInRange($definition, 'action_delay', 1, 10000, $location);
      $this->requireIntegerInRange($definition, 'resolution_priority', 0, 10000, $location);
      $targetRule = $this->requireNonEmptyString($definition, 'target_rule', $location);
      if (preg_match(self::HANDLER_PATTERN, $targetRule) !== 1) {
        throw new ContentValidationException("{$location} field 'target_rule' must be a snake-case target rule.");
      }
    } elseif (array_key_exists('action_delay', $definition) || array_key_exists('resolution_priority', $definition) || array_key_exists('target_rule', $definition)) {
      throw new ContentValidationException("{$location} passive ability must not define active scheduling or targeting fields.");
    }
    $handlerId = $this->requireNonEmptyString($definition, 'handler_id', $location);
    if (preg_match(self::HANDLER_PATTERN, $handlerId) !== 1) {
      throw new ContentValidationException("{$location} field 'handler_id' must be a snake-case handler id.");
    }
    $this->requireConfigObject($definition, 'handler_config', $location);
    try {
      if ($kind === 'active' && in_array($handlerId, CombatRules::ACTIVE_HANDLERS, true)) {
        CombatRules::validateTargetRule($handlerId, $definition['target_rule']);
        CombatRules::validateAbilityConfig($handlerId, $definition['handler_config']);
      }
      if ($kind === 'passive' && in_array($handlerId, CombatRules::PASSIVE_HANDLERS, true)) {
        CombatRules::validatePassive($handlerId, $definition['handler_config']);
      }
    } catch (InvalidArgumentException $e) {
      throw new ContentValidationException("{$location} has invalid current combat handler configuration: {$e->getMessage()}", 0, $e);
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateDiceMaterial(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'dice_material.', $location);
    $this->requirePresentation($definition, $location);
    $this->requireDieSizeList($definition, 'allowed_sizes', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateDiceAspect(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'dice_aspect.', $location);
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'description', $location);
    $this->requireDieSizeList($definition, 'allowed_sizes', $location);
    $effectId = $this->requireNonEmptyString($definition, 'effect_id', $location);
    if (preg_match(self::HANDLER_PATTERN, $effectId) !== 1) {
      throw new ContentValidationException("{$location} field 'effect_id' must be a snake-case effect id.");
    }
    $this->requireConfigObject($definition, 'effect_config', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateDiceProfile(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'dice_profile.', $location);
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireStableIdWithNamespace($definition, 'material_id', 'dice_material.', $location);
    $this->requireAllowedString($definition, 'rarity', self::RARITIES, $location);
    $this->requireStableIdList($definition, 'aspect_ids', 'dice_aspect.', true, $location);
    $this->requireDieSizeList($definition, 'allowed_sizes', $location);
  }

  /** @param array<string, mixed> $definition */
  private function requirePresentation(array $definition, string $location): void
  {
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'description', $location);
    $this->requireNonEmptyString($definition, 'art_key', $location);
  }

  /** @param array<string, mixed> $definition */
  private function requireNamespace(array $definition, string $namespace, string $location): void
  {
    if (!str_starts_with((string)$definition['id'], $namespace)) {
      throw new ContentValidationException("{$location} id must use the {$namespace} namespace.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireStatBlock(array $definition, string $field, int $minimum, int $maximum, string $location, bool $positiveHp = false): void
  {
    $value = $definition[$field] ?? null;
    if (!is_array($value) || array_is_list($value) || count($value) !== count(self::STAT_FIELDS)) {
      throw new ContentValidationException("{$location} field '{$field}' must contain exactly HP, Attack, Defense, Precision, and Resolve.");
    }
    foreach (self::STAT_FIELDS as $stat) {
      if (!array_key_exists($stat, $value) || !is_int($value[$stat]) || $value[$stat] < $minimum || $value[$stat] > $maximum) {
        throw new ContentValidationException("{$location} field '{$field}.{$stat}' must be an integer from {$minimum} to {$maximum}.");
      }
    }
    if ($positiveHp && $value['hp'] < 1) {
      throw new ContentValidationException("{$location} field '{$field}.hp' must be at least 1.");
    }
  }

  /** @param array<string, mixed> $definition
   *  @param list<string> $allowed
   */
  private function requireAllowedString(array $definition, string $field, array $allowed, string $location): string
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || !in_array($value, $allowed, true)) {
      throw new ContentValidationException("{$location} field '{$field}' must be one of: " . implode(', ', $allowed) . '.');
    }
    return $value;
  }

  /** @param array<string, mixed> $definition */
  private function requireStableIdWithNamespace(array $definition, string $field, string $namespace, string $location): void
  {
    $this->requireStableId($definition, $field, $location);
    if (!str_starts_with($definition[$field], $namespace)) {
      throw new ContentValidationException("{$location} field '{$field}' must use the {$namespace} namespace.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireStableIdList(array $definition, string $field, string $namespace, bool $allowEmpty, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_array($value) || !array_is_list($value) || (!$allowEmpty && $value === [])) {
      throw new ContentValidationException("{$location} field '{$field}' must be " . ($allowEmpty ? 'a' : 'a non-empty') . ' list of stable ids.');
    }
    $seen = [];
    foreach ($value as $id) {
      if (!is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1 || !str_starts_with($id, $namespace)) {
        throw new ContentValidationException("{$location} field '{$field}' contains an invalid {$namespace} reference.");
      }
      if (isset($seen[$id])) {
        throw new ContentValidationException("{$location} field '{$field}' contains duplicate reference '{$id}'.");
      }
      $seen[$id] = true;
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireDieSizeList(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_array($value) || !array_is_list($value) || $value === []) {
      throw new ContentValidationException("{$location} field '{$field}' must be a non-empty die-size list.");
    }
    $seen = [];
    foreach ($value as $size) {
      if (!is_int($size) || !in_array($size, self::SUPPORTED_DIE_SIZES, true)) {
        throw new ContentValidationException("{$location} field '{$field}' contains unsupported die size '" . (is_scalar($size) ? (string)$size : gettype($size)) . "'.");
      }
      if (isset($seen[$size])) {
        throw new ContentValidationException("{$location} field '{$field}' contains duplicate die size '{$size}'.");
      }
      $seen[$size] = true;
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireConfigObject(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_array($value) || array_is_list($value)) {
      throw new ContentValidationException("{$location} field '{$field}' must be an object.");
    }
    $this->validateConfigValue($value, "{$location} field '{$field}'", 0);
  }

  private function validateConfigValue(mixed $value, string $location, int $depth): void
  {
    if ($depth > 4) {
      throw new ContentValidationException("{$location} exceeds the supported configuration depth.");
    }
    if (is_float($value) && !is_finite($value)) {
      throw new ContentValidationException("{$location} contains a non-finite number.");
    }
    if (!is_array($value)) {
      if ($value !== null && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
        throw new ContentValidationException("{$location} contains an unsupported value.");
      }
      return;
    }
    foreach ($value as $key => $child) {
      if (!is_int($key) && (!is_string($key) || trim($key) === '')) {
        throw new ContentValidationException("{$location} contains an invalid key.");
      }
      $this->validateConfigValue($child, $location, $depth + 1);
    }
  }

  /** @param array<string, array<string, mixed>> $definitions
   *  @return array<string, mixed>
   */
  private function requireReferenceType(array $definitions, string $sourceId, string $field, string $targetId, string $expectedType): array
  {
    $target = $definitions[$targetId] ?? null;
    if (!is_array($target)) {
      throw new ContentValidationException("{$sourceId} {$field} references missing {$expectedType} '{$targetId}'.");
    }
    if (($target['type'] ?? null) !== $expectedType) {
      throw new ContentValidationException("{$sourceId} {$field} reference '{$targetId}' must be type {$expectedType}.");
    }
    return $target;
  }

  /** @param array<string, mixed> $profile
   *  @param array<string, mixed> $material
   *  @param list<array<string, mixed>> $aspects
   */
  private function validateProfileSizes(string $profileId, array $profile, array $material, array $aspects): void
  {
    $effective = $material['allowed_sizes'];
    foreach ($aspects as $aspect) $effective = array_values(array_intersect($effective, $aspect['allowed_sizes']));
    if ($effective === []) {
      throw new ContentValidationException("{$profileId} material/aspect combination has no legal effective die sizes.");
    }

    foreach ($profile['allowed_sizes'] as $size) {
      if (!in_array($size, $material['allowed_sizes'], true)) {
        throw new ContentValidationException("{$profileId} declares die size {$size}, which material {$material['id']} disallows.");
      }
      foreach ($aspects as $aspect) {
        if (!in_array($size, $aspect['allowed_sizes'], true)) {
          throw new ContentValidationException("{$profileId} declares die size {$size}, which aspect {$aspect['id']} disallows.");
        }
      }
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateRunGenerationConnectivity(string $id, array $definition): void
  {
    $nodesByKey = [];
    $adjacency = [];
    foreach ($definition['nodes'] as $node) {
      $key = (string)$node['key'];
      $nodesByKey[$key] = $node;
      $adjacency[$key] = [];
    }
    foreach ($definition['edges'] as $edge) {
      $adjacency[(string)$edge['from']][] = (string)$edge['to'];
    }

    $start = (string)$definition['start_node_key'];
    if (!isset($nodesByKey[$start])) {
      throw new ContentValidationException("{$id} start_node_key references missing local node '{$start}'.");
    }

    $reachable = [];
    $pending = [$start];
    while ($pending !== []) {
      $key = array_shift($pending);
      if (isset($reachable[$key])) continue;
      $reachable[$key] = true;
      foreach ($adjacency[$key] as $next) $pending[] = $next;
    }

    $exitKeys = [];
    foreach ($nodesByKey as $key => $node) {
      if (($node['node_type_id'] ?? null) === 'run_node_type.exit') $exitKeys[] = $key;
      if (!isset($reachable[$key])) {
        throw new ContentValidationException("{$id} required node '{$key}' is disconnected from start '{$start}'.");
      }
    }
    if (count($exitKeys) !== 1) {
      throw new ContentValidationException("{$id} must contain exactly one run_node_type.exit node.");
    }
    if (!isset($reachable[$exitKeys[0]])) {
      throw new ContentValidationException("{$id} exit node '{$exitKeys[0]}' is unreachable from start '{$start}'.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function validateFarmGenerationStructure(array $definition): void
  {
    $expectedTypes = [
      'run_node_type.combat',
      'run_node_type.loot',
      'run_node_type.rest',
      'run_node_type.boss',
      'run_node_type.exit',
    ];
    $nodes = $definition['nodes'];
    $actualTypes = array_map(static fn(array $node): string => (string)$node['node_type_id'], $nodes);
    if ($actualTypes !== $expectedTypes) {
      throw new ContentValidationException('region.the_farm generation must use the ordered combat, loot, rest, boss, exit structure.');
    }
    if ((string)$definition['start_node_key'] !== (string)$nodes[0]['key']) {
      throw new ContentValidationException('region.the_farm generation must start at its combat node.');
    }
    if (($nodes[0]['encounter_id'] ?? null) !== 'encounter.the_farm_mud_combat_1') {
      throw new ContentValidationException('region.the_farm first combat node must reference its standard mud encounter.');
    }
    if (($nodes[1]['event_id'] ?? null) !== 'event.farm_loot_completed') {
      throw new ContentValidationException('region.the_farm Loot node must reference its completion event.');
    }
    if (($nodes[3]['encounter_id'] ?? null) !== 'encounter.the_farm_mud_boss_1') {
      throw new ContentValidationException('region.the_farm Boss node must reference its Mudking encounter.');
    }
    if (($nodes[3]['event_id'] ?? null) !== 'event.farm_boss_completed') {
      throw new ContentValidationException('region.the_farm Boss node must reference its completion event.');
    }

    $expectedEdges = [];
    for ($index = 0; $index < count($nodes) - 1; $index++) {
      $expectedEdges[] = ['from' => (string)$nodes[$index]['key'], 'to' => (string)$nodes[$index + 1]['key']];
    }
    if ($definition['edges'] !== $expectedEdges) {
      throw new ContentValidationException('region.the_farm generation must be one connected linear path through boss to exit.');
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireExactId(array $definition, string $id, string $location): void
  {
    if ($definition['id'] !== $id) {
      throw new ContentValidationException("{$location} must use stable id '{$id}'.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireStableId(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || preg_match(self::ID_PATTERN, $value) !== 1) {
      throw new ContentValidationException("{$location} field '{$field}' must be a stable id.");
    }
  }

  /** @param array<string, mixed> $definition
   *  @param list<string> $required
   *  @param list<string> $optional
   */
  private function requireExactFieldSet(array $definition, array $required, array $optional, string $location): void
  {
    $actual = array_keys($definition);
    $missing = array_values(array_diff($required, $actual));
    $unexpected = array_values(array_diff($actual, [...$required, ...$optional]));
    if ($missing !== [] || $unexpected !== []) {
      throw new ContentValidationException("{$location} has an invalid field set.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireLocalNodeKey(array $definition, string $field, string $location): string
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || strlen($value) > 64 || preg_match(self::LOCAL_NODE_KEY_PATTERN, $value) !== 1) {
      throw new ContentValidationException("{$location} field '{$field}' must be a snake-case local node key of at most 64 characters.");
    }
    return $value;
  }

  /** @param array<string, mixed> $definition */
  private function requireBoundedNonEmptyString(array $definition, string $field, int $maximumLength, string $location): string
  {
    $value = $this->requireNonEmptyString($definition, $field, $location);
    if (strlen($value) > $maximumLength) {
      throw new ContentValidationException("{$location} field '{$field}' must be at most {$maximumLength} characters.");
    }
    return $value;
  }

  private function requireIntegerValueInRange(mixed $value, int $minimum, int $maximum, string $location): void
  {
    if (!is_int($value) || $value < $minimum || $value > $maximum) {
      throw new ContentValidationException("{$location} must be an integer from {$minimum} to {$maximum}.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireNonEmptyString(array $definition, string $field, string $location): string
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || trim($value) === '') {
      throw new ContentValidationException("{$location} field '{$field}' must be a non-empty string.");
    }
    return $value;
  }

  /** @param array<string, mixed> $definition */
  private function requireIntegerInRange(array $definition, string $field, int $minimum, int $maximum, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_int($value) || $value < $minimum || $value > $maximum) {
      throw new ContentValidationException("{$location} field '{$field}' must be an integer from {$minimum} to {$maximum}.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requirePositiveNumber(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value <= 0) {
      throw new ContentValidationException("{$location} field '{$field}' must be a positive number.");
    }
  }
}
