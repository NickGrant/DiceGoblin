import { UnitRecord } from '../../../core/models/api.models';
import { resolveUnitImageSlug, resolveUnitImageUrl } from '../unit-art/unit-art';

const PROTOTYPE_GOBLIN_SPRITE_URL = '/assets/ui/units/animated/goblin/base/frame_0.png';

type PrototypeUnitSource =
  | Pick<UnitRecord, 'name' | 'unit_type_name' | 'unit_type_slug'>
  | string
  | null
  | undefined;

export function resolvePrototypeUnitSpriteUrl(source: PrototypeUnitSource): string {
  const slug = typeof source === 'string'
    ? resolveUnitImageSlug(source)
    : resolveUnitImageSlug(source?.unit_type_slug ?? source?.unit_type_name ?? source?.name);

  if (!slug) {
    return PROTOTYPE_GOBLIN_SPRITE_URL;
  }

  return resolveUnitImageUrl(slug) ?? PROTOTYPE_GOBLIN_SPRITE_URL;
}

export function resolvePrototypeEnemySpriteUrl(enemySlug: string | null | undefined): string {
  const normalized = resolveUnitImageSlug(enemySlug);
  if (!normalized) {
    return PROTOTYPE_GOBLIN_SPRITE_URL;
  }

  return resolveUnitImageUrl(normalized) ?? PROTOTYPE_GOBLIN_SPRITE_URL;
}
