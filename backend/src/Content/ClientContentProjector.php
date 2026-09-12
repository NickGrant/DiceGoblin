<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

final class ClientContentProjector
{
  private const REGION_FIELDS = ['id', 'display_name', 'art_key'];
  private const KIN_FIELDS = ['id', 'display_name', 'description', 'art_key', 'trait_summary', 'stat_modifiers'];
  private const UNIT_TYPE_FIELDS = ['id', 'display_name', 'description', 'art_key', 'role', 'tier', 'base_stats', 'growth_per_level', 'ability_ids'];
  private const ABILITY_FIELDS = ['id', 'kind', 'display_name', 'description', 'icon_key', 'dice_slot_count'];
  private const DICE_MATERIAL_FIELDS = ['id', 'display_name', 'description', 'art_key', 'allowed_sizes'];
  private const DICE_ASPECT_FIELDS = ['id', 'display_name', 'description', 'allowed_sizes'];
  private const DICE_PROFILE_FIELDS = ['id', 'display_name', 'material_id', 'rarity', 'aspect_ids', 'allowed_sizes'];

  /** @return array{revision: string, content: array<string, array<string, array<string, mixed>>>} */
  public function project(ContentRegistry $registry): array
  {
    return [
      'revision' => $registry->revision(),
      'content' => [
        'regions' => $this->projectType($registry, 'region', self::REGION_FIELDS),
        'kin' => $this->projectType($registry, 'kin', self::KIN_FIELDS),
        'unit_types' => $this->projectType($registry, 'unit_type', self::UNIT_TYPE_FIELDS),
        'abilities' => $this->projectType($registry, 'ability', self::ABILITY_FIELDS),
        'dice_materials' => $this->projectType($registry, 'dice_material', self::DICE_MATERIAL_FIELDS),
        'dice_aspects' => $this->projectType($registry, 'dice_aspect', self::DICE_ASPECT_FIELDS),
        'dice_profiles' => $this->projectType($registry, 'dice_profile', self::DICE_PROFILE_FIELDS),
      ],
    ];
  }

  /** @param list<string> $fields
   *  @return array<string, array<string, mixed>>
   */
  private function projectType(ContentRegistry $registry, string $type, array $fields): array
  {
    $projected = [];
    $allowlist = array_flip($fields);
    foreach ($registry->definitionsOfType($type) as $id => $definition) {
      $projected[$id] = array_intersect_key($definition, $allowlist);
    }
    ksort($projected, SORT_STRING);
    return $projected;
  }
}
