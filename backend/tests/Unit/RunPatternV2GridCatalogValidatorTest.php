<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Services\RunPatternV2GridCatalogValidator;
use PHPUnit\Framework\TestCase;

final class RunPatternV2GridCatalogValidatorTest extends TestCase
{
  public function testAcceptsValidPatternV2GridDefinition(): void
  {
    $result = (new RunPatternV2GridCatalogValidator())->validateDefinitions([
      ['definition' => $this->validPattern()],
    ]);

    $this->assertTrue($result['valid'], implode("\n", $result['errors']));
    $this->assertSame([], $result['errors']);
    $this->assertSame(1, $result['pattern_count']);
  }

  public function testRejectsMalformedPatternV2GridDefinition(): void
  {
    $bad = $this->validPattern();
    $bad['width'] = 3;
    $bad['height'] = 2;
    $bad['grid'] = [
      [
        ['key' => 'start', 'type' => 'combat'],
        ['type' => 'connector', 'key' => 'fake_node'],
      ],
      [
        ['key' => 'start', 'type' => 'mystery'],
        null,
      ],
      [
        null,
        null,
      ],
    ];
    $bad['connections'] = [
      ['from' => 'start', 'to' => 'missing', 'through' => [['row' => 0, 'col' => 1], ['row' => 1, 'col' => 1], ['row' => 8, 'col' => 1]]],
      ['from' => 'start', 'to' => 'start'],
    ];
    $bad['exits'] = [
      ['row' => 1, 'col' => 1, 'direction' => 'sideways'],
      ['row' => 7, 'col' => 0, 'direction' => 'right'],
    ];

    $result = (new RunPatternV2GridCatalogValidator())->validateDefinitions([
      ['definition' => $bad],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertContains('v2_test_tile@1 grid height must match height 2.', $result['errors']);
    $this->assertContains('v2_test_tile@1 grid row 0 width must match width 3.', $result['errors']);
    $this->assertContains('v2_test_tile@1 connector cell 0:1 must not define a runtime node key.', $result['errors']);
    $this->assertContains('v2_test_tile@1 node at 1:0 has invalid node type mystery.', $result['errors']);
    $this->assertContains('v2_test_tile@1 has missing or duplicate node key start.', $result['errors']);
    $this->assertContains('v2_test_tile@1 connection 0 references unknown endpoint start->missing.', $result['errors']);
    $this->assertContains('v2_test_tile@1 connection 0 through 1:1 must reference a connector cell.', $result['errors']);
    $this->assertContains('v2_test_tile@1 connection 0 has out-of-bounds connector 8:1.', $result['errors']);
    $this->assertContains('v2_test_tile@1 connection 1 may not connect start to itself.', $result['errors']);
    $this->assertContains('v2_test_tile@1 exit 0 must reference a node or connector cell.', $result['errors']);
    $this->assertContains('v2_test_tile@1 exit 0 has invalid direction sideways.', $result['errors']);
    $this->assertContains('v2_test_tile@1 exit 1 is out of bounds at 7:0.', $result['errors']);
  }

  /**
   * @return array<string,mixed>
   */
  private function validPattern(): array
  {
    return [
      'schema_version' => 'pattern-v2',
      'slug' => 'v2_test_tile',
      'version' => 1,
      'status' => 'enabled',
      'width' => 3,
      'height' => 2,
      'cost' => 3,
      'tags' => ['test'],
      'grid' => [
        [
          ['key' => 'start', 'type' => 'combat'],
          ['type' => 'connector'],
          ['key' => 'combat', 'type' => 'combat'],
        ],
        [
          null,
          ['key' => 'loot', 'type' => 'loot'],
          null,
        ],
      ],
      'connections' => [
        ['from' => 'start', 'to' => 'combat', 'through' => [['row' => 0, 'col' => 1]]],
        ['from' => 'start', 'to' => 'loot'],
      ],
      'exits' => [
        ['row' => 0, 'col' => 2, 'direction' => 'right'],
        ['row' => 1, 'col' => 1, 'direction' => 'down'],
      ],
    ];
  }
}
