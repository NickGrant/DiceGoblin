<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\SquadRepository;
use PDO;
use Throwable;

final class UpdateSquadCommand
{
  public function __construct(private readonly PDO $pdo, private readonly PlayerStateRepository $playerState,
    private readonly SquadRepository $squads, private readonly SquadCommandSupport $support,
    private readonly ActiveRunConfigurationPolicy $activeRunPolicy) {}

  /** @param array<string,mixed> $request @return array<string,mixed> */
  public function execute(int $userId, int $squadId, array $request): array
  {
    $configuration = SquadConfiguration::fromRequest($request);
    try {
      $this->pdo->beginTransaction();
      $context = $this->support->lockPlayer($userId);
      $this->support->requireOwned($userId, $squadId);
      $current = $this->support->squadView($userId, $squadId, $context['active_squad_id'], true);
      $this->activeRunPolicy->assertSquadFormationAllowed(
        $userId,
        $squadId,
        $current['formation'] !== $configuration->canonicalRequest()['formation'],
      );
      $this->support->validateUnits($userId, $configuration);
      $this->squads->updateName($userId, $squadId, $configuration->name);
      $this->squads->replaceFormation($squadId, $configuration->formation);
      $revision = $this->playerState->incrementRevision($userId);
      $result = ['squad' => $this->support->squadView($userId, $squadId, $context['active_squad_id']),
        'active_squad_id' => $context['active_squad_id'] !== null ? (string)$context['active_squad_id'] : null,
        'player_revision' => $revision];
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
