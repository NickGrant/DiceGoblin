<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionException;
use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Repositories\PlayerStateRepository;
use Throwable;

final class ShrineNodeResolutionHandler implements ActionRunNodeResolutionHandler
{
  public function __construct(
    private readonly PlayerStateRepository $players,
    private readonly ContentRegistry $content,
  ) {}

  public function nodeTypeId(): string { return 'run_node_type.shrine'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    throw new RunNodeResolutionException('invalid_node_request', 'Shrine requires an authorized encounter action.', 400);
  }

  public function resolveAction(int $userId, array $playerState, array $run, array $node,
    string $encounterId, string $actionId): RunNodeResolutionOutcome
  {
    if (($node['encounter_id'] ?? null) === null) {
      throw new RunNodeResolutionIntegrityException('Persisted Shrine encounter is missing.');
    }
    if ($node['encounter_id'] !== $encounterId) {
      throw new RunNodeResolutionException('invalid_node_request', 'Encounter does not match this node.', 400);
    }
    try {
      $encounter = $this->content->encounter($encounterId);
    } catch (Throwable) {
      throw new RunNodeResolutionIntegrityException('Persisted Shrine encounter is unavailable.');
    }
    if (($encounter['kind'] ?? null) !== 'non_combat' || ($encounter['family'] ?? null) !== 'shrine'
      || ($encounter['region_id'] ?? null) !== ($run['region_id'] ?? null)) {
      throw new RunNodeResolutionIntegrityException('Persisted Shrine encounter does not match this run.');
    }
    $action = null;
    foreach ($encounter['actions'] as $candidate) {
      if ($candidate['id'] === $actionId) { $action = $candidate; break; }
    }
    if ($action === null) throw new RunNodeResolutionException('invalid_node_request', 'Encounter action is unavailable.', 400);
    $effect = $action['effect'];
    if (($effect['type'] ?? null) !== 'grant_currency'
      || !in_array($effect['currency_id'] ?? null, ['teeth', 'raw_chaos'], true)
      || !is_int($effect['amount'] ?? null) || $effect['amount'] < 1 || $effect['amount'] > 20) {
      throw new RunNodeResolutionIntegrityException('Shrine effect is unsupported.');
    }
    $currency = $effect['currency_id'];
    $before = $playerState[$currency] ?? null;
    if (!is_int($before) || $before < 0 || $before > PHP_INT_MAX - $effect['amount']) {
      throw new RunNodeResolutionIntegrityException('Shrine wallet state is invalid.');
    }
    try {
      $after = $before + $effect['amount'];
      $this->players->applyCurrencyTransition($userId, $currency, $before, $after);
      return new RunNodeResolutionOutcome('non_combat', [
        'encounter_id' => $encounterId, 'action_id' => $actionId,
        'wallet' => [$currency => $after],
        'granted_rewards' => [['reward_type' => 'currency', 'currency_id' => $currency, 'amount' => $effect['amount']]],
      ]);
    } catch (Throwable $e) {
      throw new RunNodeResolutionIntegrityException('Shrine resolution failed.', 0, $e);
    }
  }
}
