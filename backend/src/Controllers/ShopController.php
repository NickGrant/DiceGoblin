<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Services\DiceValuationService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\Services\UnitLoadoutService;
use DiceGoblins\Services\UnitNameGenerator;
use DiceGoblins\Services\UserUnlockService;
use PDO;
use RuntimeException;
use Throwable;

final class ShopController
{
  use RequiresCsrf;

  private const BASIC_UNIT_COST = 32;
  private const FEATURE_UNLOCK_COSTS = [
    UserUnlockService::FEATURE_ACADEMY => 250,
  ];

  public function catalog(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null) {
      return;
    }

    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $data = $this->buildCatalog($svc['pdo'], $svc['playerStateRepo'], $userId, false);
      Response::json(['ok' => true, 'data' => $data]);
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  public function purchase(): void
  {
    $svc = $this->services();
    $userId = $this->requireUserId($svc['sessionService']);
    if ($userId === null || !$this->requireCsrf($svc['csrfService'])) {
      return;
    }

    $body = $this->readJsonBody();
    if ($body === null) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'Invalid JSON body.']], 400);
      return;
    }

    $itemType = trim((string)($body['item_type'] ?? ''));
    $productId = trim((string)($body['product_id'] ?? ''));
    if (!in_array($itemType, ['basic_unit', 'basic_dice', 'daily_deal', 'feature_unlock'], true)) {
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => 'item_type is required.']], 400);
      return;
    }

    /** @var PDO $pdo */
    $pdo = $svc['pdo'];
    try {
      $svc['bootstrapper']->ensureBaseline($userId);
      $pdo->beginTransaction();
      $svc['playerStateRepo']->ensurePlayerState($userId);
      $state = $svc['playerStateRepo']->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        throw new RuntimeException('Player state unavailable.');
      }

      $purchase = match ($itemType) {
        'basic_unit' => $this->purchaseBasicUnit($pdo, $userId, $productId),
        'basic_dice' => $this->purchaseBasicDice($pdo, $userId, $productId),
        'daily_deal' => $this->purchaseDailyDeal($pdo, $userId),
        'feature_unlock' => $this->purchaseFeatureUnlock($pdo, $userId, $productId),
      };

      $cost = (int)$purchase['cost'];
      $currentSoft = max(0, (int)$state['currency_soft']);
      if ($currentSoft < $cost) {
        $pdo->rollBack();
        Response::json(['ok' => false, 'error' => ['code' => 'insufficient_currency', 'message' => 'Not enough soft currency.']], 409);
        return;
      }

      $nextSoft = $currentSoft - $cost;
      $svc['playerStateRepo']->setCurrency($userId, $nextSoft, max(0, (int)$state['currency_hard']));

      $pdo->commit();
      Response::json([
        'ok' => true,
        'data' => [
          'item_type' => $itemType,
          'product_id' => $purchase['product_id'],
          'cost' => $cost,
          'currency_soft' => $nextSoft,
          'purchase' => $purchase['purchase'],
        ],
      ]);
    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'validation_error', 'message' => $e->getMessage()]], 400);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Response::json(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Unexpected error.']], 500);
    }
  }

  /**
   * @return array{
   *   server_date:string,
   *   currency_soft:int,
   *   basic_dice:array<int,array{product_id:string,label:string,rarity:string,sides:int,cost:int}>,
   *   basic_units:array<int,array{product_id:string,unit_type_slug:string,name:string,role:string,cost:int}>,
   *   feature_unlocks:array<int,array{product_id:string,name:string,description:string,cost:int,is_unlocked:bool}>,
   *   daily_deal:?array<string,mixed>
   * }
   */
  private function buildCatalog(PDO $pdo, PlayerStateRepository $playerStateRepo, int $userId, bool $lockDeal): array
  {
    $playerStateRepo->ensurePlayerState($userId);
    $currency = $playerStateRepo->getCurrency($userId);
    $shopDate = gmdate('Y-m-d');

    return [
      'server_date' => $shopDate,
      'currency_soft' => max(0, (int)($currency['soft'] ?? 0)),
      'basic_dice' => $this->listBasicDiceCatalog($pdo),
      'basic_units' => $this->listBasicUnitCatalog($pdo, $userId),
      'feature_unlocks' => $this->listFeatureUnlockCatalog($pdo, $userId),
      'daily_deal' => $this->resolveDailyDeal($pdo, $userId, $shopDate, $lockDeal),
    ];
  }

  /**
   * @return array<int,array{product_id:string,label:string,rarity:string,sides:int,cost:int}>
   */
  private function listBasicDiceCatalog(PDO $pdo): array
  {
    $stmt = $pdo->query("
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
        'cost' => DiceValuationService::calculateValue($sides, 'common'),
      ];
    }

    return $out;
  }

  /**
   * @return array<int,array{product_id:string,unit_type_slug:string,name:string,role:string,cost:int}>
   */
  private function listBasicUnitCatalog(PDO $pdo, int $userId): array
  {
    $unlockService = new UserUnlockService($pdo);
    $unlockedUnitSlugs = $unlockService->listUnlockedKeys($userId, UserUnlockService::NAMESPACE_UNIT_TYPE);
    if (count($unlockedUnitSlugs) === 0) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($unlockedUnitSlugs), '?'));
    $stmt = $pdo->prepare("
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
      'cost' => self::BASIC_UNIT_COST,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * @return array<int,array{product_id:string,name:string,description:string,cost:int,is_unlocked:bool}>
   */
  private function listFeatureUnlockCatalog(PDO $pdo, int $userId): array
  {
    $unlockService = new UserUnlockService($pdo);

    return [[
      'product_id' => UserUnlockService::FEATURE_ACADEMY,
      'name' => 'Academy',
      'description' => 'Unlock promotions and unit-type research for your warband.',
      'cost' => self::FEATURE_UNLOCK_COSTS[UserUnlockService::FEATURE_ACADEMY],
      'is_unlocked' => $unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_ACADEMY),
    ]];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function resolveDailyDeal(PDO $pdo, int $userId, string $shopDate, bool $lockRow): ?array
  {
    $sql = "
      SELECT
        sdd.`id`,
        sdd.`shop_date`,
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
      WHERE sdd.`user_id` = ? AND sdd.`shop_date` = ?
      LIMIT 1
    ";
    if ($lockRow) {
      $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $shopDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      $row = $this->createDailyDeal($pdo, $userId, $shopDate);
    }

    $sides = max(2, (int)($row['sides'] ?? 6));
    $dealCost = DiceValuationService::calculateValue(
      $sides,
      (string)($row['rarity'] ?? 'uncommon'),
      [['rarity' => (string)($row['affix_rarity'] ?? 'uncommon')]]
    );

    return [
      'product_id' => 'daily_deal',
      'shop_date' => (string)($row['shop_date'] ?? $shopDate),
      'sides' => $sides,
      'rarity' => (string)($row['rarity'] ?? 'uncommon'),
      'cost' => $dealCost,
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
  private function createDailyDeal(PDO $pdo, int $userId, string $shopDate): array
  {
    $diceDefs = $pdo->query("
      SELECT `id`, `sides`, `rarity`
      FROM `dice_definitions`
      WHERE `rarity` = 'uncommon' AND `sides` IN (4, 6, 8, 10)
      ORDER BY `sides` ASC, `id` ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (count($diceDefs) === 0) {
      throw new RuntimeException('No uncommon dice definitions available for daily deal generation.');
    }

    $affixes = $pdo->query("
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

    $insert = $pdo->prepare("
      INSERT INTO `shop_daily_deals` (
        `user_id`, `shop_date`, `dice_definition_id`, `affix_definition_id`, `affix_value`
      ) VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        `dice_definition_id` = VALUES(`dice_definition_id`),
        `affix_definition_id` = VALUES(`affix_definition_id`),
        `affix_value` = VALUES(`affix_value`)
    ");
    $insert->execute([
      $userId,
      $shopDate,
      (int)$diceDef['id'],
      (int)$affix['id'],
      $value,
    ]);

    return [
      'shop_date' => $shopDate,
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
  private function purchaseBasicUnit(PDO $pdo, int $userId, string $productId): array
  {
    if (!(new UserUnlockService($pdo))->isUnlocked($userId, UserUnlockService::NAMESPACE_UNIT_TYPE, $productId)) {
      throw new RuntimeException('Requested unit is not unlocked yet.');
    }

    $stmt = $pdo->prepare("
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

    $unitTypeId = (int)$type['id'];
    $insert = $pdo->prepare("
      INSERT INTO `unit_instances` (`user_id`, `unit_type_id`, `display_name`, `tier`, `level`, `xp`, `locked`)
      VALUES (?, ?, ?, 1, 1, 0, 0)
    ");
    $insert->execute([$userId, $unitTypeId, (new UnitNameGenerator())->generate()]);
    $unitInstanceId = (int)$pdo->lastInsertId();
    (new UnitLoadoutService($pdo))->initializeUnit($unitInstanceId, $unitTypeId);

    return [
      'product_id' => (string)$type['slug'],
      'cost' => self::BASIC_UNIT_COST,
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
  private function purchaseBasicDice(PDO $pdo, int $userId, string $productId): array
  {
    if (!preg_match('/^common_d(4|6|8|10)$/', $productId, $matches)) {
      throw new RuntimeException('Requested die is not available in the shop.');
    }
    $sides = (int)$matches[1];

    $stmt = $pdo->prepare("
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

    $insert = $pdo->prepare("
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ");
    $insert->execute([$userId, (int)$definition['id']]);

    return [
      'product_id' => $productId,
      'cost' => DiceValuationService::calculateValue($sides, 'common'),
      'purchase' => [
        'dice_instance_id' => (string)$pdo->lastInsertId(),
        'rarity' => (string)$definition['rarity'],
        'sides' => $sides,
      ],
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseDailyDeal(PDO $pdo, int $userId): array
  {
    $shopDate = gmdate('Y-m-d');
    $deal = $this->resolveDailyDeal($pdo, $userId, $shopDate, true);
    if (!is_array($deal)) {
      throw new RuntimeException('Daily deal unavailable.');
    }
    if (!empty($deal['is_purchased'])) {
      throw new RuntimeException('Daily deal already purchased.');
    }

    $stmt = $pdo->prepare("
      SELECT sdd.`id`, sdd.`dice_definition_id`, sdd.`affix_definition_id`, sdd.`affix_value`
      FROM `shop_daily_deals` sdd
      WHERE sdd.`user_id` = ? AND sdd.`shop_date` = ?
      LIMIT 1
      FOR UPDATE
    ");
    $stmt->execute([$userId, $shopDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      throw new RuntimeException('Daily deal unavailable.');
    }

    $insertDice = $pdo->prepare("
      INSERT INTO `dice_instances` (`user_id`, `dice_definition_id`, `display_name`)
      VALUES (?, ?, NULL)
    ");
    $insertDice->execute([$userId, (int)$row['dice_definition_id']]);
    $diceInstanceId = (int)$pdo->lastInsertId();

    $insertAffix = $pdo->prepare("
      INSERT INTO `dice_instance_affixes` (`dice_instance_id`, `affix_definition_id`, `value`)
      VALUES (?, ?, ?)
    ");
    $insertAffix->execute([
      $diceInstanceId,
      (int)$row['affix_definition_id'],
      (float)$row['affix_value'],
    ]);

    $markPurchased = $pdo->prepare("
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
      'product_id' => 'daily_deal',
      'cost' => max(0, $cost),
      'purchase' => [
        'dice_instance_id' => (string)$diceInstanceId,
        'rarity' => (string)$deal['rarity'],
        'sides' => (int)$deal['sides'],
        'affix' => $deal['affix'],
      ],
    ];
  }

  /**
   * @return array{product_id:string,cost:int,purchase:array<string,mixed>}
   */
  private function purchaseFeatureUnlock(PDO $pdo, int $userId, string $productId): array
  {
    $cost = self::FEATURE_UNLOCK_COSTS[$productId] ?? null;
    if ($cost === null) {
      throw new RuntimeException('Requested feature unlock is not available.');
    }

    $unlockService = new UserUnlockService($pdo);
    if ($unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, $productId)) {
      throw new RuntimeException('Requested feature is already unlocked.');
    }

    $unlockService->grant($userId, UserUnlockService::NAMESPACE_FEATURE, $productId);

    return [
      'product_id' => $productId,
      'cost' => $cost,
      'purchase' => [
        'unlock_namespace' => UserUnlockService::NAMESPACE_FEATURE,
        'unlock_key' => $productId,
      ],
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function readJsonBody(): ?array
  {
    return JsonRequestBody::decode();
  }

  private function requireUserId(SessionService $sessionService): ?int
  {
    try {
      return $sessionService->requireUserId();
    } catch (Throwable $e) {
      Response::json(['ok' => false, 'error' => ['code' => 'unauthorized', 'message' => 'No active session.']], 401);
      return null;
    }
  }

  private function services(): array
  {
    $pdo = Db::pdo();
    $core = ControllerServiceFactory::buildCore($pdo);

    return [
      'pdo' => $pdo,
      'csrfService' => $core['csrfService'],
      'sessionService' => $core['sessionService'],
      'bootstrapper' => $core['bootstrapper'],
      'playerStateRepo' => new PlayerStateRepository($pdo),
    ];
  }
}
