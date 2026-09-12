<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use Closure;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use PDO;
use Throwable;

final class RenameUnitCommand
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly WarbandUnitRepository $units,
    private readonly UnitConfigurationSupport $support,
    private readonly ?Closure $beforeCommit = null,
  ) {}

  /** @param array<string,mixed> $request @return array{unit:array<string,mixed>,player_revision:int} */
  public function execute(int $userId, int $unitId, array $request): array
  {
    $rename = UnitRenameRequest::fromRequest($request);
    try {
      $this->pdo->beginTransaction();
      $context = $this->support->lockPlayer($userId);
      $unit = $this->support->lockValidUnit($userId, $unitId);
      if ($unit['display_name'] === $rename->name) {
        $this->pdo->commit();
        return ['unit' => $unit, 'player_revision' => $context['player_revision']];
      }

      $this->units->updateDisplayName($userId, $unitId, $rename->name);
      $revision = $this->playerState->incrementRevision($userId);
      $result = ['unit' => $this->support->detail($userId, $unitId), 'player_revision' => $revision];
      if ($this->beforeCommit !== null) ($this->beforeCommit)();
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }
}
