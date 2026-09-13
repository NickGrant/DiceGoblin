<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\RunPersistenceRepository;

final class ActiveRunSummaryQuery
{
  public function __construct(
    private readonly RunPersistenceRepository $runs,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{id:string,region_id:string,squad_id:string,status:string}|null */
  public function execute(int $userId): ?array
  {
    $run = $this->runs->findActiveRunForUser($userId);
    if ($run === null) return null;
    if ((int)$run['id'] <= 0 || (int)$run['user_id'] !== $userId || (string)$run['status'] !== 'active'
      || $run['squad_id'] === null || (int)$run['squad_id'] <= 0
      || $run['squad_user_id'] === null || (int)$run['squad_user_id'] !== $userId || $run['ended_at'] !== null) {
      throw new CurrentRunIntegrityException('Active run summary is invalid.');
    }
    try {
      $this->content->region((string)$run['region_id']);
    } catch (ContentValidationException) {
      throw new CurrentRunIntegrityException('Active run region is invalid.');
    }
    return ['id' => (string)$run['id'], 'region_id' => (string)$run['region_id'],
      'squad_id' => (string)$run['squad_id'], 'status' => 'active'];
  }
}
