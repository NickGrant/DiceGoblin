<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Domain\Battles\PersistedBattle;
use DiceGoblins\Persistence\BattleRecordCodec;
use PDO;

/** Persistence primitives only; the owning application command supplies transaction scope. */
final class BattlePersistenceRepository
{
  public function __construct(
    private readonly PDO $pdo,
    private readonly BattleRecordCodec $codec = new BattleRecordCodec(),
  ) {}

  public function insertFinalized(int $runId, int $runNodeId, FinalizedBattle $battle): int
  {
    $payload = $this->codec->encode($battle);
    $stmt = $this->pdo->prepare('INSERT INTO `battles`
      (`run_id`, `run_node_id`, `engine_version`, `playback_version`, `input_snapshot`, `participant_manifest`, `result_json`)
      VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $runId, $runNodeId, $payload['engine_version'], $payload['playback_version'], $payload['input_snapshot'],
      $payload['participant_manifest'], $payload['result_json'],
    ]);
    return (int)$this->pdo->lastInsertId();
  }

  public function findById(int $battleId): ?PersistedBattle
  {
    return $this->fetch('WHERE `id` = ?', [$battleId]);
  }

  public function findForRunNode(int $runId, int $runNodeId): ?PersistedBattle
  {
    return $this->fetch('WHERE `run_id` = ? AND `run_node_id` = ?', [$runId, $runNodeId]);
  }

  public function findOwnedById(int $userId, int $battleId): ?PersistedBattle
  {
    return $this->fetch('JOIN `runs` r ON r.`id` = `battles`.`run_id`
      WHERE `battles`.`id` = ? AND r.`user_id` = ?', [$battleId, $userId]);
  }

  /** @param list<int> $params */
  private function fetch(string $where, array $params): ?PersistedBattle
  {
    $stmt = $this->pdo->prepare('SELECT `battles`.`id`, `battles`.`run_id`, `battles`.`run_node_id`,
      `battles`.`engine_version`, `battles`.`playback_version`, `battles`.`input_snapshot`,
      `battles`.`participant_manifest`, `battles`.`result_json`, `battles`.`created_at`
      FROM `battles` ' . $where . ' LIMIT 1');
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $this->codec->hydrate($row) : null;
  }
}
