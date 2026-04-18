<?php
declare(strict_types=1);

namespace DiceGoblins\Services;

final class UnitNameGenerator
{
  /** @var list<string> */
  private const FIRST = [
    'Ash', 'Bog', 'Bone', 'Brass', 'Briar', 'Cinder', 'Coal', 'Copper',
    'Crow', 'Dirt', 'Ember', 'Flint', 'Grit', 'Hook', 'Iron', 'Kettle',
    'Knuckle', 'Lichen', 'Mire', 'Moss', 'Mud', 'Oath', 'Pike', 'Rag',
    'Rust', 'Shade', 'Shard', 'Soot', 'Spine', 'Thorn',
  ];

  /** @var list<string> */
  private const SECOND = [
    'back', 'bane', 'belly', 'bite', 'brand', 'brow', 'chewer', 'claw',
    'drum', 'fang', 'grin', 'hand', 'hide', 'jaw', 'lash', 'lock',
    'mark', 'muck', 'nose', 'patch', 'picker', 'root', 'scar', 'snout',
    'spike', 'stone', 'tooth', 'walker', 'whistle', 'wort',
  ];

  public function generate(): string
  {
    $first = self::FIRST[random_int(0, count(self::FIRST) - 1)];
    $second = self::SECOND[random_int(0, count(self::SECOND) - 1)];

    return $first . $second;
  }
}
