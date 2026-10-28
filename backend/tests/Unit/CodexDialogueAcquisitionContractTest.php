<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ContentRegistry;
use PHPUnit\Framework\TestCase;

final class CodexDialogueAcquisitionContractTest extends TestCase
{
  public function testAuthoredCodexEntriesHaveReachableDialogueRewardPaths(): void
  {
    $content = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $codexIds = array_keys($content->definitionsOfType('codex_entry'));
    $reachable = [];
    foreach ($content->definitionsOfType('dialogue') as $dialogue) {
      foreach ($dialogue['choices'] as $choice) {
        if (!isset($choice['event_id'])) continue;
        $event = $content->event($choice['event_id']);
        $reward = $content->rewardDefinition($event['reward_definition_id']);
        foreach ($reward['entries'] as $entry) {
          if ($entry['reward_type'] === 'codex' && $entry['probability_basis_points'] === 10000) {
            $reachable[$entry['config']['codex_id']] = true;
          }
        }
      }
    }
    sort($codexIds, SORT_STRING);
    $reachableIds = array_keys($reachable);
    sort($reachableIds, SORT_STRING);
    $this->assertSame($codexIds, $reachableIds,
      'Every player-visible Codex entry needs an authored dialogue choice with a certain grant.');
  }
}
