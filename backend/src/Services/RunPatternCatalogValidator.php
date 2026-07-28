<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use RuntimeException;

final class RunPatternCatalogValidator
{
  private const VALID_NODE_TYPES = ['combat', 'loot', 'rest', 'boss', 'exit', 'dialogue', 'hazard', 'shrine', 'chaos'];
  private const VALID_TRANSFORMS = ['identity', 'mirror_y'];
  private const VALID_PHASES = ['start', 'spine', 'branch', 'cap', 'boss_approach', 'terminal'];
  private const VALID_SOCKET_KINDS = ['entry', 'exit'];
  private const VALID_DIRECTIONS = ['left', 'right', 'up', 'down'];
  private const VALID_PATHS = ['spine', 'branch'];
  private const REQUIRED_BUDGETS = [
    'total_nodes',
    'spine_nodes',
    'branch_nodes',
    'combat_nodes',
    'reward_nodes',
    'recovery_nodes',
    'hazard_nodes',
    'shrine_nodes',
    'chaos_nodes',
    'branch_count',
    'frontier_count',
    'pattern_instances',
  ];

  /**
   * @return array{
   *   valid:bool,
   *   errors:list<string>,
   *   catalog_hash:string,
   *   pattern_count:int,
   *   rule_count:int,
   *   profile_count:int,
   *   variant_count:int
   * }
   */
  public function validateDefaultCatalog(): array
  {
    return $this->validateDirectory(dirname(__DIR__, 2) . '/data/run-patterns');
  }

  /**
   * @return array{
   *   valid:bool,
   *   errors:list<string>,
   *   catalog_hash:string,
   *   pattern_count:int,
   *   rule_count:int,
   *   profile_count:int,
   *   variant_count:int
   * }
   */
  public function validateDirectory(string $catalogRoot): array
  {
    $errors = [];
    $patterns = $this->loadPatterns($catalogRoot, $errors);
    $rules = $this->loadRules($catalogRoot, $errors);
    $profiles = $this->loadProfiles($catalogRoot, $errors);

    $patternIndex = [];
    foreach ($patterns as $pattern) {
      $key = $this->patternKey($pattern);
      if (isset($patternIndex[$key])) {
        $errors[] = "Duplicate pattern {$key}.";
        continue;
      }
      $patternIndex[$key] = $pattern;
    }

    $rulesByPattern = [];
    foreach ($rules as $rule) {
      $patternKey = $this->patternKeyFromParts(
        (string)($rule['pattern_slug'] ?? ''),
        (int)($rule['pattern_version'] ?? 0)
      );
      $rulesByPattern[$patternKey][] = $rule;
      $this->validateRule($rule, $patternIndex, $errors);
    }

    foreach ($patterns as $pattern) {
      $key = $this->patternKey($pattern);
      $patternRules = $rulesByPattern[$key] ?? [];
      $this->validatePattern($pattern, $patternRules, $errors);
    }

    foreach ($profiles as $profile) {
      $this->validateProfile($profile, $patternIndex, $errors);
    }

    return [
      'valid' => count($errors) === 0,
      'errors' => $errors,
      'catalog_hash' => $this->catalogHash($patterns, $rules, $profiles),
      'pattern_count' => count($patterns),
      'rule_count' => count($rules),
      'profile_count' => count($profiles),
      'variant_count' => $this->variantCount($patterns),
    ];
  }

  /**
   * @param list<string> $errors
   * @return list<array<string,mixed>>
   */
  private function loadPatterns(string $catalogRoot, array &$errors): array
  {
    $path = $catalogRoot . '/shared/patterns.json';
    $data = $this->decodeJsonFile($path, $errors);
    $patterns = is_array($data['patterns'] ?? null) ? $data['patterns'] : [];
    if ($patterns === []) {
      $errors[] = "{$path} must define at least one pattern.";
    }

    return array_values(array_filter($patterns, 'is_array'));
  }

  /**
   * @param list<string> $errors
   * @return list<array<string,mixed>>
   */
  private function loadRules(string $catalogRoot, array &$errors): array
  {
    $rules = [];
    foreach (glob($catalogRoot . '/*/rules.json') ?: [] as $path) {
      $data = $this->decodeJsonFile($path, $errors);
      $regionSlug = (string)($data['region_slug'] ?? '');
      foreach (is_array($data['rules'] ?? null) ? $data['rules'] : [] as $rule) {
        if (!is_array($rule)) {
          continue;
        }
        $rules[] = [
          ...$rule,
          'region_slug' => $regionSlug,
          'generator_version' => (string)($data['generator_version'] ?? ''),
        ];
      }
    }

    if ($rules === []) {
      $errors[] = 'Pattern catalog must define at least one region rule file.';
    }

    return $rules;
  }

  /**
   * @param list<string> $errors
   * @return list<array<string,mixed>>
   */
  private function loadProfiles(string $catalogRoot, array &$errors): array
  {
    $profiles = [];
    foreach (glob($catalogRoot . '/profiles/*.json') ?: [] as $path) {
      $data = $this->decodeJsonFile($path, $errors);
      if (is_array($data)) {
        $profiles[] = $data;
      }
    }

    if ($profiles === []) {
      $errors[] = 'Pattern catalog must define at least one generation profile.';
    }

    return $profiles;
  }

  /**
   * @param list<string> $errors
   * @return array<string,mixed>
   */
  private function decodeJsonFile(string $path, array &$errors): array
  {
    if (!is_file($path)) {
      $errors[] = "Missing JSON file {$path}.";
      return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
      $errors[] = "Unable to read {$path}.";
      return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      $errors[] = "Invalid JSON in {$path}.";
      return [];
    }

    return $data;
  }

  /**
   * @param array<string,mixed> $pattern
   * @param list<array<string,mixed>> $rules
   * @param list<string> $errors
   */
  private function validatePattern(array $pattern, array $rules, array &$errors): void
  {
    $key = $this->patternKey($pattern);
    $status = (string)($pattern['status'] ?? '');
    if ($status !== 'enabled') {
      return;
    }

    if (!preg_match('/^[a-z0-9_]+$/', (string)($pattern['slug'] ?? ''))) {
      $errors[] = "{$key} must use a stable lowercase slug.";
    }
    if ((int)($pattern['version'] ?? 0) <= 0) {
      $errors[] = "{$key} must use a positive version.";
    }
    if ($rules === []) {
      $errors[] = "{$key} must have at least one region rule.";
    }

    $nodes = array_values(array_filter(is_array($pattern['nodes'] ?? null) ? $pattern['nodes'] : [], 'is_array'));
    $nodeKeys = [];
    $occupied = [];
    foreach ($nodes as $node) {
      $nodeKey = (string)($node['key'] ?? '');
      if ($nodeKey === '' || isset($nodeKeys[$nodeKey])) {
        $errors[] = "{$key} has missing or duplicate node key {$nodeKey}.";
      }
      $nodeKeys[$nodeKey] = true;

      $nodeType = (string)($node['type'] ?? '');
      if (!in_array($nodeType, self::VALID_NODE_TYPES, true)) {
        $errors[] = "{$key}.{$nodeKey} has invalid node type {$nodeType}.";
      }
      if (!is_int($node['x'] ?? null) || !is_int($node['y'] ?? null)) {
        $errors[] = "{$key}.{$nodeKey} must use integer coordinates.";
      }

      $cell = (string)($node['x'] ?? '') . ':' . (string)($node['y'] ?? '');
      if (isset($occupied[$cell])) {
        $errors[] = "{$key} has multiple nodes at local cell {$cell}.";
      }
      $occupied[$cell] = true;
    }
    if ($nodes === []) {
      $errors[] = "{$key} must define at least one node.";
    }

    $edges = array_values(array_filter(is_array($pattern['edges'] ?? null) ? $pattern['edges'] : [], 'is_array'));
    foreach ($edges as $edge) {
      $from = (string)($edge['from'] ?? '');
      $to = (string)($edge['to'] ?? '');
      if (!isset($nodeKeys[$from]) || !isset($nodeKeys[$to])) {
        $errors[] = "{$key} has an edge with an unknown endpoint {$from}->{$to}.";
      }
      if ($from === $to) {
        $errors[] = "{$key} has a self-cycle edge on {$from}.";
      }
    }
    if ($this->hasCycle($edges)) {
      $errors[] = "{$key} contains an internal cycle.";
    }

    $sockets = array_values(array_filter(is_array($pattern['sockets'] ?? null) ? $pattern['sockets'] : [], 'is_array'));
    $socketIds = [];
    $hasEntry = false;
    $hasSpineExit = false;
    foreach ($sockets as $socket) {
      $socketId = (string)($socket['id'] ?? '');
      if ($socketId === '' || isset($socketIds[$socketId])) {
        $errors[] = "{$key} has missing or duplicate socket id {$socketId}.";
      }
      $socketIds[$socketId] = true;

      $kind = (string)($socket['kind'] ?? '');
      if (!in_array($kind, self::VALID_SOCKET_KINDS, true)) {
        $errors[] = "{$key}.{$socketId} has invalid socket kind {$kind}.";
      }
      $node = (string)($socket['node'] ?? '');
      if (!isset($nodeKeys[$node])) {
        $errors[] = "{$key}.{$socketId} references unknown node {$node}.";
      }
      $direction = (string)($socket['direction'] ?? '');
      if (!in_array($direction, self::VALID_DIRECTIONS, true)) {
        $errors[] = "{$key}.{$socketId} has invalid direction {$direction}.";
      }
      $paths = is_array($socket['path_eligibility'] ?? null) ? $socket['path_eligibility'] : [];
      if ($paths === []) {
        $errors[] = "{$key}.{$socketId} must define path eligibility.";
      }
      foreach ($paths as $path) {
        if (!in_array($path, self::VALID_PATHS, true)) {
          $errors[] = "{$key}.{$socketId} has invalid path eligibility {$path}.";
        }
      }
      $hasEntry = $hasEntry || $kind === 'entry';
      $hasSpineExit = $hasSpineExit || ($kind === 'exit' && in_array('spine', $paths, true));
    }

    $phases = array_values(array_unique(array_map(static fn(array $rule): string => (string)$rule['allowed_phase'], $rules)));
    if (!$hasEntry && !in_array('start', $phases, true)) {
      $errors[] = "{$key} must define an entry socket unless it is a start pattern.";
    }
    if (in_array('spine', $phases, true) && !$hasSpineExit) {
      $errors[] = "{$key} is spine-eligible but has no spine exit socket.";
    }
    if (!$this->nodesAreReachableFromEntries($nodes, $edges, $sockets, in_array('start', $phases, true))) {
      $errors[] = "{$key} has nodes that are not reachable from an entry socket.";
    }
    if (!$this->exitsAreReachableFromEntries($edges, $sockets)) {
      $errors[] = "{$key} has exit sockets that are not reachable from every entry socket.";
    }

    $hasTerminalNode = false;
    foreach ($nodes as $node) {
      $nodeType = (string)($node['type'] ?? '');
      $hasTerminalNode = $hasTerminalNode || $nodeType === 'boss' || $nodeType === 'exit';
    }
    if ($hasTerminalNode && array_diff($phases, ['terminal']) !== []) {
      $errors[] = "{$key} contains boss/exit nodes outside terminal phase.";
    }

    $transforms = is_array($pattern['allowed_transforms'] ?? null) ? $pattern['allowed_transforms'] : [];
    if (!in_array('identity', $transforms, true)) {
      $errors[] = "{$key} must explicitly allow identity transform.";
    }
    foreach ($transforms as $transform) {
      if (!in_array($transform, self::VALID_TRANSFORMS, true)) {
        $errors[] = "{$key} allows unsupported transform {$transform}.";
      }
    }
  }

  /**
   * @param array<string,mixed> $rule
   * @param array<string,array<string,mixed>> $patternIndex
   * @param list<string> $errors
   */
  private function validateRule(array $rule, array $patternIndex, array &$errors): void
  {
    $patternKey = $this->patternKeyFromParts((string)($rule['pattern_slug'] ?? ''), (int)($rule['pattern_version'] ?? 0));
    if (!isset($patternIndex[$patternKey])) {
      $errors[] = "Rule references unknown pattern {$patternKey}.";
    }
    if ((string)($rule['region_slug'] ?? '') === '') {
      $errors[] = "Rule for {$patternKey} must define a region slug.";
    }
    if ((string)($rule['generator_version'] ?? '') !== 'pattern-v1') {
      $errors[] = "Rule for {$patternKey} must target pattern-v1.";
    }
    if ((int)($rule['base_weight'] ?? 0) <= 0) {
      $errors[] = "Rule for {$patternKey} must define positive base_weight.";
    }
    if (!in_array((string)($rule['allowed_phase'] ?? ''), self::VALID_PHASES, true)) {
      $errors[] = "Rule for {$patternKey} has invalid phase.";
    }
    if ((int)($rule['min_depth'] ?? -1) < 0) {
      $errors[] = "Rule for {$patternKey} has invalid min_depth.";
    }
  }

  /**
   * @param array<string,mixed> $profile
   * @param array<string,array<string,mixed>> $patternIndex
   * @param list<string> $errors
   */
  private function validateProfile(array $profile, array $patternIndex, array &$errors): void
  {
    $regionSlug = (string)($profile['region_slug'] ?? '');
    if ($regionSlug === '') {
      $errors[] = 'Generation profile must define region_slug.';
    }
    if ((string)($profile['generator_version'] ?? '') !== 'pattern-v1') {
      $errors[] = "{$regionSlug} profile must target pattern-v1.";
    }
    if ((int)($profile['profile_version'] ?? 0) <= 0) {
      $errors[] = "{$regionSlug} profile must define positive profile_version.";
    }

    $budgets = is_array($profile['budgets'] ?? null) ? $profile['budgets'] : [];
    foreach (self::REQUIRED_BUDGETS as $budgetKey) {
      $budget = is_array($budgets[$budgetKey] ?? null) ? $budgets[$budgetKey] : null;
      if ($budget === null) {
        $errors[] = "{$regionSlug} profile missing {$budgetKey} budget.";
        continue;
      }
      $min = (int)($budget['min'] ?? -1);
      $target = (int)($budget['target'] ?? -1);
      $max = (int)($budget['max'] ?? -1);
      if ($min < 0 || $target < $min || $max < $target) {
        $errors[] = "{$regionSlug} profile budget {$budgetKey} must satisfy min <= target <= max.";
      }
    }

    $requirements = is_array($profile['requirements'] ?? null) ? $profile['requirements'] : [];
    $fallbackPatterns = is_array($requirements['fallback_patterns'] ?? null) ? $requirements['fallback_patterns'] : [];
    foreach ($fallbackPatterns as $fallbackSlug) {
      $exists = false;
      foreach ($patternIndex as $pattern) {
        if ((string)($pattern['slug'] ?? '') === (string)$fallbackSlug) {
          $exists = true;
          break;
        }
      }
      if (!$exists) {
        $errors[] = "{$regionSlug} profile references missing fallback pattern {$fallbackSlug}.";
      }
    }
  }

  /**
   * @param list<array<string,mixed>> $edges
   */
  private function hasCycle(array $edges): bool
  {
    $children = [];
    foreach ($edges as $edge) {
      $children[(string)($edge['from'] ?? '')][] = (string)($edge['to'] ?? '');
    }

    $visiting = [];
    $visited = [];
    $visit = function (string $node) use (&$visit, &$children, &$visiting, &$visited): bool {
      if (isset($visiting[$node])) {
        return true;
      }
      if (isset($visited[$node])) {
        return false;
      }
      $visiting[$node] = true;
      foreach ($children[$node] ?? [] as $child) {
        if ($visit($child)) {
          return true;
        }
      }
      unset($visiting[$node]);
      $visited[$node] = true;
      return false;
    };

    foreach (array_keys($children) as $node) {
      if ($visit($node)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param list<array<string,mixed>> $nodes
   * @param list<array<string,mixed>> $edges
   * @param list<array<string,mixed>> $sockets
   */
  private function nodesAreReachableFromEntries(array $nodes, array $edges, array $sockets, bool $isStartPattern): bool
  {
    $nodeKeys = array_map(static fn(array $node): string => (string)($node['key'] ?? ''), $nodes);
    $startNodes = $this->entrySocketNodes($sockets);
    if ($startNodes === [] && $isStartPattern && $nodeKeys !== []) {
      $startNodes = [$nodeKeys[0]];
    }
    if ($startNodes === []) {
      return false;
    }

    $reachable = $this->reachableNodes($edges, $startNodes);
    foreach ($nodeKeys as $nodeKey) {
      if ($nodeKey !== '' && !isset($reachable[$nodeKey])) {
        return false;
      }
    }

    return true;
  }

  /**
   * @param list<array<string,mixed>> $edges
   * @param list<array<string,mixed>> $sockets
   */
  private function exitsAreReachableFromEntries(array $edges, array $sockets): bool
  {
    $entryNodes = $this->entrySocketNodes($sockets);
    $exitNodes = [];
    foreach ($sockets as $socket) {
      if ((string)($socket['kind'] ?? '') === 'exit') {
        $exitNodes[] = (string)($socket['node'] ?? '');
      }
    }
    if ($entryNodes === [] || $exitNodes === []) {
      return true;
    }

    foreach ($entryNodes as $entryNode) {
      $reachable = $this->reachableNodes($edges, [$entryNode]);
      foreach ($exitNodes as $exitNode) {
        if ($exitNode !== '' && !isset($reachable[$exitNode])) {
          return false;
        }
      }
    }

    return true;
  }

  /**
   * @param list<array<string,mixed>> $sockets
   * @return list<string>
   */
  private function entrySocketNodes(array $sockets): array
  {
    $nodes = [];
    foreach ($sockets as $socket) {
      if ((string)($socket['kind'] ?? '') === 'entry') {
        $nodes[] = (string)($socket['node'] ?? '');
      }
    }
    return array_values(array_unique(array_filter($nodes, static fn(string $node): bool => $node !== '')));
  }

  /**
   * @param list<array<string,mixed>> $edges
   * @param list<string> $startNodes
   * @return array<string,bool>
   */
  private function reachableNodes(array $edges, array $startNodes): array
  {
    $children = [];
    foreach ($edges as $edge) {
      $children[(string)($edge['from'] ?? '')][] = (string)($edge['to'] ?? '');
    }

    $reachable = [];
    $queue = $startNodes;
    while ($queue !== []) {
      $node = array_shift($queue);
      if ($node === '' || isset($reachable[$node])) {
        continue;
      }
      $reachable[$node] = true;
      foreach ($children[$node] ?? [] as $child) {
        $queue[] = $child;
      }
    }

    return $reachable;
  }

  /**
   * @param list<array<string,mixed>> $patterns
   */
  private function variantCount(array $patterns): int
  {
    $count = 0;
    foreach ($patterns as $pattern) {
      if ((string)($pattern['status'] ?? '') !== 'enabled') {
        continue;
      }
      $count += count(is_array($pattern['allowed_transforms'] ?? null) ? $pattern['allowed_transforms'] : []);
    }
    return $count;
  }

  /**
   * @param list<array<string,mixed>> $patterns
   * @param list<array<string,mixed>> $rules
   * @param list<array<string,mixed>> $profiles
   */
  private function catalogHash(array $patterns, array $rules, array $profiles): string
  {
    $payload = json_encode([$patterns, $rules, $profiles], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
      throw new RuntimeException('Unable to encode run pattern catalog hash payload.');
    }
    return hash('sha256', $payload);
  }

  /**
   * @param array<string,mixed> $pattern
   */
  private function patternKey(array $pattern): string
  {
    return $this->patternKeyFromParts((string)($pattern['slug'] ?? ''), (int)($pattern['version'] ?? 0));
  }

  private function patternKeyFromParts(string $slug, int $version): string
  {
    return "{$slug}@{$version}";
  }
}
