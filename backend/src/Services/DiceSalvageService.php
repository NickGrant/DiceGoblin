<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

use DiceGoblins\Repositories\DiceRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use RuntimeException;
use Throwable;

final class DiceSalvageService
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly DiceRepository $diceRepository,
    private readonly PlayerStateRepository $playerStateRepository,
  ) {}

  /**
   * @return array{dice_id:string,raw_chaos_awarded:int,currency_raw_chaos:int}
   */
  public function salvageDice(int $userId, int $diceInstanceId): array
  {
    try {
      $this->pdo->beginTransaction();

      $dice = $this->diceRepository->getDiceWithAffixesForUserByIdForUpdate($userId, $diceInstanceId);
      if (!is_array($dice)) {
        $this->pdo->rollBack();
        throw new RuntimeException('dice_not_found');
      }

      if ($this->diceRepository->isDiceEquippedForUpdate($diceInstanceId)) {
        $this->pdo->rollBack();
        throw new RuntimeException('equipped_dice_cannot_be_salvaged');
      }

      $unlockService = new UserUnlockService($this->pdo);
      if (!$unlockService->isUnlocked($userId, UserUnlockService::NAMESPACE_FEATURE, UserUnlockService::FEATURE_WRONG_MACHINE)) {
        $this->pdo->rollBack();
        throw new RuntimeException('wrong_machine_locked');
      }

      $this->playerStateRepository->ensurePlayerState($userId);
      $state = $this->playerStateRepository->getPlayerStateForUpdate($userId);
      if (!is_array($state)) {
        $this->pdo->rollBack();
        throw new RuntimeException('player_state_unavailable');
      }

      $rawChaosAwarded = DiceValuationService::calculateRawChaosSalvageValue(
        max(2, (int)($dice['sides'] ?? 6)),
        (string)($dice['rarity'] ?? 'common'),
        is_array($dice['affixes'] ?? null) ? $dice['affixes'] : [],
      );
      $nextRawChaos = max(0, (int)($state['currency_raw_chaos'] ?? 0)) + $rawChaosAwarded;

      $this->playerStateRepository->setRawChaos($userId, $nextRawChaos);
      $this->diceRepository->deleteOwnedDiceInstance($userId, $diceInstanceId);

      $this->pdo->commit();

      return [
        'dice_id' => (string)$diceInstanceId,
        'raw_chaos_awarded' => $rawChaosAwarded,
        'currency_raw_chaos' => $nextRawChaos,
      ];
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }
}
