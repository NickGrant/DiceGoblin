import { UnitRecord } from '../../../core/models/api.models';
import { resolveUnitImageSlug } from '../unit-art/unit-art';

const PROTOTYPE_GOBLIN_SPRITE_URL = '/assets/ui/units/animated/goblin/base/frame_0.png';
const PROTOTYPE_KOBOLD_FALLBACK_SPRITE_URL = '/assets/ui/units/animated/kobold/sharpshooter/frame_0.png';

const KOBOLD_SPRITE_MAP: Record<string, string> = {
  kobold_skirmisher: '/assets/ui/units/animated/kobold/skirmisher/frame_0.png',
  kobold_shieldbearer: '/assets/ui/units/animated/kobold/shieldbearer/frame_0.png',
  kobold_sharpshooter: '/assets/ui/units/animated/kobold/sharpshooter/frame_0.png',
  kobold_warchief: '/assets/ui/units/animated/kobold/warchief/frame_0.png',
};

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
    return PROTOTYPE_KOBOLD_FALLBACK_SPRITE_URL;
  }

  if (slug.startsWith('kobold_') && KOBOLD_SPRITE_MAP[slug]) {
    return KOBOLD_SPRITE_MAP[slug];
  }

  if (slug.startsWith('goblin_') || isGoblinClassSlug(slug)) {
    return PROTOTYPE_GOBLIN_SPRITE_URL;
  }

  return PROTOTYPE_KOBOLD_FALLBACK_SPRITE_URL;
}

export function resolvePrototypeEnemySpriteUrl(enemySlug: string | null | undefined): string {
  const normalized = resolveUnitImageSlug(enemySlug);
  if (normalized && normalized.startsWith('kobold_') && KOBOLD_SPRITE_MAP[normalized]) {
    return KOBOLD_SPRITE_MAP[normalized];
  }

  if (!normalized || normalized.startsWith('goblin_') || isGoblinClassSlug(normalized)) {
    return PROTOTYPE_GOBLIN_SPRITE_URL;
  }

  return PROTOTYPE_KOBOLD_FALLBACK_SPRITE_URL;
}

function isGoblinClassSlug(slug: string): boolean {
  return [
    'frontline_',
    'backline_',
    'support_',
    'control_',
    'bruiser',
    'enforcer',
    'pit_fighter',
    'juggernaut',
    'guardian',
    'bulwark',
    'shieldbreaker',
    'ironwall',
    'marksman',
    'deadeye',
    'trapper',
    'sharpshot',
    'bannerbearer',
    'warcaller',
    'mascot',
    'saboteur',
    'trickshot',
    'plaguehand',
  ].some((prefix) => slug.startsWith(prefix));
}
