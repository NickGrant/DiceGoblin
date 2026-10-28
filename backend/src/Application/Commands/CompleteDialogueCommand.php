<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Commands;

use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Support\ClientSafeInteger;
use PDO;
use Throwable;

final class CompleteDialogueCommand
{
  private const OPERATION = 'complete_dialogue';

  public function __construct(
    private readonly PDO $pdo,
    private readonly PlayerStateRepository $players,
    private readonly UserCodexRepository $codex,
    private readonly UserUnlockRepository $unlocks,
    private readonly IdempotencyRequestRepository $receipts,
    private readonly RewardApplicationService $rewards,
    private readonly ContentRegistry $content,
    private readonly ?ObjectiveProgressService $objectives = null,
  ) {}

  /** @return array{dialogue_id:string,choice_id:string,response:string,knowledge:?array{codex_id:string,outcome:string},player_revision:int} */
  public function execute(int $userId, string $dialogueId, string $choiceId, ?string $providedKey): array
  {
    if (preg_match('/^dialogue\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $dialogueId) !== 1
      || preg_match('/^[a-z][a-z0-9_]*$/D', $choiceId) !== 1) {
      throw new DialogueCompletionException('dialogue_choice_unavailable', 404);
    }
    $key = IdempotencyKey::validate($providedKey);
    $hash = hash('sha256', json_encode(['dialogue_id' => $dialogueId, 'choice_id' => $choiceId], JSON_THROW_ON_ERROR));
    try {
      $this->pdo->beginTransaction();
      $state = $this->players->getPlayerStateForUpdate($userId);
      if ($state === null) throw new DialogueCompletionIntegrityException('Player state is unavailable.');
      $prior = $this->receipts->getForUser($userId, $key);
      if ($prior !== null) {
        $this->requireReceipt($prior, $hash, $dialogueId, $choiceId, $userId);
        $this->pdo->commit();
        return $prior['result'];
      }
      if ($state['teeth'] < 0 || $state['raw_chaos'] < 0 || $state['player_revision'] < 0
        || $state['player_revision'] > ClientSafeInteger::MAXIMUM) {
        throw new DialogueCompletionIntegrityException('Player state is incoherent.');
      }
      try { $dialogue = $this->content->dialogue($dialogueId); }
      catch (ContentValidationException) { throw new DialogueCompletionException('dialogue_choice_unavailable', 404); }
      $choice = null;
      foreach ($dialogue['choices'] as $candidate) {
        if ($candidate['id'] === $choiceId) { $choice = $candidate; break; }
      }
      if ($choice === null) throw new DialogueCompletionException('dialogue_choice_unavailable', 404);
      $owned = $this->codex->listIdsForUser($userId, true);
      foreach ($owned as $id) $this->content->codexEntry($id);
      foreach ($choice['required_codex_ids'] as $required) {
        if (!in_array($required, $owned, true)) throw new DialogueCompletionException('dialogue_choice_unavailable', 404);
      }
      $knowledge = null;
      $revision = $state['player_revision'];
      $revisionChanged = false;
      if (isset($choice['event_id'])) {
        $event = $this->content->event($choice['event_id']);
        $reward = $this->content->rewardDefinition($event['reward_definition_id']);
        if (count($reward['entries']) !== 1 || $reward['entries'][0]['reward_type'] !== 'codex'
          || $reward['entries'][0]['probability_basis_points'] !== 10000) {
          throw new DialogueCompletionIntegrityException('Dialogue reward is incoherent.');
        }
        $unlocks = $this->unlocks->listIdsForUser($userId, true);
        $sourceId = hash('sha256', $key . ':' . $dialogueId . ':' . $choiceId);
        $result = $this->rewards->apply($userId, $choice['event_id'], 'dialogue_completion', $sourceId,
          new RewardContext($state['teeth'], $state['raw_chaos'], [], $unlocks, $owned));
        $entry = $result->entries()[0] ?? null;
        $codexId = $reward['entries'][0]['config']['codex_id'];
        if (count($result->entries()) !== 1 || $entry['reward_type'] !== 'codex'
          || !in_array($entry['outcome'], ['granted', 'already_owned'], true)
          || $entry['grant']['codex_id'] !== $codexId || !$this->codex->owns($userId, $codexId)) {
          throw new DialogueCompletionIntegrityException('Dialogue knowledge grant is incoherent.');
        }
        $knowledge = ['codex_id' => $codexId, 'outcome' => $entry['outcome']];
        if ($entry['outcome'] === 'granted') {
          if ($revision === ClientSafeInteger::MAXIMUM) throw new DialogueCompletionIntegrityException('Player revision overflow.');
          $revision = $this->players->incrementRevision($userId);
          $revisionChanged = true;
        }
      }
      $objectiveChanged = $this->objectives?->apply($userId, 'complete_dialogue', $dialogueId) ?? false;
      if ($objectiveChanged && !$revisionChanged) {
        if ($revision === ClientSafeInteger::MAXIMUM) throw new DialogueCompletionIntegrityException('Player revision overflow.');
        $revision = $this->players->incrementRevision($userId);
      }
      $receipt = ['dialogue_id' => $dialogueId, 'choice_id' => $choiceId,
        'response' => $choice['response'], 'knowledge' => $knowledge, 'player_revision' => $revision];
      $this->receipts->insertFinalized($userId, $key, self::OPERATION, $hash, $receipt);
      $stored = $this->receipts->getForUser($userId, $key);
      if ($stored === null) throw new DialogueCompletionIntegrityException('Dialogue receipt is unavailable.');
      $this->requireReceipt($stored, $hash, $dialogueId, $choiceId, $userId);
      $this->pdo->commit();
      return $stored['result'];
    } catch (DialogueCompletionException|DialogueCompletionIntegrityException|IdempotencyConflictException $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $e;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw new DialogueCompletionIntegrityException('Dialogue completion failed safely.', 0, $e);
    }
  }

  /** @param array{operation_type:string,request_hash:string,result:array<string,mixed>} $receipt */
  private function requireReceipt(array $receipt, string $hash, string $dialogueId, string $choiceId, int $userId): void
  {
    if ($receipt['operation_type'] !== self::OPERATION || !hash_equals($receipt['request_hash'], $hash)) {
      throw new IdempotencyConflictException('Idempotency key was already used for another request.');
    }
    $result = $receipt['result'];
    if (!$this->exact($result, ['dialogue_id', 'choice_id', 'response', 'knowledge', 'player_revision'])
      || $result['dialogue_id'] !== $dialogueId || $result['choice_id'] !== $choiceId
      || !is_string($result['response']) || trim($result['response']) === ''
      || strlen($result['response']) > 2000
      || !is_int($result['player_revision']) || $result['player_revision'] < 0
      || $result['player_revision'] > ClientSafeInteger::MAXIMUM) {
      throw new DialogueCompletionIntegrityException('Persisted dialogue receipt is invalid.');
    }
    $knowledge = $result['knowledge'];
    if ($knowledge !== null) {
      if (!is_array($knowledge) || array_is_list($knowledge) || !$this->exact($knowledge, ['codex_id', 'outcome'])
        || !is_string($knowledge['codex_id'])
        || preg_match('/^codex\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $knowledge['codex_id']) !== 1
        || !in_array($knowledge['outcome'], ['granted', 'already_owned'], true)
        || !$this->codex->owns($userId, $knowledge['codex_id'])) {
        throw new DialogueCompletionIntegrityException('Persisted dialogue knowledge is invalid.');
      }
      $this->content->codexEntry($knowledge['codex_id']);
    }
  }

  /** @param array<string,mixed> $value @param list<string> $keys */
  private function exact(array $value, array $keys): bool
  { $actual = array_keys($value); sort($actual); sort($keys); return $actual === $keys; }
}
