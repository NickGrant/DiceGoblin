<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use PDO;
use RuntimeException;

final class RunModifierRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return list<array<string,mixed>> */
  public function listActive(int $runId, bool $forUpdate = false): array
  {
    $stmt = $this->pdo->prepare('SELECT `id`, `run_id`, `source_node_id`, `modifier_id`, `remaining_combats`
      FROM `run_modifiers` WHERE `run_id` = ? ORDER BY `id` ASC' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create(int $runId, int $sourceNodeId, string $modifierId): int
  {
    $this->requireTransaction();
    $stmt = $this->pdo->prepare('INSERT INTO `run_modifiers`
      (`run_id`, `source_node_id`, `modifier_id`, `remaining_combats`) VALUES (?, ?, ?, 1)');
    $stmt->execute([$runId, $sourceNodeId, $modifierId]);
    return (int)$this->pdo->lastInsertId();
  }

  /** @param list<int> $ids */
  public function consume(int $runId, array $ids): void
  {
    $this->requireTransaction();
    $stmt = $this->pdo->prepare('DELETE FROM `run_modifiers`
      WHERE `run_id` = ? AND `id` = ? AND `remaining_combats` = 1');
    foreach ($ids as $id) {
      $stmt->execute([$runId, $id]);
      if ($stmt->rowCount() !== 1) throw new RuntimeException('Run modifier changed before consumption.');
    }
  }

  private function requireTransaction(): void
  {
    if (!$this->pdo->inTransaction()) throw new RuntimeException('Run modifier mutation requires a caller-owned transaction.');
  }
}
