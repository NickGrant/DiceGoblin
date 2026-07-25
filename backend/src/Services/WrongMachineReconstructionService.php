<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use RuntimeException;
use Throwable;

final class WrongMachineReconstructionService
{
  private const PIG_KIN_COST = [
    'lineage_slug' => LineageUnlockService::PIG_KIN,
    'unit_type_slug' => 'frontline_bruiser_t1',
    'raw_chaos' => 5,
    'items' => [
      ['item_slug' => 'pig_ear', 'quantity' => 3],
      ['item_slug' => 'mudking_crown_fragment', 'quantity' => 1],
    ],
  ];

  public function __construct(
    private readonly PDO $pdo,
    private ?UserUnlockService $userUnlockService = null,
    private ?LineageUnlockService $lineageUnlockService = null,
    private ?ItemInventoryService $itemInventoryService = null,
    private ?PlayerStateRepository $playerStateRepository = null,
    private ?UserAssetGrantService $userAssetGrantService = null,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function preview(int $userId): array
  {
    return [
      'feature_unlocked' => $this->hasWrongMachine($userId),
      'reconstructions' => [
        $this->previewPigKin($userId),
      ],
    ];
  }

  /**
   * @return array<string,mixed>
   */
  public function reconstruct(int $userId, string $lineageSlug): array
  {
    $lineageSlug = trim($lineageSlug);
    if ($lineageSlug !== LineageUnlockService::PIG_KIN) {
      throw new RuntimeException('unknown_lineage');
    }

    if (!$this->hasWrongMachine($userId)) {
      throw new RuntimeException('wrong_machine_locked');
    }

    return $this->withinTransaction(function () use ($userId): array {
      $this->lockLineageUnlock($userId, LineageUnlockService::PIG_KIN);
      if ($this->lineageUnlockService()->isUnlocked($userId, LineageUnlockService::PIG_KIN)) {
        return [
          'lineage' => $this->ownedLineage($userId, LineageUnlockService::PIG_KIN),
          'newly_reconstructed' => false,
          'spent' => [
            'raw_chaos' => 0,
            'items' => [],
          ],
          'granted_unit' => null,
          'preview' => $this->previewPigKin($userId),
        ];
      }

      $playerStateRepository = $this->playerStateRepository();
      $playerStateRepository->ensurePlayerState($userId);
      $state = $playerStateRepository->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        throw new RuntimeException('player_state_unavailable');
      }

      $rawChaosCost = (int)self::PIG_KIN_COST['raw_chaos'];
      $ownedRawChaos = max(0, (int)($state['currency_raw_chaos'] ?? 0));
      if ($ownedRawChaos < $rawChaosCost) {
        throw new RuntimeException('insufficient_raw_chaos');
      }

      $spentItems = [];
      foreach (self::PIG_KIN_COST['items'] as $itemCost) {
        $spentItems[] = $this->itemInventoryService()->spendBySlugForUpdate(
          $userId,
          (string)$itemCost['item_slug'],
          (int)$itemCost['quantity']
        );
      }

      $playerStateRepository->setRawChaos($userId, $ownedRawChaos - $rawChaosCost);
      $this->lineageUnlockService()->grant($userId, LineageUnlockService::PIG_KIN);
      $grantedUnit = $this->userAssetGrantService()->grantUnitBySlug(
        $userId,
        (string)self::PIG_KIN_COST['unit_type_slug'],
        1,
        1,
        0,
        false,
        null,
        LineageUnlockService::PIG_KIN
      );

      return [
        'lineage' => $this->ownedLineage($userId, LineageUnlockService::PIG_KIN),
        'newly_reconstructed' => true,
        'spent' => [
          'raw_chaos' => $rawChaosCost,
          'items' => $spentItems,
        ],
        'granted_unit' => [
          'id' => (string)$grantedUnit['id'],
          'unit_type_slug' => (string)$grantedUnit['unit_type_slug'],
          'kin_slug' => (string)$grantedUnit['splice_variant_slug'],
          'splice_variant_slug' => (string)$grantedUnit['splice_variant_slug'],
        ],
        'preview' => $this->previewPigKin($userId),
      ];
    });
  }

  /**
   * @return array<string,mixed>
   */
  private function previewPigKin(int $userId): array
  {
    $ownedItems = [];
    foreach ($this->itemInventoryService()->listForUser($userId) as $item) {
      $ownedItems[(string)$item['item_slug']] = (int)$item['quantity'];
    }

    $currency = $this->playerStateRepository()->getCurrency($userId);
    $itemCosts = [];
    $missing = [];
    foreach (self::PIG_KIN_COST['items'] as $itemCost) {
      $slug = (string)$itemCost['item_slug'];
      $required = (int)$itemCost['quantity'];
      $owned = max(0, (int)($ownedItems[$slug] ?? 0));
      $itemCosts[] = [
        'item_slug' => $slug,
        'quantity_required' => $required,
        'quantity_owned' => $owned,
        'is_met' => $owned >= $required,
      ];
      if ($owned < $required) {
        $missing[] = ['type' => 'item', 'item_slug' => $slug, 'quantity_missing' => $required - $owned];
      }
    }

    $rawChaosRequired = (int)self::PIG_KIN_COST['raw_chaos'];
    $rawChaosOwned = max(0, (int)($currency['raw_chaos'] ?? 0));
    if ($rawChaosOwned < $rawChaosRequired) {
      $missing[] = ['type' => 'raw_chaos', 'quantity_missing' => $rawChaosRequired - $rawChaosOwned];
    }

    $alreadyUnlocked = $this->lineageUnlockService()->isUnlocked($userId, LineageUnlockService::PIG_KIN);

    return [
      'lineage_slug' => LineageUnlockService::PIG_KIN,
      'kin_slug' => LineageUnlockService::PIG_KIN,
      'name' => 'Pig Kin',
      'description' => 'Reconstruct the first goblin-kin lineage from Farm materials.',
      'is_unlocked' => $alreadyUnlocked,
      'can_reconstruct' => !$alreadyUnlocked && $this->hasWrongMachine($userId) && $missing === [],
      'cost' => [
        'raw_chaos' => [
          'quantity_required' => $rawChaosRequired,
          'quantity_owned' => $rawChaosOwned,
          'is_met' => $rawChaosOwned >= $rawChaosRequired,
        ],
        'items' => $itemCosts,
      ],
      'missing' => $missing,
      'grants' => [
        'lineage_slug' => LineageUnlockService::PIG_KIN,
        'unit_type_slug' => (string)self::PIG_KIN_COST['unit_type_slug'],
        'unit_count' => 1,
      ],
    ];
  }

  private function hasWrongMachine(int $userId): bool
  {
    return $this->userUnlockService()->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE);
  }

  private function lockLineageUnlock(int $userId, string $lineageSlug): void
  {
    $stmt = $this->pdo->prepare('
      SELECT `user_id`
      FROM `user_unlocks`
      WHERE `user_id` = ? AND `unlock_namespace` = ? AND `unlock_key` = ?
      LIMIT 1
      FOR UPDATE
    ');
    $stmt->execute([$userId, UserUnlockService::NAMESPACE_LINEAGE, $lineageSlug]);
  }

  /**
   * @return array<string,mixed>|null
   */
  private function ownedLineage(int $userId, string $lineageSlug): ?array
  {
    foreach ($this->lineageUnlockService()->listForUser($userId) as $lineage) {
      if ((string)$lineage['lineage_slug'] === $lineageSlug) {
        return $lineage;
      }
    }

    return null;
  }

  /**
   * @template T
   * @param callable():T $callback
   * @return T
   */
  private function withinTransaction(callable $callback): mixed
  {
    $ownsTransaction = false;
    try {
      if (!$this->pdo->inTransaction()) {
        $this->pdo->beginTransaction();
        $ownsTransaction = true;
      }

      $result = $callback();
      if ($ownsTransaction) {
        $this->pdo->commit();
      }

      return $result;
    } catch (Throwable $throwable) {
      if ($ownsTransaction && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $throwable;
    }
  }

  private function userUnlockService(): UserUnlockService
  {
    $this->userUnlockService ??= new UserUnlockService($this->pdo);
    return $this->userUnlockService;
  }

  private function lineageUnlockService(): LineageUnlockService
  {
    $this->lineageUnlockService ??= new LineageUnlockService($this->pdo);
    return $this->lineageUnlockService;
  }

  private function itemInventoryService(): ItemInventoryService
  {
    $this->itemInventoryService ??= new ItemInventoryService($this->pdo);
    return $this->itemInventoryService;
  }

  private function playerStateRepository(): PlayerStateRepository
  {
    $this->playerStateRepository ??= new PlayerStateRepository($this->pdo);
    return $this->playerStateRepository;
  }

  private function userAssetGrantService(): UserAssetGrantService
  {
    $this->userAssetGrantService ??= new UserAssetGrantService($this->pdo);
    return $this->userAssetGrantService;
  }
}
