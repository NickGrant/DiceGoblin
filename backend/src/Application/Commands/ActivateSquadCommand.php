<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Repositories\PlayerStateRepository;
use PDO;
use Throwable;

final class ActivateSquadCommand
{
  public function __construct(private readonly PDO $pdo, private readonly PlayerStateRepository $playerState,
    private readonly SquadCommandSupport $support) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, int $squadId): array
  {
    try {
      $this->pdo->beginTransaction();
      $context = $this->support->lockPlayer($userId);
      $this->support->requireOwned($userId, $squadId);
      $squad = $this->support->squadView($userId, $squadId, $squadId);
      if ($context['active_squad_id'] === $squadId) {
        $revision = $context['player_revision'];
      } else {
        $revision = $this->playerState->setActiveSquadAndIncrementRevision($userId, $squadId);
      }
      $result = ['squad' => $squad, 'active_squad_id' => (string)$squadId, 'player_revision' => $revision];
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
