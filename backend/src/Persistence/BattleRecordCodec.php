<?php
declare(strict_types=1);

namespace DiceGoblins\Persistence;

use DateTimeImmutable;
use DiceGoblins\Domain\Battles\FinalizedBattle;
use DiceGoblins\Domain\Battles\PersistedBattle;
use JsonException;
use RuntimeException;

/** JSON storage codec that validates finalized battle payloads on both sides of persistence. */
final class BattleRecordCodec
{
  /** @return array{engine_version:int,playback_version:int,input_snapshot:string,participant_manifest:string,result_json:string} */
  public function encode(FinalizedBattle $battle): array
  {
    return [
      'engine_version' => $battle->engineVersion,
      'playback_version' => $battle->playbackVersion,
      'input_snapshot' => $this->encodeJson($battle->inputSnapshot),
      'participant_manifest' => $this->encodeJson($battle->manifestArray()),
      'result_json' => $this->encodeJson($battle->result),
    ];
  }

  /** @param array<string,mixed> $row */
  public function hydrate(array $row): PersistedBattle
  {
    foreach (['id', 'run_id', 'run_node_id', 'engine_version', 'playback_version', 'input_snapshot',
      'participant_manifest', 'result_json', 'created_at'] as $field) {
      if (!array_key_exists($field, $row)) throw new RuntimeException("Stored battle row is missing {$field}.");
    }
    try {
      $createdAt = new DateTimeImmutable((string)$row['created_at']);
      $battle = new FinalizedBattle(
        $this->positiveInteger($row['engine_version'], 'engine_version'),
        $this->positiveInteger($row['playback_version'], 'playback_version'),
        $this->decodeObject((string)$row['input_snapshot'], 'input_snapshot'),
        $this->decodeList((string)$row['participant_manifest'], 'participant_manifest'),
        $this->decodeObject((string)$row['result_json'], 'result_json'),
      );
      return new PersistedBattle(
        $this->positiveInteger($row['id'], 'id'),
        $this->positiveInteger($row['run_id'], 'run_id'),
        $this->positiveInteger($row['run_node_id'], 'run_node_id'),
        $battle,
        $createdAt,
      );
    } catch (\Throwable $e) {
      if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), 'Stored battle')) throw $e;
      throw new RuntimeException('Stored battle row failed persistence validation.', 0, $e);
    }
  }

  private function encodeJson(array $value): string
  {
    try {
      return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    } catch (JsonException $e) {
      throw new RuntimeException('Finalized battle payload could not be encoded.', 0, $e);
    }
  }

  /** @return array<string,mixed> */
  private function decodeObject(string $json, string $field): array
  {
    $value = $this->decodeJson($json, $field);
    if (array_is_list($value)) throw new RuntimeException("Stored battle {$field} must be an object.");
    return $value;
  }

  /** @return list<array<string,mixed>> */
  private function decodeList(string $json, string $field): array
  {
    $value = $this->decodeJson($json, $field);
    if (!array_is_list($value)) throw new RuntimeException("Stored battle {$field} must be a list.");
    return $value;
  }

  /** @return array<mixed> */
  private function decodeJson(string $json, string $field): array
  {
    try {
      $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      throw new RuntimeException("Stored battle {$field} is invalid JSON.", 0, $e);
    }
    if (!is_array($value)) throw new RuntimeException("Stored battle {$field} must decode to JSON data.");
    return $value;
  }

  private function positiveInteger(mixed $value, string $field): int
  {
    if ((!is_int($value) && (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1)) || (int)$value < 1) {
      throw new RuntimeException("Stored battle {$field} must be a positive integer.");
    }
    return (int)$value;
  }
}
