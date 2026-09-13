<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

final class ContentValidator
{
  private const ID_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/';
  private const HANDLER_PATTERN = '/^[a-z][a-z0-9_]*$/';
  private const STAT_FIELDS = ['hp', 'attack', 'defense', 'precision', 'resolve'];
  private const SUPPORTED_DIE_SIZES = [4, 6, 8, 10, 12, 20];
  private const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
  private const RUN_GENERATION_ALGORITHMS = ['fixed_graph_v1'];
  private const LOCAL_NODE_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

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
      'ability' => $this->validateAbility($definition, $location),
      'dice_material' => $this->validateDiceMaterial($definition, $location),
      'dice_aspect' => $this->validateDiceAspect($definition, $location),
      'dice_profile' => $this->validateDiceProfile($definition, $location),
      'run_node_type' => $this->validateRunNodeType($definition, $location),
      'run_generation' => $this->validateRunGeneration($definition, $location),
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

    foreach ($definitions as $id => $definition) {
      if (($definition['type'] ?? null) === 'region') {
        $this->requireReferenceType($definitions, $id, 'run_generation_id', $definition['run_generation_id'], 'run_generation');
      }

      if (($definition['type'] ?? null) === 'unit_type') {
        foreach ($definition['ability_ids'] as $abilityId) {
          $this->requireReferenceType($definitions, $id, 'ability_ids', $abilityId, 'ability');
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
    $this->requireStableId($definition, 'starting_region_id', $location);
  }

  /** @param array<string, mixed> $definition */
  private function validateRegion(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'region.', $location);
    $this->requireNonEmptyString($definition, 'display_name', $location);
    $this->requireNonEmptyString($definition, 'art_key', $location);
    $this->requireStableIdWithNamespace($definition, 'run_generation_id', 'run_generation.', $location);
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
      $this->requireExactFieldSet($node, ['key', 'node_type_id', 'position'], ['encounter_id'], $nodeLocation);
      $key = $this->requireLocalNodeKey($node, 'key', $nodeLocation);
      if (isset($nodeKeys[$key])) {
        throw new ContentValidationException("{$location} contains duplicate local node key '{$key}'.");
      }
      $nodeKeys[$key] = true;
      $this->requireStableIdWithNamespace($node, 'node_type_id', 'run_node_type.', $nodeLocation);
      if (array_key_exists('encounter_id', $node) && $node['encounter_id'] !== null) {
        $this->requireStableIdWithNamespace($node, 'encounter_id', 'encounter.', $nodeLocation);
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

  /** @param array<string, mixed> $definition */
  private function validateAbility(array $definition, string $location): void
  {
    $this->requireNamespace($definition, 'ability.', $location);
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
