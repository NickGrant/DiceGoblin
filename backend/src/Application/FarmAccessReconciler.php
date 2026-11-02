<?php
declare(strict_types=1);

namespace DiceGoblins\Application;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use RuntimeException;
use Throwable;

/** One-time reconciliation for players who owned units before Farm became an unlock. */
final class FarmAccessReconciler
{
  private const UNLOCK_ID = 'unlock.region.the_farm';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly WarbandUnitRepository $units,
    private readonly UserUnlockRepository $unlocks,
    private readonly ContentRegistry $content,
  ) {}

  public function reconcile(int $userId): bool
  {
    if ($userId < 1) throw new RuntimeException('Player identity is invalid.');
    $this->content->unlock(self::UNLOCK_ID);
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new RuntimeException('Player state is missing.');
      if (!$this->units->hasAnyForUser($userId) || $this->unlocks->owns($userId, self::UNLOCK_ID)) {
        $this->pdo->commit();
        return false;
      }
      if ($state['player_revision'] < 1 || $state['player_revision'] >= ClientSafeInteger::MAXIMUM) {
        throw new RuntimeException('Player revision cannot advance.');
      }
      if (!$this->unlocks->insertIfAbsent($userId, self::UNLOCK_ID)) {
        throw new RuntimeException('Farm access reconciliation could not grant the unlock.');
      }
      $revision = $this->players->incrementRevision($userId);
      if ($revision !== $state['player_revision'] + 1) throw new RuntimeException('Player revision transition is incoherent.');
      $this->pdo->commit();
      return true;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
