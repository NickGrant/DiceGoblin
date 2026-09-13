<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Application\Queries\CurrentRunIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Infrastructure\Clock;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use PDO;
use Throwable;

final class AbandonRunCommand
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $playerState,
    private readonly RunPersistenceRepository $runs,
    private readonly ContentRegistry $content,
    private readonly Clock $clock,
  ) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, int $runId): array
  {
    try {
      $this->pdo->beginTransaction();
      $state = $this->playerState->getPlayerStateForUpdate($userId);
      if ($state === null) throw new CurrentRunIntegrityException('Required player state is missing.');
      $run = $this->runs->findOwnedRunForUpdate($userId, $runId);
      if ($run === null) throw new RunNotFoundException();
      $this->validateRoot($run, $userId);
      $status = (string)$run['status'];
      if ($status === 'active') {
        if ($run['squad_id'] === null || (int)$run['squad_id'] <= 0 || $run['ended_at'] !== null) {
          throw new CurrentRunIntegrityException('Active run lifecycle is invalid.');
        }
        $endedAt = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $this->runs->abandon($userId, $runId, $endedAt);
        $revision = $this->playerState->incrementRevision($userId);
      } elseif ($status === 'abandoned') {
        if ($run['ended_at'] === null) throw new CurrentRunIntegrityException('Abandoned run lifecycle is invalid.');
        $endedAt = new DateTimeImmutable((string)$run['ended_at'], new DateTimeZone('UTC'));
        $revision = (int)$state['player_revision'];
      } else {
        throw new RunLifecycleConflictException();
      }
      $result = ['run' => ['id' => (string)$runId, 'region_id' => (string)$run['region_id'],
        'squad_id' => $run['squad_id'] !== null ? (string)$run['squad_id'] : null, 'status' => 'abandoned',
        'ended_at' => $endedAt->format('Y-m-d\TH:i:s\Z')],
        'active_run' => null, 'player_revision' => $revision];
      $this->pdo->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    }
  }

  /** @param array<string,mixed> $run */
  private function validateRoot(array $run, int $userId): void
  {
    if ((int)$run['id'] <= 0 || (int)$run['user_id'] !== $userId
      || ($run['squad_id'] !== null && ($run['squad_user_id'] === null || (int)$run['squad_user_id'] !== $userId))) {
      throw new CurrentRunIntegrityException('Run root is invalid.');
    }
    try { $this->content->region((string)$run['region_id']); }
    catch (ContentValidationException) { throw new CurrentRunIntegrityException('Run region is invalid.'); }
  }
}
