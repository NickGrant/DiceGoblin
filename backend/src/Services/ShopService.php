<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\EnergyRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use RuntimeException;
use Throwable;

final class ShopService
{
  private const BASIC_UNIT_COST = 32;
  private const FEATURE_UNLOCK_COSTS = [
    UserUnlockService::FEATURE_ACADEMY => 250,
    UserUnlockService::FEATURE_BIGGER_SQUAD => 500,
    UserUnlockService::FEATURE_BIGGEREST_SQUAD => 1000,
    UserUnlockService::FEATURE_SHOP_DISCOUNT => 500,
    UserUnlockService::FEATURE_SELL_BONUS => 500,
    UserUnlockService::FEATURE_MARKET_MASTERY => 1000,
    UserUnlockService::FEATURE_SECOND_DAILY_DEAL => 500,
    UserUnlockService::FEATURE_ENERGY_75 => 750,
    UserUnlockService::FEATURE_ENERGY_100 => 1250,
    UserUnlockService::FEATURE_D4_EXPLODE => 2000,
  ];

  private UserAssetGrantService $userAssetGrantService;

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerStateRepository,
    ?UserAssetGrantService $userAssetGrantService = null,
  ) {
    $this->userAssetGrantService = $userAssetGrantService ?? new UserAssetGrantService($pdo);
  }

  /**
   * @return array{
   *   server_date:string,
   *   currency_soft:int,
   *   basic_dice:array<int,array{product_id:string,label:string,rarity:string,sides:int,cost:int}>,
   *   basic_units:array<int,array{product_id:string,unit_type_slug:string,name:string,role:string,cost:int}>,
   *   feature_unlocks:array<int,array{
   *     product_id:string,
   *     name:string,
   *     description:string,
   *     cost:int,
   *     is_unlocked:bool,
   *     category:string,
   *     requires_unlock_key:?string,
   *     is_available:bool
   *   }>,
   *   daily_deal:?array<string,mixed>,
   *   daily_deals:array<int,array<string,mixed>>
   * }
   */
  public function buildCatalog(int $userId, bool $lockDeal = false): array
  {
    $this->playerStateRepository->ensurePlayerState($userId);
    $currency = $this->playerStateRepository->getCurrency($userId);
    $shopDate = gmdate('Y-m-d');
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);
    $dailyDeals = $this->resolveDailyDeals($userId, $shopDate, $lockDeal, $featureUnlocks);

    return [
      'server_date' => $shopDate,
      'currency_soft' => max(0, (int)($currency['soft'] ?? 0)),
      'basic_dice' => $this->listBasicDiceCatalog($featureUnlocks),
      'basic_units' => $this->listBasicUnitCatalog($userId, $featureUnlocks),
      'feature_unlocks' => $this->listFeatureUnlockCatalog($userId, $featureUnlocks),
      'daily_deal' => $dailyDeals[0] ?? null,
      'daily_deals' => $dailyDeals,
    ];
  }

  /**
   * @return array{
   *   item_type:string,
   *   product_id:string,
   *   cost:int,
   *   currency_soft:int,
   *   purchase:array<string,mixed>
   * }
   */
  public function purchase(int $userId, string $itemType, string $productId): array
  {
    if (!in_array($itemType, ['basic_unit', 'basic_dice', 'daily_deal', 'feature_unlock'], true)) {
      throw new RuntimeException('item_type is required.');
    }

    try {
      $this->pdo->beginTransaction();
      $this->playerStateRepository->ensurePlayerState($userId);
      $state = $this->playerStateRepository->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        throw new RuntimeException('Player state unavailable.');
      }

      $purchase = match ($itemType) {
        'basic_unit' => $this->purchaseBasicUnit($userId, $productId),
        'basic_dice' => $this->purchaseBasicDice($userId, $productId),
        'daily_deal' => $this->purchaseDailyDeal($userId, $productId),
        'feature_unlock' => $this->purchaseFeatureUnlock($userId, $productId),
      };

      $cost = (int)$purchase['cost'];
      $currentSoft = max(0, (int)$state['currency_soft']);
      if ($currentSoft < $cost) {
        throw new RuntimeException('Not enough soft currency.');
      }

      $nextSoft = $currentSoft - $cost;
      $this->playerStateRepository->setCurrency($userId, $nextSoft, max(0, (int)$state['currency_hard']));
      $this->pdo->commit();

      return [
        'item_type' => $itemType,
        'product_id' => (string)$purchase['product_id'],
        'cost' => $cost,
        'currency_soft' => $nextSoft,
        'purchase' => $purchase['purchase'],
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array<int,array{product_id:string,label:string,rarity:string,sides:int,cost:int}>
   */
  private function listBasicDiceCatalog(array $featureUnlocks): array
  {
    $stmt = $this->pdo->query("
      SELECT `id`, `rarity`, `sides`
      FROM `dice_definitions`
      WHERE `rarity` = 'common' AND `sides` IN (4, 6, 8, 10)
      ORDER BY `sides` ASC, `id` ASC
    ");

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $sides = max(2, (int)($row['sides'] ?? 6));
      $out[] = [
        'product_id' => 'common_d' . $sides,
        'label' => 'Common d' . $sides,
        'rarity' => 'common',
        'sides' => $sides,
        'cost' => EconomyModifierService::adjustedShopCost(
          DiceValuationService::calculateValue($sides, 'common'),
          $featureUnlocks
        ),
      ];
    }

    return $out;
  }

  /**
   * @return array<int,array{product_id:string,unit_type_slug:string,name:string,role:string,cost:int}>
   */
  private function listBasicUnitCatalog(int $userId, array $featureUnlocks): array
  {
    $unlockService = new UserUnlockService($this->pdo);
    $unlockedUnitSlugs = $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE);
    if (count($unlockedUnitSlugs) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unlockedUnitSlugs), '?'));
    $stmt = $this->pdo->prepare("
      SELECT `slug`, `name`, `role`
      FROM `unit_types`
      WHERE RIGHT(`slug`, 3) = '_t1'
        AND `slug` IN ($placeholders)
      ORDER BY `id` ASC
    ");
    $stmt->execute($unlockedUnitSlugs);

    return array_map(static fn(array $row): array => [
      'product_id' => (string)$row['slug'],
      'unit_type_slug' => (string)$row['slug'],
      'name' => (string)$row['name'],
      'role' => (string)$row['role'],
      'cost' => EconomyModifierService::adjustedShopCost(self::BASIC_UNIT_COST, $featureUnlocks),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return array<int,array{
   *   product_id:string,
   *   name:string,
   *   description:string,
   *   cost:int,
   *   is_unlocked:bool,
   *   category:string,
   *   requires_unlock_key:?string,
   *   is_available:bool
   * }>
   */
  private function listFeatureUnlockCatalog(int $userId, array $featureUnlocks): array
  {
    $unlockService = new UserUnlockService($this->pdo);
    $academyUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ACADEMY);
    $biggerSquadUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_BIGGER_SQUAD);
    $biggerestSquadUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_BIGGEREST_SQUAD);
    $shopDiscountUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP_DISCOUNT);
    $sellBonusUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SELL_BONUS);
    $marketMasteryUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_MARKET_MASTERY);
    $secondDailyDealUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SECOND_DAILY_DEAL);
    $energy75Unlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ENERGY_75);
    $energy100Unlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ENERGY_100);
    $d4ExplodeUnlocked = $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_D4_EXPLODE);

    return [[
      'product_id' => UserUnlockService::FEATURE_ACADEMY,
      'name' => 'Academy',
      'description' => 'Unlock promotions and unit-type research for your warband.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_ACADEMY],
        $featureUnlocks
      ),
      'is_unlocked' => $academyUnlocked,
      'category' => 'feature',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_BIGGER_SQUAD,
      'name' => 'Bigger Squad',
      'description' => 'Raise your squad size cap from 4 units to 6.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_BIGGER_SQUAD],
        $featureUnlocks
      ),
      'is_unlocked' => $biggerSquadUnlocked,
      'category' => 'squad_upgrade',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_BIGGEREST_SQUAD,
      'name' => 'Biggerest Squad',
      'description' => 'Raise your squad size cap from 6 units to the full 9-slot formation.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_BIGGEREST_SQUAD],
        $featureUnlocks
      ),
      'is_unlocked' => $biggerestSquadUnlocked,
      'category' => 'squad_upgrade',
      'requires_unlock_key' => UserUnlockService::FEATURE_BIGGER_SQUAD,
      'is_available' => $biggerSquadUnlocked || $biggerestSquadUnlocked,
    ], [
      'product_id' => UserUnlockService::FEATURE_SHOP_DISCOUNT,
      'name' => 'Coupon Book',
      'description' => 'Make all future shop purchases cost 10% less.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_SHOP_DISCOUNT],
        $featureUnlocks
      ),
      'is_unlocked' => $shopDiscountUnlocked,
      'category' => 'economy_upgrade',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_SELL_BONUS,
      'name' => 'Sharp Dealer',
      'description' => 'Make dice sales pay out 10% more teeth.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_SELL_BONUS],
        $featureUnlocks
      ),
      'is_unlocked' => $sellBonusUnlocked,
      'category' => 'economy_upgrade',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_MARKET_MASTERY,
      'name' => 'Market Mastery',
      'description' => 'Improve both shop discounts and sale payouts to 20% once your traders are fully trained.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_MARKET_MASTERY],
        $featureUnlocks
      ),
      'is_unlocked' => $marketMasteryUnlocked,
      'category' => 'economy_upgrade',
      'requires_unlock_key' => 'shop_discount_and_sell_bonus',
      'is_available' => ($shopDiscountUnlocked && $sellBonusUnlocked) || $marketMasteryUnlocked,
    ], [
      'product_id' => UserUnlockService::FEATURE_SECOND_DAILY_DEAL,
      'name' => 'Second Deal',
      'description' => 'Add a second daily deal slot so the shop offers two rotating featured dice each day.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_SECOND_DAILY_DEAL],
        $featureUnlocks
      ),
      'is_unlocked' => $secondDailyDealUnlocked,
      'category' => 'feature',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_ENERGY_75,
      'name' => 'Deep Pantry',
      'description' => 'Raise your max energy from 50 to 75.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_ENERGY_75],
        $featureUnlocks
      ),
      'is_unlocked' => $energy75Unlocked,
      'category' => 'energy_upgrade',
      'requires_unlock_key' => null,
      'is_available' => true,
    ], [
      'product_id' => UserUnlockService::FEATURE_ENERGY_100,
      'name' => 'Bottomless Pantry',
      'description' => 'Raise your max energy from 75 to 100.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_ENERGY_100],
        $featureUnlocks
      ),
      'is_unlocked' => $energy100Unlocked,
      'category' => 'energy_upgrade',
      'requires_unlock_key' => UserUnlockService::FEATURE_ENERGY_75,
      'is_available' => $energy75Unlocked || $energy100Unlocked,
    ], [
      'product_id' => UserUnlockService::FEATURE_D4_EXPLODE,
      'name' => 'Loaded Caltrops',
      'description' => 'All d4s gain a one-time explode when they roll max during combat.',
      'cost' => EconomyModifierService::adjustedShopCost(
        self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_D4_EXPLODE],
        $featureUnlocks
      ),
      'is_unlocked' => $d4ExplodeUnlocked,
      'category' => 'dice_upgrade',
      'requires_unlock_key' => null,
      'is_available' => true,
    ]];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function resolveDailyDeals(int $userId, string $shopDate, bool $lockRow, array $featureUnlocks): array
  {
    $deals = [];
    $slotCount = $this->dailyDealSlotCount($featureUnlocks);
    for ($slot = 1; $slot <= $slotCount; $slot++) {
      $deal = $this->resolveDailyDeal($userId, $shopDate, $slot, $lockRow, $featureUnlocks);
      if (is_array($deal)) {
        $deals[] = $deal;
      }
    }

    return $deals;
  }

  /**
   * @return array<string,mixed>|null
   */
  private function resolveDailyDeal(int $userId, string $shopDate, int $slot, bool $lockRow, array $featureUnlocks): ?array
  {
    $sql = "
      SELECT
        sdd.`id`,
        sdd.`shop_date`,
        sdd.`deal_slot`,
        sdd.`purchased_at`,
        dd.`sides`,
        dd.`rarity`,
        ad.`slug` AS `affix_slug`,
        ad.`name` AS `affix_name`,
        ad.`description`,
        ad.`rarity` AS `affix_rarity`,
        sdd.`affix_value`
      FROM `shop_daily_deals` sdd
      JOIN `dice_definitions` dd ON dd.`id` = sdd.`dice_definition_id`
      JOIN `affix_definitions` ad ON ad.`id` = sdd.`affix_definition_id`
      WHERE sdd.`user_id` = ? AND sdd.`shop_date` = ? AND sdd.`deal_slot` = ?
      LIMIT 1
    ";
    if ($lockRow) {
      $sql .= ' FOR UPDATE';
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$userId, $shopDate, $slot]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      $row = $this->createDailyDeal($userId, $shopDate, $slot);
    }

    $sides = max(2, (int)($row['sides'] ?? 6));
    $dealCost = DiceValuationService::calculateValue(
      $sides,
      (string)($row['rarity'] ?? 'uncommon'),
      [['rarity' => (string)($row['affix_rarity'] ?? 'uncommon')]]
    );

    return [
      'product_id' => 'daily_deal_' . $slot,
      'shop_date' => (string)($row['shop_date'] ?? $shopDate),
      'slot' => max(1, (int)($row['deal_slot'] ?? $slot)),
      'sides' => $sides,
      'rarity' => (string)($row['rarity'] ?? 'uncommon'),
      'cost' => EconomyModifierService::adjustedShopCost($dealCost, $featureUnlocks),
      'is_purchased' => !empty($row['purchased_at']),
      'affix' => [
        'slug' => (string)($row['affix_slug'] ?? ''),
        'name' => (string)($row['affix_name'] ?? 'Affix'),
        'description' => (string)($row['description'] ?? ''),
        'rarity' => (string)($row['affix_rarity'] ?? 'common'),
        'value' => (float)($row['affix_value'] ?? 0),
      ],
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function createDailyDeal(int $userId, string $shopDate, int $slot): array
  {
    $diceDefs = $this->pdo->query("
      SELECT `id`, `sides`, `rarity`
      FROM `dice_definitions`
      WHERE `rarity` = 'uncommon' AND `sides` IN (4, 6, 8, 10)
      ORDER BY `sides` ASC, `id` ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (count($diceDefs) === 0) {
      throw new RuntimeException('No uncommon dice definitions available for daily deal generation.');
    }

    $affixes = $this->pdo->query("
      SELECT `id`, `slug`, `name`, `description`, `rarity`, `min_value`, `max_value`
      FROM `affix_definitions`
      WHERE `rarity` = 'uncommon'
      ORDER BY `id` ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (count($affixes) === 0) {
      throw new RuntimeException('No affix definitions available for daily deal generation.');
    }

    $diceDef = $diceDefs[random_int(0, count($diceDefs) - 1)] ?? null;
    $affix = $affixes[random_int(0, count($affixes) - 1)] ?? null;
    if (!is_array($diceDef) || !is_array($affix)) {
      throw new RuntimeException('Unable to generate daily deal.');
    }

    $min = (float)($affix['min_value'] ?? 0);
    $max = (float)($affix['max_value'] ?? $min);
    $ratio = random_int(0, 10000) / 10000;
    $value = abs($max - $min) < 0.000001 ? $min : $min + (($max - $min) * $ratio);

    $insert = $this->pdo->prepare("
      INSERT INTO `shop_daily_deals` (
        `user_id`, `shop_date`, `deal_slot`, `dice_definition_id`, `affix_definition_id`, `affix_value`
      ) VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `deal_slot` = VALUES(`deal_slot`),
        `dice_definition_id` = VALUES(`dice_definition_id`),
        `affix_definition_id` = VALUES(`affix_definition_id`),
        `affix_value` = VALUES(`affix_value`)
    ");
    $insert->execute([
      $userId,
      $shopDate,
      $slot,
      (int)$diceDef['id'],
      (int)$affix['id'],
      $value,
    ]);

    return [
      'shop_date' => $shopDate,
      'deal_slot' => $slot,
      'sides' => (int)$diceDef['sides'],
      'rarity' => (string)$diceDef['rarity'],
      'affix_slug' => (string)$affix['slug'],
      'affix_name' => (string)$affix['name'],
      'description' => (string)$affix['description'],
      'affix_rarity' => (string)$affix['rarity'],
      'affix_value' => $value,
      'purchased_at' => null,
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseBasicUnit(int $userId, string $productId): array
  {
    if (!(new UserUnlockService($this->pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $productId)) {
      throw new RuntimeException('Requested unit is not unlocked yet.');
    }

    $stmt = $this->pdo->prepare("
      SELECT `id`, `slug`
      FROM `unit_types`
      WHERE `slug` = ? AND RIGHT(`slug`, 3) = '_t1'
      LIMIT 1
    ");
    $stmt->execute([$productId]);
    $type = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($type)) {
      throw new RuntimeException('Requested unit is not available in the shop.');
    }

    $grantedUnit = $this->userAssetGrantService->grantUnitBySlug($userId, (string)$type['slug'], 1, 1);
    $unitInstanceId = (int)$grantedUnit['id'];

    return [
      'product_id' => (string)$type['slug'],
      'cost' => (new EconomyModifierService($this->pdo))->adjustedShopCostForUser($userId, self::BASIC_UNIT_COST),
      'purchase' => [
        'unit_instance_id' => (string)$unitInstanceId,
        'unit_type_slug' => (string)$type['slug'],
        'tier' => 1,
        'level' => 1,
      ],
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseBasicDice(int $userId, string $productId): array
  {
    if (!preg_match('/^common_d(4|6|8|10)$/', $productId, $matches)) {
      throw new RuntimeException('Requested die is not available in the shop.');
    }
    $sides = (int)$matches[1];

    $stmt = $this->pdo->prepare("
      SELECT `id`, `rarity`, `sides`
      FROM `dice_definitions`
      WHERE `rarity` = 'common' AND `sides` = ?
      ORDER BY `id` ASC
      LIMIT 1
    ");
    $stmt->execute([$sides]);
    $definition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($definition)) {
      throw new RuntimeException('Requested die is not available in the shop.');
    }

    $grantedDice = $this->userAssetGrantService->grantDiceByDefinitionId($userId, (int)$definition['id']);

    return [
      'product_id' => $productId,
      'cost' => (new EconomyModifierService($this->pdo))->adjustedShopCostForUser(
        $userId,
        DiceValuationService::calculateValue($sides, 'common')
      ),
      'purchase' => [
        'dice_instance_id' => (string)$grantedDice['id'],
        'rarity' => (string)$definition['rarity'],
        'sides' => $sides,
      ],
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseDailyDeal(int $userId, string $productId): array
  {
    $shopDate = gmdate('Y-m-d');
    $slot = $this->parseDailyDealSlot($productId);
    $featureUnlocks = (new UserUnlockService($this->pdo))
      ->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE);
    if ($slot > $this->dailyDealSlotCount($featureUnlocks)) {
      throw new RuntimeException('Requested daily deal slot is not unlocked.');
    }

    $deal = $this->resolveDailyDeal($userId, $shopDate, $slot, true, $featureUnlocks);
    if (!is_array($deal)) {
      throw new RuntimeException('Daily deal unavailable.');
    }
    if (!empty($deal['is_purchased'])) {
      throw new RuntimeException('Daily deal already purchased.');
    }

    $stmt = $this->pdo->prepare("
      SELECT sdd.`id`, sdd.`deal_slot`, sdd.`dice_definition_id`, sdd.`affix_definition_id`, sdd.`affix_value`
      FROM `shop_daily_deals` sdd
      WHERE sdd.`user_id` = ? AND sdd.`shop_date` = ? AND sdd.`deal_slot` = ?
      LIMIT 1
      FOR UPDATE
    ");
    $stmt->execute([$userId, $shopDate, $slot]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Daily deal unavailable.');
    }

    $grantedDice = $this->userAssetGrantService->grantDiceByDefinitionId(
      $userId,
      (int)$row['dice_definition_id'],
      [[
        'affix_definition_id' => (int)$row['affix_definition_id'],
        'value' => (float)$row['affix_value'],
      ]]
    );
    $diceInstanceId = (int)$grantedDice['id'];

    $markPurchased = $this->pdo->prepare("
      UPDATE `shop_daily_deals`
      SET `purchased_at` = UTC_TIMESTAMP(), `purchased_dice_instance_id` = ?
      WHERE `id` = ?
    ");
    $markPurchased->execute([$diceInstanceId, (int)$row['id']]);

    $cost = DiceValuationService::calculateValue(
      max(2, (int)($deal['sides'] ?? 6)),
      (string)($deal['rarity'] ?? 'uncommon'),
      [['rarity' => (string)($deal['affix']['rarity'] ?? 'uncommon')]]
    );

    return [
      'product_id' => 'daily_deal_' . $slot,
      'cost' => (new EconomyModifierService($this->pdo))->adjustedShopCostForUser($userId, max(1, $cost)),
      'purchase' => [
        'dice_instance_id' => (string)$diceInstanceId,
        'slot' => $slot,
        'rarity' => (string)$deal['rarity'],
        'sides' => (int)$deal['sides'],
        'affix' => $deal['affix'],
      ],
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseFeatureUnlock(int $userId, string $productId): array
  {
    $baseCost = self::FEATURE_UNLOCK_COSTS[$productId] ?? null;
    if ($baseCost === null) {
      throw new RuntimeException('Requested feature unlock is not available.');
    }

    $unlockService = new UserUnlockService($this->pdo);
    if ($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, $productId)) {
      throw new RuntimeException('Requested feature is already unlocked.');
    }

    if (
      $productId === UserUnlockService::FEATURE_BIGGEREST_SQUAD
      && !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_BIGGER_SQUAD)
    ) {
      throw new RuntimeException('Bigger Squad must be unlocked first.');
    }

    if (
      $productId === UserUnlockService::FEATURE_ENERGY_100
      && !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ENERGY_75)
    ) {
      throw new RuntimeException('Deep Pantry must be unlocked first.');
    }

    if (
      $productId === UserUnlockService::FEATURE_MARKET_MASTERY
      && (
        !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SHOP_DISCOUNT)
        || !$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_SELL_BONUS)
      )
    ) {
      throw new RuntimeException('Coupon Book and Sharp Dealer must be unlocked first.');
    }

    $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, $productId);

    if (
      $productId === UserUnlockService::FEATURE_ENERGY_75
      || $productId === UserUnlockService::FEATURE_ENERGY_100
    ) {
      $effectiveMax = UserUnlockService::resolveEnergyMaxFromFeatureUnlocks(
        $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_FEATURE)
      );
      $this->applyEnergyCapUnlockInOpenTransaction($userId, $effectiveMax);
    }

    return [
      'product_id' => $productId,
      'cost' => (new EconomyModifierService($this->pdo))->adjustedShopCostForUser($userId, $baseCost),
      'purchase' => [
        'unlock_namespace' => UserUnlockService::NAMESPACE_FEATURE,
        'unlock_key' => $productId,
      ],
    ];
  }

  /**
   * @param list<string> $featureUnlocks
   */
  private function dailyDealSlotCount(array $featureUnlocks): int
  {
    return in_array(UserUnlockService::FEATURE_SECOND_DAILY_DEAL, $featureUnlocks, true) ? 2 : 1;
  }

  private function parseDailyDealSlot(string $productId): int
  {
    if (!preg_match('/^daily_deal_(\d+)$/', $productId, $matches)) {
      throw new RuntimeException('Requested daily deal is not available.');
    }

    return max(1, (int)$matches[1]);
  }

  private function applyEnergyCapUnlockInOpenTransaction(int $userId, int $energyMax): void
  {
    $energyRepo = new EnergyRepository($this->pdo);
    $energyRepo->ensureEnergyState($userId);
    $row = $energyRepo->getEnergyStateForUpdate($userId);
    if (!is_array($row)) {
      throw new RuntimeException('Energy state row not found.');
    }

    $current = min(max(0, (int)($row['energy_current'] ?? 0)), $energyMax);
    $stmt = $this->pdo->prepare('
      UPDATE `energy_state`
      SET `energy_max` = ?, `energy_current` = ?
      WHERE `user_id` = ?
    ');
    $stmt->execute([$energyMax, $current, $userId]);
  }
}
