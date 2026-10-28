<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Application\Objectives\ObjectiveProgressService;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Rewards\RewardContext;
use DiceGoblins\Repositories\UserCodexRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use Throwable;

final class DialogueNodeResolutionHandler implements ActionRunNodeResolutionHandler
{
  public function __construct(
    private readonly ContentRegistry $content,
    private readonly UserCodexRepository $codex,
    private readonly UserUnlockRepository $unlocks,
    private readonly RewardApplicationService $rewards,
    private readonly ObjectiveProgressService $objectives,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.dialogue'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    throw new RunNodeResolutionException('invalid_node_request', 'Dialogue requires an authorized choice.', 400);
  }

  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) !== $encounterId) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter does not match this node.', 400);
    }
    try {
      $encounter = $this->content->encounter($encounterId);
      if (($encounter['kind'] ?? null) !== 'non_combat' || ($encounter['family'] ?? null) !== 'dialogue'
        || ($encounter['region_id'] ?? null) !== ($run['region_id'] ?? null)
        || ($node['encounter_step_id'] ?? null) !== null) {
        throw new RunNodeResolutionIntegrityException('Persisted dialogue encounter does not match this run.');
      }
      $dialogue = $this->content->dialogue($encounter['dialogue_id']);
      $choice = null;
      foreach ($dialogue['choices'] as $candidate) {
        if ($candidate['id'] === $actionId) { $choice = $candidate; break; }
      }
      if ($choice === null) throw new RunNodeResolutionException('invalid_node_request', 'Dialogue choice is unavailable.', 400);
      $owned = $this->codex->listIdsForUser($userId, true);
      foreach ($owned as $id) $this->content->codexEntry($id);
      foreach ($choice['required_codex_ids'] as $required) {
        if (!in_array($required, $owned, true)) {
          throw new RunNodeResolutionException('invalid_node_request', 'Dialogue choice is unavailable.', 400);
        }
      }
      $knowledge = null;
      if (isset($choice['event_id'])) {
        $event = $this->content->event($choice['event_id']);
        $reward = $this->content->rewardDefinition($event['reward_definition_id']);
        if (count($reward['entries']) !== 1 || $reward['entries'][0]['reward_type'] !== 'codex'
          || $reward['entries'][0]['probability_basis_points'] !== 10000) {
          throw new RunNodeResolutionIntegrityException('Dialogue reward is incoherent.');
        }
        $sourceId = hash('sha256', $run['id'] . ':' . $node['id'] . ':' . $actionId);
        $result = $this->rewards->apply($userId, $choice['event_id'], 'dialogue_completion', $sourceId,
          new RewardContext((int)$playerState['teeth'], (int)$playerState['raw_chaos'], [],
            $this->unlocks->listIdsForUser($userId, true), $owned));
        $entry = $result->entries()[0] ?? null;
        $codexId = $reward['entries'][0]['config']['codex_id'];
        if (count($result->entries()) !== 1 || $entry['reward_type'] !== 'codex'
          || !in_array($entry['outcome'], ['granted', 'already_owned'], true)
          || $entry['grant']['codex_id'] !== $codexId || !$this->codex->owns($userId, $codexId)) {
          throw new RunNodeResolutionIntegrityException('Dialogue knowledge grant is incoherent.');
        }
        $knowledge = ['codex_id' => $codexId, 'outcome' => $entry['outcome']];
      }
      $this->objectives->apply($userId, 'complete_dialogue', $encounter['dialogue_id']);
      return new RunNodeResolutionOutcome('dialogue', [
        'encounter_id' => $encounterId, 'action_id' => $actionId,
        'response' => $choice['response'], 'knowledge' => $knowledge,
      ]);
    } catch (RunNodeResolutionException|RunNodeResolutionIntegrityException $e) {
      throw $e;
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Dialogue resolution failed.', 0, $e);
    }
  }
}
