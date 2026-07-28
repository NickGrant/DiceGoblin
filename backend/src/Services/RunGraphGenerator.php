<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\RunPatternCatalogRepository;
use PDO;
use RuntimeException;

final class RunGraphGenerator
{
  /** @var array<int,array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string}> */
  private const DIALOGUE_NODE_DEFINITIONS = [
    [
      'region_slug' => 'mystic_cave',
      'dialogue_id' => 'start-run-kickoff',
      'placement' => 'start',
      'one_time' => true,
      'tags' => ['lore'],
    ],
    [
      'region_slug' => 'mystic_cave',
      'dialogue_id' => 'mystic-cave-wrong-machine-reminder',
      'placement' => 'start',
      'one_time' => false,
      'tags' => [],
      'requires_seen_dialogue' => 'start-run-kickoff',
      'excludes_feature_unlock' => 'wrong_machine',
    ],
    [
      'region_slug' => 'mystic_cave',
      'dialogue_id' => 'mystic-cave-wrong-machine-recovered',
      'placement' => 'start',
      'one_time' => false,
      'tags' => ['lore'],
      'requires_seen_dialogue' => 'start-run-kickoff',
      'requires_feature_unlock' => 'wrong_machine',
    ],
    [
      'region_slug' => 'the_farm',
      'dialogue_id' => 'farm-boss-intro',
      'placement' => 'before_boss',
      'one_time' => false,
      'tags' => [],
    ],
    [
      'region_slug' => 'the_farm',
      'dialogue_id' => 'farm-shop-unlock',
      'placement' => 'before_exit',
      'one_time' => true,
      'tags' => ['lore'],
    ],
    [
      'region_slug' => 'mountains',
      'dialogue_id' => 'mountains-archivist-first-contact',
      'placement' => 'start',
      'one_time' => true,
      'tags' => ['lore'],
    ],
    [
      'region_slug' => 'mountains',
      'dialogue_id' => 'mountains-wrong-machine-search-repeat',
      'placement' => 'start',
      'one_time' => false,
      'tags' => [],
      'requires_seen_dialogue' => 'mountains-archivist-first-contact',
      'excludes_feature_unlock' => 'wrong_machine',
    ],
    [
      'region_slug' => 'mountains',
      'dialogue_id' => 'mountains-kobold-machine-trail',
      'placement' => 'before_boss',
      'one_time' => true,
      'tags' => ['lore'],
      'excludes_feature_unlock' => 'wrong_machine',
    ],
    [
      'region_slug' => 'mountains',
      'dialogue_id' => 'mountains-kobold-machine-recovered',
      'placement' => 'before_boss',
      'one_time' => false,
      'tags' => ['lore'],
      'requires_feature_unlock' => 'wrong_machine',
    ],
    [
      'region_slug' => 'mountains',
      'dialogue_id' => 'mountains-swamps-lead',
      'placement' => 'before_exit',
      'one_time' => true,
      'tags' => ['lore'],
    ],
  ];

  /** @var array<string,array<string,int>> */
  private const REGION_CONFIG = [
    'mountains' => [
      'row_count' => 9,
      'travel_columns' => 8,
      'path_count' => 3,
      'opening_rows_min' => 3,
      'opening_rows_max' => 3,
      'lane_gap' => 2,
      'dead_ends_min' => 2,
      'dead_ends_max' => 3,
      'dead_end_chain_min' => 1,
      'dead_end_chain_max' => 1,
      'rest_weight' => 1,
      'loot_weight' => 2,
      'combat_weight' => 5,
    ],
    'swamps' => [
      'row_count' => 11,
      'travel_columns' => 10,
      'path_count' => 4,
      'opening_rows_min' => 4,
      'opening_rows_max' => 4,
      'lane_gap' => 2,
      'dead_ends_min' => 3,
      'dead_ends_max' => 5,
      'dead_end_chain_min' => 1,
      'dead_end_chain_max' => 1,
      'rest_weight' => 2,
      'loot_weight' => 3,
      'combat_weight' => 3,
    ],
  ];

  private int $rngCounter = 0;

  public function __construct(
    private readonly PDO $pdo,
  ) {}

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generate(int $regionId, string $regionSlug, string $seed, bool $allowChaosNodes = true): array
  {
    return $this->generateWithVersion($regionId, $regionSlug, $seed, $allowChaosNodes, 'lane-v1');
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateWithVersion(
    int $regionId,
    string $regionSlug,
    string $seed,
    bool $allowChaosNodes = true,
    string $generatorVersion = 'lane-v1',
    array $storyPlacementRequests = []
  ): array {
    if ($regionSlug === 'mystic_cave') {
      return $this->generateMysticCave();
    }

    if ($regionSlug === 'the_farm') {
      return $this->generateFarm($regionId);
    }

    if ($generatorVersion === 'pattern-v1') {
      return $this->generatePatternV1($regionId, $regionSlug, $seed, $allowChaosNodes, $storyPlacementRequests);
    }

    if ($generatorVersion !== 'lane-v1') {
      throw new RuntimeException("Unsupported run graph generator version {$generatorVersion}.");
    }

    return $this->generateProcedural($regionId, $regionSlug, $seed, $allowChaosNodes);
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateMysticCave(): array
  {
    $graph = [
      'nodes' => [
        [
          'node_index' => 0,
          'node_type' => 'dialogue',
          'status' => 'available',
          'meta' => [
            'col' => 0,
            'row' => 1,
            'dialogue_id' => 'start-run-kickoff',
            'one_time' => true,
            'tags' => ['lore'],
          ],
        ],
        ['node_index' => 1, 'node_type' => 'exit', 'status' => 'locked', 'meta' => ['col' => 1, 'row' => 1]],
      ],
      'edges' => [
        ['from' => 0, 'to' => 1],
      ],
    ];

    $this->validateGraph($graph['nodes'], $graph['edges']);

    return $graph;
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateFarm(int $regionId): array
  {
    $templateIds = $this->loadEncounterTemplateIdsBySlug($regionId, [
      'the_farm_mud_combat_1',
      'the_farm_loot_1',
      'the_farm_rest_1',
      'the_farm_mud_boss_1',
    ]);

    $graph = [
      'nodes' => [
        ['node_index' => 0, 'node_type' => 'combat', 'status' => 'available', 'encounter_template_id' => $templateIds['the_farm_mud_combat_1'] ?? null, 'meta' => ['col' => 0, 'row' => 1]],
        ['node_index' => 1, 'node_type' => 'loot', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_loot_1'] ?? null, 'meta' => ['col' => 1, 'row' => 1, 'node_quality_tier' => 'good']],
        ['node_index' => 2, 'node_type' => 'rest', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_rest_1'] ?? null, 'meta' => ['col' => 2, 'row' => 1]],
        ['node_index' => 3, 'node_type' => 'boss', 'status' => 'locked', 'encounter_template_id' => $templateIds['the_farm_mud_boss_1'] ?? null, 'meta' => ['col' => 3, 'row' => 1]],
        ['node_index' => 4, 'node_type' => 'exit', 'status' => 'locked', 'meta' => ['col' => 4, 'row' => 1]],
      ],
      'edges' => [
        ['from' => 0, 'to' => 1],
        ['from' => 1, 'to' => 2],
        ['from' => 2, 'to' => 3],
        ['from' => 3, 'to' => 4],
      ],
    ];

    $this->validateGraph($graph['nodes'], $graph['edges']);

    return $graph;
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  public function applyDialogueNodes(int $userId, string $regionSlug, array $graph): array
  {
    $seenDialogues = $this->seenDialogueSet($userId);
    $graph = $this->removeSeenOneTimeDialogueNodes($graph, $seenDialogues);
    $definitions = $this->eligibleDialogueNodeDefinitions($userId, $regionSlug, $seenDialogues);

    if ($definitions === []) {
      $graph = $this->normalizeNodeIndexes($graph);
      $this->validateGraph($graph['nodes'], $graph['edges']);
      return $graph;
    }

    foreach ($definitions as $definition) {
      if ($regionSlug === 'mystic_cave' && $definition['dialogue_id'] === 'start-run-kickoff') {
        continue;
      }

      $graph = $this->insertDialogueNode($graph, $definition);
    }

    $graph = $this->normalizeNodeIndexes($graph);
    $this->validateGraph($graph['nodes'], $graph['edges']);

    return $graph;
  }

  /**
   * @return list<array{dialogue_id:string,placement:string,one_time:bool,tags:list<string>}>
   */
  public function buildDialoguePlacementRequests(int $userId, string $regionSlug): array
  {
    return array_map(
      static fn(array $definition): array => [
        'dialogue_id' => (string)$definition['dialogue_id'],
        'placement' => (string)$definition['placement'],
        'one_time' => (bool)$definition['one_time'],
        'tags' => array_values(array_map('strval', $definition['tags'])),
      ],
      $this->eligibleDialogueNodeDefinitions($userId, $regionSlug, $this->seenDialogueSet($userId)),
    );
  }

  /**
   * @param array<string,bool> $seenDialogues
   * @return array<int,array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string}>
   */
  private function eligibleDialogueNodeDefinitions(int $userId, string $regionSlug, array $seenDialogues): array
  {
    $definitions = $this->dialogueNodeDefinitionsForRegion($regionSlug);
    if ($definitions === []) {
      return [];
    }

    $hasShop = $this->hasFeatureUnlock($userId, 'shop');
    $eligible = [];
    foreach ($definitions as $definition) {
      if ($definition['dialogue_id'] === 'farm-boss-intro' && $hasShop) {
        $definition['dialogue_id'] = 'farm-boss-intro-shop-unlocked';
      }

      $requiredSeenDialogue = trim((string)($definition['requires_seen_dialogue'] ?? ''));
      if ($requiredSeenDialogue !== '' && !isset($seenDialogues[$requiredSeenDialogue])) {
        continue;
      }

      $requiredFeatureUnlock = trim((string)($definition['requires_feature_unlock'] ?? ''));
      if ($requiredFeatureUnlock !== '' && !$this->hasFeatureUnlock($userId, $requiredFeatureUnlock)) {
        continue;
      }

      $excludedFeatureUnlock = trim((string)($definition['excludes_feature_unlock'] ?? ''));
      if ($excludedFeatureUnlock !== '' && $this->hasFeatureUnlock($userId, $excludedFeatureUnlock)) {
        continue;
      }

      if ($definition['one_time'] && isset($seenDialogues[$definition['dialogue_id']])) {
        continue;
      }

      $eligible[] = $definition;
    }

    return $eligible;
  }

  /**
   * @return array<int,array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string}>
   */
  private function dialogueNodeDefinitionsForRegion(string $regionSlug): array
  {
    return array_values(array_filter(
      self::DIALOGUE_NODE_DEFINITIONS,
      static fn(array $definition): bool => $definition['region_slug'] === $regionSlug,
    ));
  }

  /**
   * @return array<string,bool>
   */
  private function seenDialogueSet(int $userId): array
  {
    $stmt = $this->pdo->prepare("
      SELECT `unlock_key`
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = 'dialogue'
    ");
    $stmt->execute([$userId]);

    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $seen[(string)$row['unlock_key']] = true;
    }

    return $seen;
  }

  private function hasFeatureUnlock(int $userId, string $featureKey): bool
  {
    $stmt = $this->pdo->prepare("
      SELECT 1
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = 'feature' AND `unlock_key` = ?
      LIMIT 1
    ");
    $stmt->execute([$userId, $featureKey]);

    return $stmt->fetchColumn() !== false;
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @param array<string,bool> $seenDialogues
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  private function removeSeenOneTimeDialogueNodes(array $graph, array $seenDialogues): array
  {
    $removeIndexes = [];
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['node_type'] ?? '') !== 'dialogue') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $dialogueId = (string)($meta['dialogue_id'] ?? '');
      $oneTime = (bool)($meta['one_time'] ?? false);
      if ($oneTime && $dialogueId !== '' && isset($seenDialogues[$dialogueId])) {
        $removeIndexes[(int)$node['node_index']] = true;
      }
    }

    if ($removeIndexes === []) {
      return $graph;
    }

    $incoming = [];
    $outgoing = [];
    foreach ($graph['edges'] as $edge) {
      $from = (int)$edge['from'];
      $to = (int)$edge['to'];
      $outgoing[$from][] = $to;
      $incoming[$to][] = $from;
    }

    $edges = [];
    foreach ($graph['edges'] as $edge) {
      if (isset($removeIndexes[(int)$edge['from']]) || isset($removeIndexes[(int)$edge['to']])) {
        continue;
      }

      $this->appendEdge($edges, (int)$edge['from'], (int)$edge['to']);
    }

    foreach (array_keys($removeIndexes) as $removedIndex) {
      $parents = array_values(array_filter(
        $incoming[$removedIndex] ?? [],
        static fn(int $parentIndex): bool => !isset($removeIndexes[$parentIndex]),
      ));
      $children = array_values(array_filter(
        $outgoing[$removedIndex] ?? [],
        static fn(int $childIndex): bool => !isset($removeIndexes[$childIndex]),
      ));

      foreach ($parents as $parentIndex) {
        foreach ($children as $childIndex) {
          $this->appendEdge($edges, $parentIndex, $childIndex);
        }
      }

      if ($parents === []) {
        foreach ($graph['nodes'] as $nodeIndex => $node) {
          if (in_array((int)$node['node_index'], $children, true)) {
            $graph['nodes'][$nodeIndex]['status'] = 'available';
          }
        }
      }
    }

    $graph['nodes'] = array_values(array_filter(
      $graph['nodes'],
      static fn(array $node): bool => !isset($removeIndexes[(int)$node['node_index']]),
    ));
    $graph['edges'] = $edges;

    return $graph;
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @param array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string} $definition
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  private function insertDialogueNode(array $graph, array $definition): array
  {
    return match ($definition['placement']) {
      'start' => $this->insertDialogueAtStart($graph, $definition),
      'before_boss' => $this->insertDialogueBeforeType($graph, $definition, 'boss'),
      'before_exit' => $this->insertDialogueBeforeType($graph, $definition, 'exit'),
      default => $graph,
    };
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @param array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string} $definition
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  private function insertDialogueAtStart(array $graph, array $definition): array
  {
    $starts = [];
    foreach ($graph['nodes'] as $index => $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $graph['nodes'][$index]['meta'] = [
        ...$meta,
        'col' => ((int)($meta['col'] ?? 0)) + 1,
      ];

      if ((string)($node['status'] ?? '') === 'available') {
        $graph['nodes'][$index]['status'] = 'locked';
        $starts[] = (int)$node['node_index'];
      }
    }

    if ($starts === []) {
      return $graph;
    }

    $dialogueIndex = $this->nextNodeIndex($graph['nodes']);
    $graph['nodes'][] = $this->dialogueNode($dialogueIndex, $definition, 'available', 0, 1);
    foreach ($starts as $startIndex) {
      $this->appendEdge($graph['edges'], $dialogueIndex, $startIndex);
    }

    return $graph;
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @param array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string} $definition
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  private function insertDialogueBeforeType(array $graph, array $definition, string $targetType): array
  {
    $target = null;
    foreach ($graph['nodes'] as $node) {
      if ((string)($node['node_type'] ?? '') === $targetType) {
        $target = $node;
        break;
      }
    }

    if ($target === null) {
      return $graph;
    }

    $targetIndex = (int)$target['node_index'];
    $targetMeta = is_array($target['meta'] ?? null) ? $target['meta'] : [];
    $targetCol = (int)($targetMeta['col'] ?? 0);
    $targetRow = (int)($targetMeta['row'] ?? 0);

    foreach ($graph['nodes'] as $index => $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? 0);
      if ($col >= $targetCol) {
        $graph['nodes'][$index]['meta'] = [
          ...$meta,
          'col' => $col + 1,
        ];
      }
    }

    $dialogueIndex = $this->nextNodeIndex($graph['nodes']);
    $graph['nodes'][] = $this->dialogueNode($dialogueIndex, $definition, 'locked', $targetCol, $targetRow);

    $rewiredEdges = [];
    foreach ($graph['edges'] as $edge) {
      if ((int)$edge['to'] === $targetIndex) {
        $this->appendEdge($rewiredEdges, (int)$edge['from'], $dialogueIndex);
        continue;
      }

      $this->appendEdge($rewiredEdges, (int)$edge['from'], (int)$edge['to']);
    }
    $this->appendEdge($rewiredEdges, $dialogueIndex, $targetIndex);
    $graph['edges'] = $rewiredEdges;

    return $graph;
  }

  /**
   * @param array{region_slug:string,dialogue_id:string,placement:string,one_time:bool,tags:array<int,string>,requires_seen_dialogue?:string,requires_feature_unlock?:string,excludes_feature_unlock?:string} $definition
   * @return array<string,mixed>
   */
  private function dialogueNode(int $nodeIndex, array $definition, string $status, int $col, int $row): array
  {
    return [
      'node_index' => $nodeIndex,
      'node_type' => 'dialogue',
      'status' => $status,
      'encounter_template_id' => null,
      'meta' => [
        'col' => $col,
        'row' => $row,
        'dialogue_id' => $definition['dialogue_id'],
        'one_time' => $definition['one_time'],
        'placement' => $definition['placement'],
        'tags' => $definition['tags'],
      ],
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function nextNodeIndex(array $nodes): int
  {
    $maxIndex = -1;
    foreach ($nodes as $node) {
      $maxIndex = max($maxIndex, (int)($node['node_index'] ?? -1));
    }

    return $maxIndex + 1;
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  private function normalizeNodeIndexes(array $graph): array
  {
    $nodes = $graph['nodes'];
    usort($nodes, static function (array $left, array $right): int {
      $leftMeta = is_array($left['meta'] ?? null) ? $left['meta'] : [];
      $rightMeta = is_array($right['meta'] ?? null) ? $right['meta'] : [];
      $leftCol = (int)($leftMeta['col'] ?? 0);
      $rightCol = (int)($rightMeta['col'] ?? 0);
      if ($leftCol !== $rightCol) {
        return $leftCol <=> $rightCol;
      }

      $leftRow = (int)($leftMeta['row'] ?? 0);
      $rightRow = (int)($rightMeta['row'] ?? 0);
      if ($leftRow !== $rightRow) {
        return $leftRow <=> $rightRow;
      }

      return ((int)($left['node_index'] ?? 0)) <=> ((int)($right['node_index'] ?? 0));
    });

    $indexMap = [];
    foreach ($nodes as $newIndex => $node) {
      $oldIndex = (int)$node['node_index'];
      $indexMap[$oldIndex] = $newIndex;
      $nodes[$newIndex]['node_index'] = $newIndex;
    }

    $edges = [];
    foreach ($graph['edges'] as $edge) {
      $from = $indexMap[(int)$edge['from']] ?? null;
      $to = $indexMap[(int)$edge['to']] ?? null;
      if ($from === null || $to === null) {
        continue;
      }

      $this->appendEdge($edges, $from, $to);
    }

    return ['nodes' => $nodes, 'edges' => $edges];
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  public function generateProcedural(int $regionId, string $regionSlug, string $seed, bool $allowChaosNodes = true): array
  {
    $config = self::REGION_CONFIG[$regionSlug] ?? [
      'row_count' => 9,
      'travel_columns' => 9,
      'path_count' => 3,
      'opening_rows_min' => 3,
      'opening_rows_max' => 3,
      'lane_gap' => 2,
      'dead_ends_min' => 2,
      'dead_ends_max' => 4,
      'dead_end_chain_min' => 1,
      'dead_end_chain_max' => 1,
      'rest_weight' => 2,
      'loot_weight' => 2,
      'combat_weight' => 4,
    ];

    $this->rngCounter = 0;
    $seedKey = sprintf('%s|%d|%s', $regionSlug, $regionId, $seed);

    $rowCount = max(3, (int)$config['row_count']);
    $travelColumns = max(4, (int)$config['travel_columns']);
    $pathCount = max(3, (int)$config['path_count']);
    $laneGap = max(1, (int)($config['lane_gap'] ?? 2));
    $centerRow = intdiv($rowCount - 1, 2);

    $nodes = [];
    $edges = [];
    /** @var array<string,bool> $occupied */
    $occupied = [];
    /** @var array<string,int> $nodeIndexByPosition */
    $nodeIndexByPosition = [];
    /** @var array<int,array{walker_id:int,row:int,node_index:int,anchor_row:int}> $walkers */
    $walkers = [];

    $startIndex = $this->appendNode($nodes, $occupied, $nodeIndexByPosition, 'combat', 'available', 0, $centerRow);

    $openingCount = $this->randBetween(
      $seedKey . '|openings',
      min((int)$config['opening_rows_min'], $rowCount),
      min((int)$config['opening_rows_max'], $rowCount),
    );
    $laneRows = $this->buildLaneRows($rowCount, $pathCount, $centerRow);
    $openingRows = $this->pickOpeningRows($seedKey, $rowCount, $openingCount, $centerRow, $laneGap, $laneRows);
    foreach ($openingRows as $openingRow) {
      $openingIndex = $this->ensureNodeAt($nodes, $occupied, $nodeIndexByPosition, 1, $openingRow);
      $this->appendEdge($edges, $startIndex, $openingIndex);
    }

    for ($walkerId = 0; $walkerId < $pathCount; $walkerId++) {
      $openingRow = $this->pickOpeningRowForWalker($seedKey, $openingRows, $laneRows, $walkerId);
      $walkers[] = [
        'walker_id' => $walkerId,
        'row' => $openingRow,
        'node_index' => $this->mustNodeIndexAt($nodeIndexByPosition, 1, $openingRow),
        'anchor_row' => $openingRow,
        'straight_streak' => 0,
      ];
    }

    for ($column = 1; $column < $travelColumns; $column++) {
      usort($walkers, static function (array $left, array $right): int {
        if ($left['row'] !== $right['row']) {
          return $left['row'] <=> $right['row'];
        }

        return $left['walker_id'] <=> $right['walker_id'];
      });

      $nextWalkers = [];
      $previousChosenRow = null;

      foreach ($walkers as $walker) {
        $nextRow = $this->pickNextWalkerRow(
          $seedKey,
          $walker['walker_id'],
          $column,
          (int)$walker['row'],
          (int)$walker['anchor_row'],
          (int)$walker['straight_streak'],
          $rowCount,
          $centerRow,
          $previousChosenRow,
          $travelColumns,
          $laneGap,
        );

        $fromIndex = (int)$walker['node_index'];
        $toIndex = $this->ensureNodeAt($nodes, $occupied, $nodeIndexByPosition, $column + 1, $nextRow);
        $this->appendEdge($edges, $fromIndex, $toIndex);

        $nextWalkers[] = [
          'walker_id' => (int)$walker['walker_id'],
          'row' => $nextRow,
          'node_index' => $toIndex,
          'anchor_row' => (int)$walker['anchor_row'],
          'straight_streak' => $nextRow === (int)$walker['row']
            ? ((int)$walker['straight_streak']) + 1
            : 0,
        ];
        $previousChosenRow = $nextRow;
      }

      $walkers = $nextWalkers;
    }

    $bossCol = $travelColumns + 1;
    $exitCol = $travelColumns + 2;
    $bossIndex = $this->appendNode($nodes, $occupied, $nodeIndexByPosition, 'boss', 'locked', $bossCol, $centerRow);
    $exitIndex = $this->appendNode($nodes, $occupied, $nodeIndexByPosition, 'exit', 'locked', $exitCol, $centerRow);

    foreach ($this->nodeIndexesInColumn($nodes, $travelColumns) as $nodeIndex) {
      $this->appendEdge($edges, $nodeIndex, $bossIndex);
    }
    $this->appendEdge($edges, $bossIndex, $exitIndex);

    $deadEndsTarget = $this->randBetween($seedKey . '|dead-end-count', (int)$config['dead_ends_min'], (int)$config['dead_ends_max']);
    $deadEndChainMin = max(1, (int)$config['dead_end_chain_min']);
    $deadEndChainMax = max($deadEndChainMin, (int)$config['dead_end_chain_max']);
    $this->addDeadEndBranches(
      $seedKey,
      $nodes,
      $edges,
      $occupied,
      $nodeIndexByPosition,
      $travelColumns,
      $rowCount,
      $centerRow,
      $deadEndsTarget,
      $deadEndChainMin,
      $deadEndChainMax,
    );
    $this->compactRowsTowardCenter($nodes, $edges);
    $this->addNearbyShortcutConnections($seedKey, $nodes, $edges, $travelColumns);
    $this->removeRedundantSameRowBypassEdges($nodes, $edges);

    $this->assignProceduralNodeTypes($nodes, $edges, $seedKey, $regionSlug, $config, $travelColumns, $allowChaosNodes);
    $this->ensureAtLeastOneRestNode($nodes, $seedKey, $travelColumns);
    if ($allowChaosNodes) {
      $this->ensureAtLeastOneChaosNode($nodes, $seedKey, $travelColumns);
    }
    $this->assignHazardEffects($nodes, $seedKey, $regionSlug);
    $this->assignNodeQualityTiers($nodes, $edges, $travelColumns);
    $nodes = $this->assignEncounterTemplates($regionId, $nodes, $seedKey);
    $this->validateGraph($nodes, $edges);

    return ['nodes' => $nodes, 'edges' => $edges];
  }

  /**
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>, generation:array<string,mixed>}
   */
  public function generatePatternV1(
    int $regionId,
    string $regionSlug,
    string $seed,
    bool $allowChaosNodes = true,
    array $storyPlacementRequests = []
  ): array {
    $request = (new RunPatternGenerationRequestBuilder(new RunPatternCatalogRepository($this->pdo)))
      ->build($regionSlug, $seed, 'pattern-v1');
    $request['story_placement_requests'] = $this->normalizedStoryPlacementRequests($storyPlacementRequests);
    $assembly = (new RunPatternPreviewAssemblerService())->assemble($request);
    if (!$assembly['validation']['valid']) {
      throw new RuntimeException('Pattern run graph generation failed validation: ' . implode(', ', $assembly['validation']['errors']));
    }

    $graph = $this->runtimeGraphFromPatternAssembly($assembly['graph'], $request);
    if (!$allowChaosNodes) {
      foreach ($graph['nodes'] as $index => $node) {
        if ((string)($node['node_type'] ?? '') === 'chaos') {
          $graph['nodes'][$index]['node_type'] = 'combat';
        }
      }
    }

    $this->assignHazardEffects($graph['nodes'], $seed . '|pattern-v1', $regionSlug);
    $this->assignNodeQualityTiers($graph['nodes'], $graph['edges'], $this->maxRunColumn($graph['nodes']));
    $graph['nodes'] = $this->assignEncounterTemplates($regionId, $graph['nodes'], $seed . '|pattern-v1');
    $this->validateGraph($graph['nodes'], $graph['edges']);

    $summary = (new RunPatternGenerationSummaryService())->summarize($request, $assembly['graph'], $assembly['trace']);
    return [
      'nodes' => $graph['nodes'],
      'edges' => $graph['edges'],
      'generation' => [
        ...$summary,
        'generation_attempt' => 0,
      ],
    ];
  }

  /**
   * @param array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} $patternGraph
   * @param array<string,mixed> $request
   * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array{from:int,to:int}>}
   */
  private function runtimeGraphFromPatternAssembly(array $patternGraph, array $request): array
  {
    $patternNodes = array_values(array_filter($patternGraph['nodes'] ?? [], 'is_array'));
    usort($patternNodes, static function (array $left, array $right): int {
      $leftX = (int)($left['x'] ?? 0);
      $rightX = (int)($right['x'] ?? 0);
      if ($leftX !== $rightX) {
        return $leftX <=> $rightX;
      }

      $leftY = (int)($left['y'] ?? 0);
      $rightY = (int)($right['y'] ?? 0);
      if ($leftY !== $rightY) {
        return $leftY <=> $rightY;
      }

      return strcmp((string)($left['key'] ?? ''), (string)($right['key'] ?? ''));
    });

    $keyToIndex = [];
    $nodes = [];
    foreach ($patternNodes as $nodeIndex => $node) {
      $key = (string)($node['key'] ?? '');
      $keyToIndex[$key] = $nodeIndex;
      $sourceType = (string)($node['source_type'] ?? $node['type'] ?? 'combat');
      $nodeType = (string)($node['type'] ?? $sourceType);
      if ($nodeType === 'start') {
        $nodeType = $sourceType !== 'start' ? $sourceType : 'combat';
      }

      $nodes[] = [
        'node_index' => $nodeIndex,
        'node_type' => $nodeType,
        'status' => $nodeIndex === 0 ? 'available' : 'locked',
        'encounter_template_id' => null,
        'meta' => [
          'col' => (int)($node['x'] ?? 0),
          'row' => (int)($node['y'] ?? 0),
          'generation' => [
            'generator_version' => 'pattern-v1',
            'profile_version' => (int)($request['profile_version'] ?? 0),
            'catalog_hash' => (string)($request['catalog_hash'] ?? ''),
            'pattern_key' => (string)($node['pattern_key'] ?? ''),
            'local_node_key' => (string)($node['key'] ?? ''),
            'path_role' => (string)($node['path_role'] ?? ''),
            'depth' => (int)($node['depth'] ?? 0),
            'tags' => is_array($node['tags'] ?? null) ? array_values($node['tags']) : [],
          ],
          ...$this->patternDialogueMeta($node),
        ],
      ];
    }

    $edges = [];
    foreach (array_values(array_filter($patternGraph['edges'] ?? [], 'is_array')) as $edge) {
      $from = $keyToIndex[(string)($edge['from'] ?? '')] ?? null;
      $to = $keyToIndex[(string)($edge['to'] ?? '')] ?? null;
      if ($from !== null && $to !== null) {
        $this->appendEdge($edges, $from, $to);
      }
    }

    return ['nodes' => $nodes, 'edges' => $edges];
  }

  /**
   * @param list<array<string,mixed>> $requests
   * @return list<array{dialogue_id:string,placement:string,one_time:bool,tags:list<string>}>
   */
  private function normalizedStoryPlacementRequests(array $requests): array
  {
    $normalized = [];
    foreach ($requests as $request) {
      if (!is_array($request)) {
        continue;
      }

      $dialogueId = trim((string)($request['dialogue_id'] ?? ''));
      $placement = trim((string)($request['placement'] ?? ''));
      if ($dialogueId === '' || !in_array($placement, ['start', 'before_boss', 'before_exit'], true)) {
        continue;
      }

      $normalized[] = [
        'dialogue_id' => $dialogueId,
        'placement' => $placement,
        'one_time' => (bool)($request['one_time'] ?? false),
        'tags' => array_values(array_map('strval', is_array($request['tags'] ?? null) ? $request['tags'] : [])),
      ];
    }

    return $normalized;
  }

  /**
   * @param array<string,mixed> $node
   * @return array<string,mixed>
   */
  private function patternDialogueMeta(array $node): array
  {
    if ((string)($node['source_type'] ?? '') !== 'dialogue' && !isset($node['dialogue_id'])) {
      return [];
    }

    return [
      'dialogue_id' => (string)($node['dialogue_id'] ?? ''),
      'one_time' => (bool)($node['one_time'] ?? false),
      'placement' => (string)($node['placement'] ?? ''),
      'tags' => array_values(array_map('strval', is_array($node['tags'] ?? null) ? $node['tags'] : [])),
    ];
  }

  /**
   * @param array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>} $graph
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array{from:int,to:int}>}
   */
  public function applyTreasureSenseReveal(int $regionId, array $graph, string $seed, float $revealChance): array
  {
    if ($revealChance <= 0.0) {
      return $graph;
    }

    $roll = $this->randBetween($seed . '|treasure-sense-roll', 1, 10000) / 10000;
    if ($roll > $revealChance) {
      return $graph;
    }

    $nodes = $graph['nodes'];
    $edges = $graph['edges'];
    $occupied = [];
    $nodeIndexByPosition = [];
    $exitCol = -1;

    foreach ($nodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      $row = (int)($meta['row'] ?? -1);
      if ($col >= 0 && $row >= 0) {
        $occupied[$col . ':' . $row] = true;
        $nodeIndexByPosition[$col . ':' . $row] = (int)$node['node_index'];
      }
      if ((string)($node['node_type'] ?? '') === 'exit') {
        $exitCol = max($exitCol, $col);
      }
    }

    $candidateParents = [];
    foreach ($nodes as $node) {
      $nodeIndex = (int)($node['node_index'] ?? -1);
      $nodeType = (string)($node['node_type'] ?? '');
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      $row = (int)($meta['row'] ?? -1);

      if ($nodeIndex < 0 || $col < 0 || $row < 0 || in_array($nodeType, ['boss', 'exit'], true) || ($col + 1) >= $exitCol) {
        continue;
      }

      $candidate = $this->pickDeadEndCandidate(
        $seed . '|treasure-sense|' . $nodeIndex,
        $nodes,
        $edges,
        $occupied,
        $nodeIndexByPosition,
        $nodeIndex,
        $col,
        $row,
        max(1, $this->maxRowIndex($nodes) + 1),
        max(0, intdiv($this->maxRowIndex($nodes), 2)),
        1,
      );
      if ($candidate === null) {
        continue;
      }

      $candidateParents[] = $candidate;
    }

    if ($candidateParents === []) {
      return $graph;
    }

    $lootPool = $this->loadEncounterTemplatePools($regionId)['loot'] ?? [];
    if ($lootPool === []) {
      return $graph;
    }

    $pickIndex = $this->randBetween($seed . '|treasure-sense-parent', 0, count($candidateParents) - 1);
    $picked = $candidateParents[$pickIndex];

    $newNodeIndex = $this->appendNode(
      $nodes,
      $occupied,
      $nodeIndexByPosition,
      'loot',
      'locked',
      (int)$picked['col'],
      (int)$picked['row'],
    );
    $nodes[$newNodeIndex]['meta']['revealed_by_treasure_sense'] = true;
    $nodes[$newNodeIndex]['meta']['hidden_treasure'] = true;
    $nodes[$newNodeIndex]['meta']['treasure_sense_chance'] = round($revealChance, 4);
    $templateIndex = $this->randBetween($seed . '|treasure-sense-template', 0, count($lootPool) - 1);
    $nodes[$newNodeIndex]['encounter_template_id'] = $lootPool[$templateIndex];
    $this->appendEdge($edges, (int)$picked['from'], $newNodeIndex);

    $this->validateGraph($nodes, $edges);

    return [
      'nodes' => $nodes,
      'edges' => $edges,
    ];
  }

  /**
   * @return array{combat:array<int,int>,boss:array<int,int>,loot:array<int,int>,rest:array<int,int>,shrine:array<int,int>,chaos:array<int,int>}
   */
  public function loadEncounterTemplatePools(int $regionId): array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `slug`, `reward_profile_json` FROM `encounter_templates` WHERE `region_id` = ? ORDER BY `id` ASC');
    $stmt->execute([$regionId]);

    $pools = [
      'combat' => [],
      'boss' => [],
      'loot' => [],
      'rest' => [],
      'shrine' => [],
      'chaos' => [],
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $id = (int)$row['id'];
      $slug = (string)$row['slug'];

      $rewardType = null;
      $rewardProfile = json_decode((string)($row['reward_profile_json'] ?? ''), true);
      if (is_array($rewardProfile) && isset($rewardProfile['type'])) {
        $rewardType = (string)$rewardProfile['type'];
      }

      if (str_contains($slug, '_boss_')) {
        $pools['boss'][] = $id;
        continue;
      }
      if (str_contains($slug, '_combat_')) {
        $pools['combat'][] = $id;
        continue;
      }
      if ($rewardType === 'loot' || str_contains($slug, '_loot_')) {
        $pools['loot'][] = $id;
        continue;
      }
      if ($rewardType === 'rest' || str_contains($slug, '_rest_')) {
        $pools['rest'][] = $id;
      }
    }

    $pools['shrine'] = $pools['shrine'] !== [] ? $pools['shrine'] : $pools['loot'];

    return $pools;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<int,array<string,mixed>>
   */
  public function assignEncounterTemplates(int $regionId, array $nodes, string $seed): array
  {
    $pools = $this->loadEncounterTemplatePools($regionId);

    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      if ($nodeType === 'exit' || $nodeType === 'dialogue') {
        continue;
      }

      $pool = $pools[$nodeType] ?? [];
      if ($pool === []) {
        $nodes[$index]['encounter_template_id'] = null;
        continue;
      }

      $poolIndex = $this->randBetween($seed . '|template|' . $index . '|' . $nodeType, 0, count($pool) - 1);
      $nodes[$index]['encounter_template_id'] = $pool[$poolIndex];
    }

    return $nodes;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  public function validateGraph(array $nodes, array $edges): void
  {
    if ($nodes === []) {
      throw new RuntimeException('Run graph must contain nodes.');
    }

    $nodeIndexes = [];
    $incoming = [];
    $outgoing = [];
    $availableStarts = [];
    $bossIndexes = [];
    $exitIndexes = [];
    $maxCol = -1;

    foreach ($nodes as $node) {
      $nodeIndex = (int)($node['node_index'] ?? -1);
      if ($nodeIndex < 0) {
        throw new RuntimeException('Run graph node indexes must be non-negative.');
      }
      if (isset($nodeIndexes[$nodeIndex])) {
        throw new RuntimeException('Run graph node indexes must be unique.');
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : null;
      $col = is_array($meta) ? (int)($meta['col'] ?? -1) : -1;
      $row = is_array($meta) ? (int)($meta['row'] ?? -1) : -1;
      if ($col < 0 || $row < 0) {
        throw new RuntimeException('Run graph nodes must include valid col/row metadata.');
      }
      $maxCol = max($maxCol, $col);

      $nodeIndexes[$nodeIndex] = $node;
      $incoming[$nodeIndex] = [];
      $outgoing[$nodeIndex] = [];

      if ((string)($node['status'] ?? '') === 'available') {
        $availableStarts[] = $nodeIndex;
      }
      if ((string)($node['node_type'] ?? '') === 'boss') {
        $bossIndexes[] = $nodeIndex;
      }
      if ((string)($node['node_type'] ?? '') === 'exit') {
        $exitIndexes[] = $nodeIndex;
      }
    }

    if (count($availableStarts) !== 1) {
      throw new RuntimeException('Run graph must contain exactly one available starting node.');
    }
    if (count($bossIndexes) > 1) {
      throw new RuntimeException('Run graph must contain at most one boss node.');
    }
    if (count($exitIndexes) !== 1) {
      throw new RuntimeException('Run graph must contain exactly one exit node.');
    }

    $edgeKeys = [];
    foreach ($edges as $edge) {
      $from = (int)($edge['from'] ?? -1);
      $to = (int)($edge['to'] ?? -1);
      if (!isset($nodeIndexes[$from], $nodeIndexes[$to])) {
        throw new RuntimeException('Run graph edges must point to valid nodes.');
      }
      if ($from === $to) {
        throw new RuntimeException('Run graph may not contain self edges.');
      }

      $fromCol = (int)$nodeIndexes[$from]['meta']['col'];
      $toCol = (int)$nodeIndexes[$to]['meta']['col'];
      if ($toCol <= $fromCol) {
        throw new RuntimeException('Run graph edges must always point forward.');
      }

      $edgeKey = $from . '->' . $to;
      if (isset($edgeKeys[$edgeKey])) {
        throw new RuntimeException('Run graph may not contain duplicate edges.');
      }
      $edgeKeys[$edgeKey] = true;

      $outgoing[$from][] = $to;
      $incoming[$to][] = $from;
    }

    $startIndex = $availableStarts[0];
    foreach (array_keys($nodeIndexes) as $nodeIndex) {
      if ($nodeIndex === $startIndex) {
        continue;
      }
      if ($incoming[$nodeIndex] === []) {
        throw new RuntimeException('Every non-start node must have at least one incoming edge.');
      }
    }

    $reachableFromStart = $this->reachableNodeIndexes($startIndex, $outgoing);
    $bossIndex = $bossIndexes[0] ?? null;
    $exitIndex = $exitIndexes[0];
    $exitCol = (int)$nodeIndexes[$exitIndex]['meta']['col'];

    if ($exitCol !== $maxCol) {
      throw new RuntimeException('Exit must be the right-most node in the run graph.');
    }

    if ($bossIndex !== null) {
      $bossCol = (int)$nodeIndexes[$bossIndex]['meta']['col'];
      if ($bossCol > $exitCol - 1) {
        throw new RuntimeException('Boss must appear before the exit column.');
      }

      foreach ($nodeIndexes as $nodeIndex => $node) {
        if ($nodeIndex === $exitIndex) {
          continue;
        }
        if ((int)$node['meta']['col'] > $exitCol) {
          throw new RuntimeException('No non-exit nodes may appear to the right of the exit.');
        }
      }

      if (!isset($reachableFromStart[$bossIndex])) {
        throw new RuntimeException('Boss must be reachable from the start node.');
      }
    }
    if (!isset($reachableFromStart[$exitIndex])) {
      throw new RuntimeException('Exit must be reachable from the start node.');
    }

    if ($bossIndex !== null) {
      $reachableFromBoss = $this->reachableNodeIndexes($bossIndex, $outgoing);
      if (!isset($reachableFromBoss[$exitIndex])) {
        throw new RuntimeException('Exit must be reachable from the boss node.');
      }
    }

    foreach (array_keys($nodeIndexes) as $nodeIndex) {
      if (!isset($reachableFromStart[$nodeIndex])) {
        throw new RuntimeException('All run graph nodes must be reachable from the start node.');
      }
    }

    foreach ($nodes as $node) {
      $nodeIndex = (int)$node['node_index'];
      if (($outgoing[$nodeIndex] ?? []) !== []) {
        continue;
      }

      $nodeType = (string)$node['node_type'];
      if ($nodeType === 'exit') {
        continue;
      }

      if ($nodeType === 'boss') {
        throw new RuntimeException('Boss nodes may not be dead ends.');
      }

      foreach ($incoming[$nodeIndex] ?? [] as $parentIndex) {
        $alternateChildren = array_values(array_filter(
          $outgoing[$parentIndex] ?? [],
          static fn(int $childIndex): bool => $childIndex !== $nodeIndex,
        ));
        if ($alternateChildren === []) {
          throw new RuntimeException('Dead ends must be optional.');
        }

        $hasBossRoute = false;
        foreach ($alternateChildren as $alternateChild) {
          $reachable = $this->reachableNodeIndexes($alternateChild, $outgoing);
          if ($bossIndex !== null && isset($reachable[$bossIndex])) {
            $hasBossRoute = true;
            break;
          }
        }

        if ($bossIndex !== null && !$hasBossRoute) {
          throw new RuntimeException('Dead ends must not block access to the boss path.');
        }
      }
    }

    $this->assertNoCrossingEdges($nodes, $edges);
  }

  /**
   * @param array<int,string> $slugs
   * @return array<string,int>
   */
  private function loadEncounterTemplateIdsBySlug(int $regionId, array $slugs): array
  {
    if ($slugs === []) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $params = array_merge([$regionId], $slugs);
    $stmt = $this->pdo->prepare("
      SELECT `id`, `slug`
      FROM `encounter_templates`
      WHERE `region_id` = ? AND `slug` IN ($placeholders)
      ORDER BY `id` ASC
    ");
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(string)$row['slug']] = (int)$row['id'];
    }

    return $map;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<string,bool> $occupied
   * @param array<string,int> $nodeIndexByPosition
   */
  private function appendNode(
    array &$nodes,
    array &$occupied,
    array &$nodeIndexByPosition,
    string $nodeType,
    string $status,
    int $col,
    int $row,
  ): int {
    $positionKey = $col . ':' . $row;
    if (isset($occupied[$positionKey])) {
      throw new RuntimeException('Run graph attempted to reuse a map position.');
    }

    $nodeIndex = count($nodes);
    $nodes[] = [
      'node_index' => $nodeIndex,
      'node_type' => $nodeType,
      'status' => $status,
      'meta' => ['col' => $col, 'row' => $row],
    ];
    $occupied[$positionKey] = true;
    $nodeIndexByPosition[$positionKey] = $nodeIndex;

    return $nodeIndex;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<string,bool> $occupied
   * @param array<string,int> $nodeIndexByPosition
   */
  private function ensureNodeAt(
    array &$nodes,
    array &$occupied,
    array &$nodeIndexByPosition,
    int $col,
    int $row,
  ): int {
    $positionKey = $col . ':' . $row;
    if (isset($nodeIndexByPosition[$positionKey])) {
      return $nodeIndexByPosition[$positionKey];
    }

    return $this->appendNode($nodes, $occupied, $nodeIndexByPosition, 'combat', 'locked', $col, $row);
  }

  /**
   * @param array<string,int> $nodeIndexByPosition
   */
  private function mustNodeIndexAt(array $nodeIndexByPosition, int $col, int $row): int
  {
    $positionKey = $col . ':' . $row;
    if (!isset($nodeIndexByPosition[$positionKey])) {
      throw new RuntimeException('Expected node position was not generated.');
    }

    return (int)$nodeIndexByPosition[$positionKey];
  }

  /**
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function appendEdge(array &$edges, int $from, int $to): void
  {
    foreach ($edges as $edge) {
      if ((int)$edge['from'] === $from && (int)$edge['to'] === $to) {
        return;
      }
    }

    $edges[] = ['from' => $from, 'to' => $to];
  }

  /**
   * @return array<int,int>
   */
  private function pickOpeningRows(string $seedKey, int $rowCount, int $openingCount, int $centerRow, int $laneGap, array $preferredRows = []): array
  {
    if ($preferredRows !== []) {
      $preferredRows = array_values(array_unique(array_filter(
        array_map(static fn(mixed $row): int => (int)$row, $preferredRows),
        static function (int $row) use ($rowCount): bool {
          return $row >= 0 && $row < $rowCount;
        },
      )));
      sort($preferredRows);
      if (count($preferredRows) >= $openingCount) {
        return array_slice($preferredRows, 0, $openingCount);
      }
    }

    $pool = [];
    for ($row = 0; $row < $rowCount; $row += $laneGap) {
      $pool[] = $row;
    }
    if (!in_array($centerRow, $pool, true)) {
      $pool[] = $centerRow;
    }

    usort($pool, static function (int $left, int $right) use ($centerRow): int {
      $leftDistance = abs($left - $centerRow);
      $rightDistance = abs($right - $centerRow);
      if ($leftDistance !== $rightDistance) {
        return $leftDistance <=> $rightDistance;
      }

      return $left <=> $right;
    });

    $picked = [];
    while (count($picked) < $openingCount && $pool !== []) {
      $maxPickIndex = min(count($pool) - 1, max(0, count($pool) - 1));
      $pickIndex = $this->randBetween($seedKey . '|opening-row|' . count($picked), 0, min($maxPickIndex, 3));
      $pickedRow = $pool[$pickIndex];
      $picked[] = $pickedRow;
      array_splice($pool, $pickIndex, 1);
      $pool = array_values(array_filter(
        $pool,
        static fn(int $candidateRow): bool => abs($candidateRow - $pickedRow) >= $laneGap,
      ));
    }

    sort($picked);
    return $picked;
  }

  /**
   * @param array<int,int> $openingRows
   */
  private function pickOpeningRowForWalker(string $seedKey, array $openingRows, array $laneRows, int $walkerId): int
  {
    if ($laneRows !== []) {
      $laneIndex = min($walkerId, count($laneRows) - 1);
      return (int)$laneRows[$laneIndex];
    }

    if ($openingRows === []) {
      return 0;
    }

    if ($walkerId < count($openingRows)) {
      return (int)$openingRows[$walkerId];
    }

    $pickIndex = $this->randBetween($seedKey . '|walker-opening|' . $walkerId, 0, count($openingRows) - 1);
    return (int)$openingRows[$pickIndex];
  }

  private function pickNextWalkerRow(
    string $seedKey,
    int $walkerId,
    int $column,
    int $currentRow,
    int $anchorRow,
    int $straightStreak,
    int $rowCount,
    int $centerRow,
    ?int $previousChosenRow,
    int $travelColumns,
    int $laneGap,
  ): int {
    $candidates = [];
    $minimumRow = $previousChosenRow === null ? 0 : $previousChosenRow + 1;
    $stepOffsets = array_values(array_unique([
      -$laneGap,
      -1,
      0,
      1,
      $laneGap,
    ]));

    foreach ($stepOffsets as $offset) {
      $candidateRow = $currentRow + $offset;
      if ($candidateRow < 0 || $candidateRow >= $rowCount || $candidateRow < $minimumRow) {
        continue;
      }

      $distanceFromCurrent = abs($candidateRow - $currentRow);
      $distanceFromAnchor = abs($candidateRow - $anchorRow);
      $score = 16;

      if ($distanceFromCurrent === 0) {
        $score -= 6 + ($straightStreak * 8);
      } else {
        $score += 6;
        if ($distanceFromCurrent === 1) {
          $score += 3;
        }
      }

      $score -= $distanceFromAnchor * 2;

      if ($column >= $travelColumns - 2) {
        $score += max(0, 6 - abs($candidateRow - $centerRow));
      }

      $score += $this->randBetween($seedKey . '|walker-step-noise|' . $walkerId . '|' . $column . '|' . $candidateRow, 0, 4);
      $candidates[] = ['row' => $candidateRow, 'score' => $score];
    }

    if ($candidates === []) {
      return min($rowCount - 1, max($minimumRow, $anchorRow));
    }

    usort($candidates, static function (array $left, array $right): int {
      if ($left['score'] !== $right['score']) {
        return $right['score'] <=> $left['score'];
      }

      return $left['row'] <=> $right['row'];
    });

    return (int)$candidates[0]['row'];
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function compactRowsTowardCenter(array &$nodes, array $edges): void
  {
    $centerRow = $this->layoutCenterRow($nodes);
    $attempt = 0;

    while ($attempt < 12) {
      $attempt++;
      $usedRows = $this->usedRows($nodes);
      usort($usedRows, static function (int $left, int $right) use ($centerRow): int {
        $leftDistance = abs($left - $centerRow);
        $rightDistance = abs($right - $centerRow);
        if ($leftDistance !== $rightDistance) {
          return $rightDistance <=> $leftDistance;
        }

        return $left <=> $right;
      });

      $moved = false;
      foreach ($usedRows as $sourceRow) {
        if ($sourceRow === $centerRow) {
          continue;
        }

        $direction = $sourceRow < $centerRow ? 1 : -1;
        for (
          $targetRow = $sourceRow + $direction;
          $direction > 0 ? $targetRow <= $centerRow : $targetRow >= $centerRow;
          $targetRow += $direction
        ) {
          if (!$this->canMoveRowWithoutOverlapOrCrossing($nodes, $edges, $sourceRow, $targetRow)) {
            continue;
          }

          $this->moveRow($nodes, $sourceRow, $targetRow);
          $moved = true;
          break 2;
        }
      }

      if (!$moved) {
        break;
      }
    }

    $this->normalizeRowIndexes($nodes);
    $this->assertNoCrossingEdges($nodes, $edges);
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function addNearbyShortcutConnections(string $seedKey, array $nodes, array &$edges, int $travelColumns): void
  {
    $nodeByIndex = $this->nodeByIndex($nodes);
    $outgoing = $this->outgoingByNode($nodes, $edges);

    $sortedIndexes = array_keys($nodeByIndex);
    usort($sortedIndexes, function (int $left, int $right) use ($nodeByIndex): int {
      $leftMeta = $nodeByIndex[$left]['meta'];
      $rightMeta = $nodeByIndex[$right]['meta'];
      if ((int)$leftMeta['col'] !== (int)$rightMeta['col']) {
        return ((int)$leftMeta['col']) <=> ((int)$rightMeta['col']);
      }

      return ((int)$leftMeta['row']) <=> ((int)$rightMeta['row']);
    });

    foreach ($sortedIndexes as $sourceIndex) {
      $sourceNode = $nodeByIndex[$sourceIndex];
      $sourceType = (string)($sourceNode['node_type'] ?? '');
      if (in_array($sourceType, ['boss', 'exit'], true) || count($outgoing[$sourceIndex] ?? []) !== 1) {
        continue;
      }

      $sourceMeta = is_array($sourceNode['meta'] ?? null) ? $sourceNode['meta'] : [];
      $sourceCol = (int)($sourceMeta['col'] ?? -1);
      $sourceRow = (int)($sourceMeta['row'] ?? -1);
      if ($sourceCol < 0 || $sourceCol >= $travelColumns) {
        continue;
      }

      $candidateIndexes = [];
      foreach ($sortedIndexes as $targetIndex) {
        if ($targetIndex === $sourceIndex || in_array($targetIndex, $outgoing[$sourceIndex] ?? [], true)) {
          continue;
        }

        $targetNode = $nodeByIndex[$targetIndex];
        $targetType = (string)($targetNode['node_type'] ?? '');
        if (in_array($targetType, ['boss', 'exit'], true)) {
          continue;
        }

        $targetMeta = is_array($targetNode['meta'] ?? null) ? $targetNode['meta'] : [];
        $targetCol = (int)($targetMeta['col'] ?? -1);
        $targetRow = (int)($targetMeta['row'] ?? -1);
        $columnDistance = $targetCol - $sourceCol;

        if ($columnDistance < 1 || $columnDistance > 3) {
          continue;
        }
        if (abs($targetRow - $sourceRow) > 1) {
          continue;
        }

        $candidateIndexes[] = $targetIndex;
      }

      usort($candidateIndexes, function (int $left, int $right) use ($nodeByIndex, $sourceCol, $sourceRow): int {
        $leftMeta = $nodeByIndex[$left]['meta'];
        $rightMeta = $nodeByIndex[$right]['meta'];
        $leftColumnDistance = abs((int)$leftMeta['col'] - $sourceCol);
        $rightColumnDistance = abs((int)$rightMeta['col'] - $sourceCol);
        if ($leftColumnDistance !== $rightColumnDistance) {
          return $leftColumnDistance <=> $rightColumnDistance;
        }

        $leftRowDistance = abs((int)$leftMeta['row'] - $sourceRow);
        $rightRowDistance = abs((int)$rightMeta['row'] - $sourceRow);
        if ($leftRowDistance !== $rightRowDistance) {
          return $leftRowDistance <=> $rightRowDistance;
        }

        return $left <=> $right;
      });

      foreach ($candidateIndexes as $targetIndex) {
        $shouldConnect = $this->randBetween(
          $seedKey . '|shortcut|' . $sourceIndex . '|' . $targetIndex,
          1,
          100,
        ) <= 20;
        if (!$shouldConnect) {
          continue;
        }

        if ($this->createsRedundantShortcutTriangle($sourceIndex, $targetIndex, $nodeByIndex, $outgoing)) {
          continue;
        }

        if (!$this->canAppendEdgeWithoutCrossing($nodes, $edges, $sourceIndex, $targetIndex)) {
          continue;
        }

        $this->appendEdge($edges, $sourceIndex, $targetIndex);
        $outgoing[$sourceIndex][] = $targetIndex;
        break;
      }
    }
  }

  /**
   * @return array<int,int>
   */
  private function buildLaneRows(int $rowCount, int $pathCount, int $centerRow): array
  {
    if ($pathCount <= 1) {
      return [$centerRow];
    }

    $maxIndex = $rowCount - 1;
    $rows = [];
    for ($laneIndex = 0; $laneIndex < $pathCount; $laneIndex++) {
      $ratio = $laneIndex / ($pathCount - 1);
      $row = (int)round($ratio * $maxIndex);
      $rows[] = max(0, min($maxIndex, $row));
    }

    $rows = array_values(array_unique($rows));
    sort($rows);

    while (count($rows) < $pathCount) {
      $candidate = min($maxIndex, end($rows) + 1);
      if (!in_array($candidate, $rows, true)) {
        $rows[] = $candidate;
      } else {
        break;
      }
    }

    return array_slice($rows, 0, $pathCount);
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<int,int>
   */
  private function nodeIndexesInColumn(array $nodes, int $column): array
  {
    $indexes = [];
    foreach ($nodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      if ((int)($meta['col'] ?? -1) !== $column) {
        continue;
      }

      $indexes[] = (int)$node['node_index'];
    }

    usort($indexes, function (int $left, int $right) use ($nodes): int {
      return ((int)$nodes[$left]['meta']['row']) <=> ((int)$nodes[$right]['meta']['row']);
    });

    return $indexes;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @param array<string,int> $nodeIndexByPosition
   */
  private function addDeadEndBranches(
    string $seedKey,
    array &$nodes,
    array &$edges,
    array &$occupied,
    array &$nodeIndexByPosition,
    int $travelColumns,
    int $rowCount,
    int $centerRow,
    int $targetCount,
    int $chainMin,
    int $chainMax,
  ): void {
    $parentIndexes = [];
    $outgoing = $this->outgoingByNode($nodes, $edges);

    foreach ($nodes as $node) {
      $nodeIndex = (int)$node['node_index'];
      $nodeType = (string)$node['node_type'];
      $col = (int)$node['meta']['col'];
      if ($nodeType === 'boss' || $nodeType === 'exit' || $col <= 0 || $col >= $travelColumns - 1) {
        continue;
      }
      if (count($outgoing[$nodeIndex] ?? []) === 0) {
        continue;
      }

      $parentIndexes[] = $nodeIndex;
    }

    $attempts = 0;
    $created = 0;
    while ($created < $targetCount && $attempts < max(12, count($parentIndexes) * 3) && $parentIndexes !== []) {
      $pickIndex = $this->randBetween($seedKey . '|dead-end-parent|' . $attempts, 0, count($parentIndexes) - 1);
      $parentIndex = $parentIndexes[$pickIndex];
      array_splice($parentIndexes, $pickIndex, 1);
      $attempts++;

      $parentMeta = $nodes[$parentIndex]['meta'];
      $chainLength = $this->randBetween($seedKey . '|dead-end-chain|' . $parentIndex, $chainMin, $chainMax);
      $candidate = $this->pickDeadEndCandidate(
        $seedKey . '|dead-end-start|' . $parentIndex,
        $nodes,
        $edges,
        $occupied,
        $nodeIndexByPosition,
        $parentIndex,
        (int)$parentMeta['col'],
        (int)$parentMeta['row'],
        $rowCount,
        $centerRow,
        $chainLength,
      );
      if ($candidate === null) {
        continue;
      }

      $previousIndex = $parentIndex;
      foreach ($candidate['steps'] as $stepIndex => $step) {
        $nodeIndex = $this->appendNode(
          $nodes,
          $occupied,
          $nodeIndexByPosition,
          'combat',
          'locked',
          (int)$step['col'],
          (int)$step['row'],
        );
        $this->appendEdge($edges, $previousIndex, $nodeIndex);
        $previousIndex = $nodeIndex;

        if ($stepIndex === 0) {
          $created++;
        }
      }
    }
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,bool> $occupied
   * @param array<string,int> $nodeIndexByPosition
   * @return array{from:int,col:int,row:int,steps:array<int,array{col:int,row:int}>}|null
   */
  private function pickDeadEndCandidate(
    string $seedKey,
    array $nodes,
    array $edges,
    array $occupied,
    array $nodeIndexByPosition,
    int $parentIndex,
    int $parentCol,
    int $parentRow,
    int $rowCount,
    int $centerRow,
    int $desiredLength,
  ): ?array {
    $preferredDirections = $parentRow <= $centerRow ? [-1, 1] : [1, -1];
    if ($this->randBetween($seedKey . '|direction-order', 0, 1) === 1) {
      $preferredDirections = array_reverse($preferredDirections);
    }

    foreach ($preferredDirections as $direction) {
      $steps = [];
      $currentCol = $parentCol;
      $currentRow = $parentRow;
      $proposedSegments = [];

      for ($stepNumber = 1; $stepNumber <= $desiredLength; $stepNumber++) {
        $nextCol = $currentCol + 1;
        $rowCandidates = $this->deadEndRowCandidates($currentRow, $direction, $rowCount);
        $pickedStep = null;

        foreach ($rowCandidates as $candidateRow) {
          $positionKey = $nextCol . ':' . $candidateRow;
          if (isset($occupied[$positionKey]) || isset($nodeIndexByPosition[$positionKey])) {
            continue;
          }

          $candidateSegments = array_merge($proposedSegments, [[
            'from' => ['col' => $currentCol, 'row' => $currentRow],
            'to' => ['col' => $nextCol, 'row' => $candidateRow],
          ]]);

          if ($this->proposedSegmentsCrossExisting($nodes, $edges, $candidateSegments)) {
            continue;
          }

          $pickedStep = ['col' => $nextCol, 'row' => $candidateRow];
          $proposedSegments = $candidateSegments;
          break;
        }

        if ($pickedStep === null) {
          break;
        }

        $steps[] = $pickedStep;
        $currentCol = (int)$pickedStep['col'];
        $currentRow = (int)$pickedStep['row'];
      }

      if ($steps !== []) {
        return [
          'from' => $parentIndex,
          'col' => (int)$steps[0]['col'],
          'row' => (int)$steps[0]['row'],
          'steps' => $steps,
        ];
      }
    }

    return null;
  }

  /**
   * @return array<int,int>
   */
  private function deadEndRowCandidates(int $currentRow, int $direction, int $rowCount): array
  {
    $candidates = [];
    foreach ([$currentRow + $direction, $currentRow, $currentRow - $direction] as $candidateRow) {
      if ($candidateRow < 0 || $candidateRow >= $rowCount || in_array($candidateRow, $candidates, true)) {
        continue;
      }

      $candidates[] = $candidateRow;
    }

    return $candidates;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function layoutCenterRow(array $nodes): int
  {
    foreach ($nodes as $node) {
      if ((string)($node['status'] ?? '') !== 'available') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      return (int)($meta['row'] ?? 0);
    }

    return intdiv($this->maxRowIndex($nodes), 2);
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<int,int>
   */
  private function usedRows(array $nodes): array
  {
    $rows = array_values(array_unique(array_map(
      static function (array $node): int {
        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        return (int)($meta['row'] ?? 0);
      },
      $nodes,
    )));
    sort($rows);

    return $rows;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function canMoveRowWithoutOverlapOrCrossing(array $nodes, array $edges, int $sourceRow, int $targetRow): bool
  {
    if ($sourceRow === $targetRow) {
      return false;
    }

    $candidateNodes = $nodes;
    foreach ($candidateNodes as $index => $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      if ((int)($meta['row'] ?? -1) !== $sourceRow) {
        continue;
      }

      $candidateNodes[$index]['meta']['row'] = $targetRow;
    }

    $positions = [];
    foreach ($candidateNodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $positionKey = (int)($meta['col'] ?? -1) . ':' . (int)($meta['row'] ?? -1);
      if (isset($positions[$positionKey])) {
        return false;
      }

      $positions[$positionKey] = true;
    }

    try {
      $this->assertNoCrossingEdges($candidateNodes, $edges);
    } catch (RuntimeException) {
      return false;
    }

    return true;
  }

  /**
   * @param array<int,array<string,mixed>> $nodeByIndex
   * @param array<int,array<int,int>> $outgoing
   */
  private function createsRedundantShortcutTriangle(int $sourceIndex, int $targetIndex, array $nodeByIndex, array $outgoing): bool
  {
    if (!isset($nodeByIndex[$sourceIndex], $nodeByIndex[$targetIndex])) {
      return false;
    }

    $sourceMeta = is_array($nodeByIndex[$sourceIndex]['meta'] ?? null) ? $nodeByIndex[$sourceIndex]['meta'] : [];
    $targetMeta = is_array($nodeByIndex[$targetIndex]['meta'] ?? null) ? $nodeByIndex[$targetIndex]['meta'] : [];
    $sourceCol = (int)($sourceMeta['col'] ?? -1);
    $targetCol = (int)($targetMeta['col'] ?? -1);

    foreach ($outgoing[$sourceIndex] ?? [] as $childIndex) {
      if (!isset($nodeByIndex[$childIndex])) {
        continue;
      }

      $childMeta = is_array($nodeByIndex[$childIndex]['meta'] ?? null) ? $nodeByIndex[$childIndex]['meta'] : [];
      $childCol = (int)($childMeta['col'] ?? -1);
      if ($childCol <= $sourceCol || $childCol >= $targetCol) {
        continue;
      }

      if (in_array($targetIndex, $outgoing[$childIndex] ?? [], true)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function moveRow(array &$nodes, int $sourceRow, int $targetRow): void
  {
    foreach ($nodes as $index => $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      if ((int)($meta['row'] ?? -1) !== $sourceRow) {
        continue;
      }

      $nodes[$index]['meta']['row'] = $targetRow;
    }
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function normalizeRowIndexes(array &$nodes): void
  {
    $usedRows = $this->usedRows($nodes);
    $rowMap = [];
    foreach ($usedRows as $index => $row) {
      $rowMap[$row] = $index;
    }

    foreach ($nodes as $index => $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $row = (int)($meta['row'] ?? 0);
      $nodes[$index]['meta']['row'] = $rowMap[$row] ?? $row;
    }
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @return array<int,array<string,mixed>>
   */
  private function nodeByIndex(array $nodes): array
  {
    $nodeByIndex = [];
    foreach ($nodes as $node) {
      $nodeByIndex[(int)$node['node_index']] = $node;
    }

    return $nodeByIndex;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function canAppendEdgeWithoutCrossing(array $nodes, array $edges, int $from, int $to): bool
  {
    foreach ($edges as $edge) {
      if ((int)$edge['from'] === $from && (int)$edge['to'] === $to) {
        return false;
      }
    }

    $nodeByIndex = $this->nodeByIndex($nodes);
    if (!isset($nodeByIndex[$from], $nodeByIndex[$to])) {
      return false;
    }

    $fromCol = (int)$nodeByIndex[$from]['meta']['col'];
    $toCol = (int)$nodeByIndex[$to]['meta']['col'];
    if ($toCol <= $fromCol) {
      return false;
    }

    $candidateEdges = $edges;
    $candidateEdges[] = ['from' => $from, 'to' => $to];

    try {
      $this->assertNoCrossingEdges($nodes, $candidateEdges);
    } catch (RuntimeException) {
      return false;
    }

    return true;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function removeRedundantSameRowBypassEdges(array $nodes, array &$edges): void
  {
    $nodeByIndex = $this->nodeByIndex($nodes);
    $outgoing = $this->outgoingByNode($nodes, $edges);
    $edgeKeysToRemove = [];

    foreach ($edges as $edge) {
      $from = (int)$edge['from'];
      $to = (int)$edge['to'];
      if (!isset($nodeByIndex[$from], $nodeByIndex[$to])) {
        continue;
      }

      $fromMeta = is_array($nodeByIndex[$from]['meta'] ?? null) ? $nodeByIndex[$from]['meta'] : [];
      $toMeta = is_array($nodeByIndex[$to]['meta'] ?? null) ? $nodeByIndex[$to]['meta'] : [];
      $fromCol = (int)($fromMeta['col'] ?? -1);
      $fromRow = (int)($fromMeta['row'] ?? -1);
      $toCol = (int)($toMeta['col'] ?? -1);

      if (($toCol - $fromCol) <= 1) {
        continue;
      }

      foreach ($outgoing[$from] ?? [] as $childIndex) {
        if (!isset($nodeByIndex[$childIndex])) {
          continue;
        }

        $childMeta = is_array($nodeByIndex[$childIndex]['meta'] ?? null) ? $nodeByIndex[$childIndex]['meta'] : [];
        $childCol = (int)($childMeta['col'] ?? -1);
        $childRow = (int)($childMeta['row'] ?? -1);

        if ($childCol !== ($fromCol + 1) || $childRow !== $fromRow) {
          continue;
        }

        if (in_array($to, $outgoing[$childIndex] ?? [], true)) {
          $edgeKeysToRemove[$from . '->' . $to] = true;
          break;
        }
      }
    }

    if ($edgeKeysToRemove === []) {
      return;
    }

    $edges = array_values(array_filter(
      $edges,
      static function (array $edge) use ($edgeKeysToRemove): bool {
        $key = (int)$edge['from'] . '->' . (int)$edge['to'];
        return !isset($edgeKeysToRemove[$key]);
      },
    ));
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<string,int> $config
   */
  private function assignProceduralNodeTypes(array &$nodes, array $edges, string $seedKey, string $regionSlug, array $config, int $travelColumns, bool $allowChaosNodes): void
  {
    $incoming = $this->incomingByNode($nodes, $edges);
    $outgoing = $this->outgoingByNode($nodes, $edges);

    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      if (in_array($nodeType, ['boss', 'exit'], true)) {
        continue;
      }

      $col = (int)($node['meta']['col'] ?? 0);
      if ((string)($node['status'] ?? '') === 'available') {
        $nodes[$index]['node_type'] = 'combat';
        continue;
      }

      $hazardRules = (new EncounterPrimitiveCatalog())->hazardEffectsForRegion($regionSlug, $col);
      $isDeadEnd = count($outgoing[$index] ?? []) === 0;
      $weights = $isDeadEnd
        ? ['loot' => 50, 'rest' => 30, 'combat' => 20, 'hazard' => $hazardRules !== [] ? 6 : 0, 'shrine' => 1, 'chaos' => 1]
        : [
          'combat' => (int)$config['combat_weight'],
          'loot' => (int)$config['loot_weight'],
          'rest' => (int)$config['rest_weight'],
          'hazard' => $hazardRules !== [] ? 2 : 0,
          'shrine' => 1,
          'chaos' => 1,
        ];

      if ($col <= 2) {
        $weights['combat'] += 4;
        $weights['rest'] = 0;
        $weights['hazard'] = 0;
        $weights['shrine'] = 0;
        $weights['chaos'] = 0;
      }
      if ($col >= $travelColumns - 1) {
        $weights['rest'] += 3;
        $weights['shrine'] += 1;
        $weights['chaos'] += 1;
      }

      $parentTypes = [];
      foreach ($incoming[$index] ?? [] as $parentIndex) {
        $parentTypes[] = (string)($nodes[$parentIndex]['node_type'] ?? '');
      }
      if (in_array('rest', $parentTypes, true)) {
        $weights['rest'] = 0;
      }
      if (in_array('shrine', $parentTypes, true)) {
        $weights['shrine'] = 0;
      }
      if (in_array('hazard', $parentTypes, true)) {
        $weights['hazard'] = 0;
      }
      if (in_array('chaos', $parentTypes, true)) {
        $weights['chaos'] = 0;
      }
      if (!$allowChaosNodes) {
        $weights['chaos'] = 0;
      }

      if (array_sum($weights) <= 0) {
        $weights = ['combat' => 1];
      }

      $nodes[$index]['node_type'] = $this->pickWeightedType($seedKey . '|node-type|' . $index, $weights);
    }
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function ensureAtLeastOneRestNode(array &$nodes, string $seedKey, int $travelColumns): void
  {
    foreach ($nodes as $node) {
      if ((string)($node['node_type'] ?? '') === 'rest') {
        return;
      }
    }

    $candidates = [];
    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      $status = (string)($node['status'] ?? '');
      if (!in_array($nodeType, ['combat', 'loot'], true) || $status === 'available') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      if ($col <= 1 || $col > $travelColumns) {
        continue;
      }

      $candidates[] = $index;
    }

    if ($candidates === []) {
      return;
    }

    usort($candidates, function (int $leftIndex, int $rightIndex) use ($nodes): int {
      $leftMeta = is_array($nodes[$leftIndex]['meta'] ?? null) ? $nodes[$leftIndex]['meta'] : [];
      $rightMeta = is_array($nodes[$rightIndex]['meta'] ?? null) ? $nodes[$rightIndex]['meta'] : [];
      $leftCol = (int)($leftMeta['col'] ?? 0);
      $rightCol = (int)($rightMeta['col'] ?? 0);

      if ($leftCol !== $rightCol) {
        return $rightCol <=> $leftCol;
      }

      return ((int)($leftMeta['row'] ?? 0)) <=> ((int)($rightMeta['row'] ?? 0));
    });

    $pickIndex = $this->randBetween($seedKey . '|force-rest', 0, count($candidates) - 1);
    $nodes[$candidates[$pickIndex]]['node_type'] = 'rest';
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function assignHazardEffects(array &$nodes, string $seedKey, string $regionSlug): void
  {
    $catalog = new EncounterPrimitiveCatalog();

    foreach ($nodes as $index => $node) {
      if ((string)($node['node_type'] ?? '') !== 'hazard') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? 0);
      $effects = $catalog->hazardEffectsForRegion($regionSlug, $col);
      if ($effects === []) {
        $nodes[$index]['node_type'] = 'combat';
        continue;
      }

      $effect = $this->pickWeightedHazardEffect($seedKey . '|hazard-effect|' . $index, $effects);
      $nodes[$index]['meta'] = [
        ...$meta,
        'encounter_family' => 'hazard',
        'encounter_effect_slug' => (string)$effect['slug'],
        'encounter_primitive' => (string)$effect['primitive'],
      ];
    }
  }

  /**
   * @param list<array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}> $effects
   * @return array{slug:string,primitive:string,regions:list<string>,min_depth:int,weight:int,result:array<string,mixed>}
   */
  private function pickWeightedHazardEffect(string $seedKey, array $effects): array
  {
    $total = array_sum(array_map(static fn(array $effect): int => max(0, (int)$effect['weight']), $effects));
    if ($total <= 0) {
      return $effects[0];
    }

    $cursor = $this->randBetween($seedKey, 0, $total - 1);
    foreach ($effects as $effect) {
      $weight = max(0, (int)$effect['weight']);
      if ($weight <= 0) {
        continue;
      }
      if ($cursor < $weight) {
        return $effect;
      }
      $cursor -= $weight;
    }

    return $effects[0];
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function ensureAtLeastOneChaosNode(array &$nodes, string $seedKey, int $travelColumns): void
  {
    foreach ($nodes as $node) {
      if ((string)($node['node_type'] ?? '') === 'chaos') {
        return;
      }
    }

    $candidates = [];
    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      $status = (string)($node['status'] ?? '');
      if (!in_array($nodeType, ['combat', 'loot', 'shrine'], true) || $status === 'available') {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? -1);
      if ($col <= 2 || $col >= $travelColumns) {
        continue;
      }

      $candidates[] = $index;
    }

    if ($candidates === []) {
      return;
    }

    $pickIndex = $this->randBetween($seedKey . '|force-chaos', 0, count($candidates) - 1);
    $picked = $candidates[$pickIndex];
    $nodes[$picked]['node_type'] = 'chaos';
    $nodes[$picked]['encounter_template_id'] = null;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function assignNodeQualityTiers(array &$nodes, array $edges, int $travelColumns): void
  {
    $outgoing = $this->outgoingByNode($nodes, $edges);

    foreach ($nodes as $index => $node) {
      $nodeType = (string)($node['node_type'] ?? '');
      if (!in_array($nodeType, ['loot', 'shrine'], true)) {
        continue;
      }

      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $col = (int)($meta['col'] ?? 0);
      $isDeadEnd = count($outgoing[(int)($node['node_index'] ?? $index)] ?? []) === 0;

      $tier = 'good';
      if ($isDeadEnd || $col >= max(3, $travelColumns - 1)) {
        $tier = 'great';
      } elseif ($col <= 2) {
        $tier = 'poor';
      }

      $nodes[$index]['meta'] = [
        ...$meta,
        'node_quality_tier' => $tier,
      ];
    }
  }

  /**
   * @param array<string,int> $weights
   */
  private function pickWeightedType(string $seedKey, array $weights): string
  {
    $weights = array_filter($weights, static fn(int $weight): bool => $weight > 0);
    if ($weights === []) {
      return 'combat';
    }

    $total = array_sum($weights);
    $roll = $this->randBetween($seedKey, 1, $total);
    $running = 0;

    foreach ($weights as $type => $weight) {
      $running += $weight;
      if ($roll <= $running) {
        return $type;
      }
    }

    return array_key_first($weights) ?? 'combat';
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @return array<int,array<int,int>>
   */
  private function outgoingByNode(array $nodes, array $edges): array
  {
    $outgoing = [];
    foreach ($nodes as $node) {
      $outgoing[(int)$node['node_index']] = [];
    }

    foreach ($edges as $edge) {
      $outgoing[(int)$edge['from']][] = (int)$edge['to'];
    }

    return $outgoing;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @return array<int,array<int,int>>
   */
  private function incomingByNode(array $nodes, array $edges): array
  {
    $incoming = [];
    foreach ($nodes as $node) {
      $incoming[(int)$node['node_index']] = [];
    }

    foreach ($edges as $edge) {
      $incoming[(int)$edge['to']][] = (int)$edge['from'];
    }

    return $incoming;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function maxRowIndex(array $nodes): int
  {
    $max = 0;
    foreach ($nodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $max = max($max, (int)($meta['row'] ?? 0));
    }

    return $max;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   */
  private function maxRunColumn(array $nodes): int
  {
    $max = 0;
    foreach ($nodes as $node) {
      $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
      $max = max($max, (int)($meta['col'] ?? 0));
    }

    return $max;
  }

  /**
   * @param array<int,array<int,int>> $outgoing
   * @return array<int,bool>
   */
  private function reachableNodeIndexes(int $startIndex, array $outgoing): array
  {
    $queue = [$startIndex];
    $visited = [];

    while ($queue !== []) {
      $current = array_shift($queue);
      if ($current === null || isset($visited[$current])) {
        continue;
      }

      $visited[$current] = true;
      foreach ($outgoing[$current] ?? [] as $next) {
        if (!isset($visited[$next])) {
          $queue[] = $next;
        }
      }
    }

    return $visited;
  }

  private function randBetween(string $seedKey, int $min, int $max): int
  {
    if ($min >= $max) {
      return $min;
    }

    $material = hash('sha256', $seedKey . '|rng|' . $this->rngCounter++);
    $value = hexdec(substr($material, 0, 8));
    return $min + ($value % (($max - $min) + 1));
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   * @param array<int,array{from:array{col:int,row:int},to:array{col:int,row:int}}> $proposedSegments
   */
  private function proposedSegmentsCrossExisting(array $nodes, array $edges, array $proposedSegments): bool
  {
    $nodeByIndex = [];
    foreach ($nodes as $node) {
      $nodeByIndex[(int)$node['node_index']] = $node;
    }

    foreach ($proposedSegments as $segment) {
      $from = $segment['from'];
      $to = $segment['to'];
      foreach ($edges as $edge) {
        $existingFrom = $nodeByIndex[(int)$edge['from']]['meta'];
        $existingTo = $nodeByIndex[(int)$edge['to']]['meta'];
        if ($this->segmentsShareEndpoint(
          (int)$from['col'],
          (int)$from['row'],
          (int)$to['col'],
          (int)$to['row'],
          (int)$existingFrom['col'],
          (int)$existingFrom['row'],
          (int)$existingTo['col'],
          (int)$existingTo['row'],
        )) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)$from['col'],
          (int)$from['row'],
          (int)$to['col'],
          (int)$to['row'],
          (int)$existingFrom['col'],
          (int)$existingFrom['row'],
          (int)$existingTo['col'],
          (int)$existingTo['row'],
        )) {
          return true;
        }
      }
    }

    $segmentCount = count($proposedSegments);
    for ($leftIndex = 0; $leftIndex < $segmentCount; $leftIndex++) {
      $left = $proposedSegments[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $segmentCount; $rightIndex++) {
        $right = $proposedSegments[$rightIndex];
        if ($this->segmentsShareEndpoint(
          (int)$left['from']['col'],
          (int)$left['from']['row'],
          (int)$left['to']['col'],
          (int)$left['to']['row'],
          (int)$right['from']['col'],
          (int)$right['from']['row'],
          (int)$right['to']['col'],
          (int)$right['to']['row'],
        )) {
          continue;
        }

        if ($this->segmentsIntersect(
          (int)$left['from']['col'],
          (int)$left['from']['row'],
          (int)$left['to']['col'],
          (int)$left['to']['row'],
          (int)$right['from']['col'],
          (int)$right['from']['row'],
          (int)$right['to']['col'],
          (int)$right['to']['row'],
        )) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * @param array<int,array<string,mixed>> $nodes
   * @param array<int,array{from:int,to:int}> $edges
   */
  private function assertNoCrossingEdges(array $nodes, array $edges): void
  {
    $nodeByIndex = [];
    foreach ($nodes as $node) {
      $nodeByIndex[(int)$node['node_index']] = $node;
    }

    $edgeCount = count($edges);
    for ($leftIndex = 0; $leftIndex < $edgeCount; $leftIndex++) {
      $leftEdge = $edges[$leftIndex];
      for ($rightIndex = $leftIndex + 1; $rightIndex < $edgeCount; $rightIndex++) {
        $rightEdge = $edges[$rightIndex];
        if (
          $leftEdge['from'] === $rightEdge['from']
          || $leftEdge['from'] === $rightEdge['to']
          || $leftEdge['to'] === $rightEdge['from']
          || $leftEdge['to'] === $rightEdge['to']
        ) {
          continue;
        }

        $a = $nodeByIndex[$leftEdge['from']]['meta'];
        $b = $nodeByIndex[$leftEdge['to']]['meta'];
        $c = $nodeByIndex[$rightEdge['from']]['meta'];
        $d = $nodeByIndex[$rightEdge['to']]['meta'];

        if ($this->segmentsIntersect(
          (int)$a['col'],
          (int)$a['row'],
          (int)$b['col'],
          (int)$b['row'],
          (int)$c['col'],
          (int)$c['row'],
          (int)$d['col'],
          (int)$d['row'],
        )) {
          throw new RuntimeException(sprintf(
            'Run graph edges may not visually cross (%d->%d [%d,%d]->[%d,%d] x %d->%d [%d,%d]->[%d,%d]).',
            (int)$leftEdge['from'],
            (int)$leftEdge['to'],
            (int)$a['col'],
            (int)$a['row'],
            (int)$b['col'],
            (int)$b['row'],
            (int)$rightEdge['from'],
            (int)$rightEdge['to'],
            (int)$c['col'],
            (int)$c['row'],
            (int)$d['col'],
            (int)$d['row'],
          ));
        }
      }
    }
  }

  private function segmentsIntersect(
    int $ax,
    int $ay,
    int $bx,
    int $by,
    int $cx,
    int $cy,
    int $dx,
    int $dy,
  ): bool {
    $o1 = $this->orientation($ax, $ay, $bx, $by, $cx, $cy);
    $o2 = $this->orientation($ax, $ay, $bx, $by, $dx, $dy);
    $o3 = $this->orientation($cx, $cy, $dx, $dy, $ax, $ay);
    $o4 = $this->orientation($cx, $cy, $dx, $dy, $bx, $by);

    if ($o1 !== $o2 && $o3 !== $o4) {
      return true;
    }

    if ($o1 === 0 && $this->onSegment($ax, $ay, $bx, $by, $cx, $cy)) {
      return true;
    }
    if ($o2 === 0 && $this->onSegment($ax, $ay, $bx, $by, $dx, $dy)) {
      return true;
    }
    if ($o3 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $ax, $ay)) {
      return true;
    }
    if ($o4 === 0 && $this->onSegment($cx, $cy, $dx, $dy, $bx, $by)) {
      return true;
    }

    return false;
  }

  private function segmentsShareEndpoint(
    int $ax,
    int $ay,
    int $bx,
    int $by,
    int $cx,
    int $cy,
    int $dx,
    int $dy,
  ): bool {
    return ($ax === $cx && $ay === $cy)
      || ($ax === $dx && $ay === $dy)
      || ($bx === $cx && $by === $cy)
      || ($bx === $dx && $by === $dy);
  }

  private function orientation(int $ax, int $ay, int $bx, int $by, int $cx, int $cy): int
  {
    $value = (($by - $ay) * ($cx - $bx)) - (($bx - $ax) * ($cy - $by));
    if ($value === 0) {
      return 0;
    }

    return $value > 0 ? 1 : 2;
  }

  private function onSegment(int $ax, int $ay, int $bx, int $by, int $px, int $py): bool
  {
    return $px >= min($ax, $bx)
      && $px <= max($ax, $bx)
      && $py >= min($ay, $by)
      && $py <= max($ay, $by);
  }
}
