<?php
declare(strict_types=1);

namespace DiceGoblins\Repositories;

use JsonException;
use PDO;
use RuntimeException;

final class IdempotencyRequestRepository
{
  public function __construct(private readonly PDO $pdo) {}

  /** @return array{operation_type:string,request_hash:string,result:array<string,mixed>}|null */
  public function getForUser(int $userId, string $key): ?array
  {
    $stmt = $this->pdo->prepare('
      SELECT `operation_type`, `request_hash`, `result_json`
      FROM `idempotency_requests`
      WHERE `user_id` = ? AND `idempotency_key` = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    try {
      $result = json_decode((string)$row['result_json'], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      throw new RuntimeException('Persisted idempotency result is invalid.', 0, $e);
    }
    if (!is_array($result)) throw new RuntimeException('Persisted idempotency result is invalid.');
    return [
      'operation_type' => (string)$row['operation_type'],
      'request_hash' => (string)$row['request_hash'],
      'result' => $result,
    ];
  }

  /** @param array<string,mixed> $result */
  public function insertFinalized(int $userId, string $key, string $operationType, string $requestHash, array $result): void
  {
    try {
      $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
      throw new RuntimeException('Idempotency result could not be encoded.', 0, $e);
    }
    $this->pdo->prepare('
      INSERT INTO `idempotency_requests` (`user_id`, `idempotency_key`, `operation_type`, `request_hash`, `result_json`)
      VALUES (?, ?, ?, ?, ?)
    ')->execute([$userId, $key, $operationType, $requestHash, $encoded]);
  }
}
