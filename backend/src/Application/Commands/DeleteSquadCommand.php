<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use PDO;
use Throwable;

final class DeleteSquadCommand
{
  public function __construct(private readonly PDO $pdo, private readonly PlayerStateRepository $playerState,
    private readonly SquadRepository $squads, private readonly SquadCommandSupport $support,
    private readonly ActiveRunConfigurationPolicy $activeRunPolicy) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, int $squadId): array
  {
    try {
      $this->pdo->beginTransaction();
      $context = $this->support->lockPlayer($userId);
      $this->support->requireOwned($userId, $squadId);
      $this->activeRunPolicy->assertSquadDeletionAllowed($userId, $squadId);
      $isActive = $context['active_squad_id'] === $squadId;
      if ($isActive && count($context['squad_ids']) > 1) {
        throw new SquadActiveDeletionException('Activate another squad before deleting the active squad.');
      }
      $this->squads->deleteOwned($userId, $squadId);
      if ($isActive) {
        $revision = $this->playerState->setActiveSquadAndIncrementRevision($userId, null);
        $active = null;
      } else {
        $revision = $this->playerState->incrementRevision($userId);
        $active = $context['active_squad_id'];
      }
      $result = ['deleted_squad_id' => (string)$squadId,
        'active_squad_id' => $active !== null ? (string)$active : null, 'player_revision' => $revision];
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
