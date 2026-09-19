<?php
declare(strict_types=1);

namespace DiceGoblins\Application\RunNodes;

use DiceGoblins\Application\Commands\RunNodeResolutionIntegrityException;
use DiceGoblins\Repositories\RunNodeResolutionRepository;

final class ExitNodeResolutionHandler implements RunNodeResolutionHandler
{
  public function __construct(private readonly RunNodeResolutionRepository $nodes) {}

  public function nodeTypeId(): string { return 'run_node_type.exit'; }

  public function resolve(int $userId, array $playerState, array $run, array $node): RunNodeResolutionOutcome
  {
    $runId = (int)($run['id'] ?? 0);
    $nodeId = (int)($node['id'] ?? 0);
    if (($run['region_id'] ?? null) !== 'region.the_farm'
      || ($node['node_type_id'] ?? null) !== $this->nodeTypeId()
      || (int)($node['node_index'] ?? -1) !== 4
      || ($node['encounter_id'] ?? null) !== null
      || ($node['event_id'] ?? null) !== null
      || !$this->nodes->isTerminalFarmExit($runId, $nodeId)) {
      throw new RunNodeResolutionIntegrityException('Persisted Farm Exit identity is invalid.');
    }
    return new RunNodeResolutionOutcome('exit', [], false, true);
  }
}
