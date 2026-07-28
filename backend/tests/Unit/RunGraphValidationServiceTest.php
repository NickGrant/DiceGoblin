<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunGraphValidationService;
use PHPUnit\Framework\TestCase;

final class RunGraphValidationServiceTest extends TestCase
{
  public function testAcceptsLinearBossGatedGraph(): void
  {
    $result = (new RunGraphValidationService())->validate([
      'nodes' => [
        ['key' => 'start', 'type' => 'start', 'x' => 0, 'y' => 0],
        ['key' => 'combat', 'type' => 'combat', 'x' => 1, 'y' => 0],
        ['key' => 'boss', 'type' => 'boss', 'x' => 2, 'y' => 0],
        ['key' => 'exit', 'type' => 'exit', 'x' => 3, 'y' => 0],
      ],
      'edges' => [
        ['from' => 'start', 'to' => 'combat'],
        ['from' => 'combat', 'to' => 'boss'],
        ['from' => 'boss', 'to' => 'exit'],
      ],
    ]);

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  public function testRejectsExitPathThatBypassesBoss(): void
  {
    $result = (new RunGraphValidationService())->validate([
      'nodes' => [
        ['key' => 'start', 'type' => 'start', 'x' => 0, 'y' => 0],
        ['key' => 'combat', 'type' => 'combat', 'x' => 1, 'y' => 0],
        ['key' => 'boss', 'type' => 'boss', 'x' => 2, 'y' => 0],
        ['key' => 'exit', 'type' => 'exit', 'x' => 3, 'y' => 0],
      ],
      'edges' => [
        ['from' => 'start', 'to' => 'combat'],
        ['from' => 'combat', 'to' => 'boss'],
        ['from' => 'boss', 'to' => 'exit'],
        ['from' => 'combat', 'to' => 'exit'],
      ],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertContains('exit_bypasses_boss', $result['errors']);
  }

  public function testRejectsUnreachableNodesBadEdgesOverlapsAndVisibleSockets(): void
  {
    $result = (new RunGraphValidationService())->validate([
      'nodes' => [
        ['key' => 'start', 'type' => 'start', 'x' => 0, 'y' => 0],
        ['key' => 'boss', 'type' => 'boss', 'x' => 1, 'y' => 0],
        ['key' => 'exit', 'type' => 'exit', 'x' => 1, 'y' => 0, 'open_sockets' => [['id' => 'east', 'visible' => true]]],
        ['key' => 'loot', 'type' => 'loot', 'x' => 5, 'y' => 0],
      ],
      'edges' => [
        ['from' => 'start', 'to' => 'missing'],
        ['from' => 'boss', 'to' => 'exit'],
      ],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertContains('edge_missing_to:0', $result['errors']);
    $this->assertContains('node_overlap:boss:exit', $result['errors']);
    $this->assertContains('open_visible_socket:exit:east', $result['errors']);
    $this->assertContains('unreachable_node:loot', $result['errors']);
  }
}
