const UNIT_ASSET_BASE_PATH = '/assets/ui/units';
const UNIT_ANIMATED_ASSET_BASE_PATH = '/assets/ui/units/animated';
const UNIT_THUMBNAIL_ASSET_BASE_PATH = '/assets/ui/units/thumbnails';

const ANIMATED_UNIT_FRAME_COUNTS: Record<string, number> = {
  goblin_bruiser: 4,
  goblin_enforcer: 4,
  goblin_pit_fighter: 4,
  goblin_juggernaut: 4,
  goblin_guardian: 4,
  goblin_bulwark: 4,
  goblin_shieldbreaker: 4,
  goblin_ironwall: 4,
  goblin_marksman: 4,
  goblin_deadeye: 4,
  goblin_trapper: 4,
  goblin_sharpshot: 4,
  goblin_bannerbearer: 4,
  goblin_warcaller: 4,
  goblin_mascot: 4,
  goblin_saboteur: 4,
  goblin_trickshot: 4,
  goblin_plaguehand: 4,
  kobold_skirmisher: 4,
  kobold_shieldbearer: 4,
  kobold_sharpshooter: 4,
  kobold_warchief: 4,
  pig_mudwrestler: 4,
  pig_mudslinger: 4,
  pig_mudking: 4,
  frogman_bruiser: 1,
  frogman_spearhunter: 1,
  frogman_wardrummer: 1,
  frogman_bog_tyrant: 1,
};

const ANIMATED_UNIT_FOLDER_MAP: Record<string, string> = {
  goblin_bruiser: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bruiser`,
  goblin_enforcer: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bruiser`,
  goblin_pit_fighter: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bruiser`,
  goblin_juggernaut: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bruiser`,
  goblin_guardian: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/guardian`,
  goblin_bulwark: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/guardian`,
  goblin_shieldbreaker: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/guardian`,
  goblin_ironwall: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/guardian`,
  goblin_marksman: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/marksmen`,
  goblin_deadeye: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/marksmen`,
  goblin_trapper: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/marksmen`,
  goblin_sharpshot: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/marksmen`,
  goblin_bannerbearer: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bannerbearer`,
  goblin_warcaller: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bannerbearer`,
  goblin_mascot: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/bannerbearer`,
  goblin_saboteur: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/saboteur`,
  goblin_trickshot: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/saboteur`,
  goblin_plaguehand: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/base/saboteur`,
  kobold_skirmisher: `${UNIT_ANIMATED_ASSET_BASE_PATH}/kobold/skirmisher`,
  kobold_shieldbearer: `${UNIT_ANIMATED_ASSET_BASE_PATH}/kobold/shieldbearer`,
  kobold_sharpshooter: `${UNIT_ANIMATED_ASSET_BASE_PATH}/kobold/sharpshooter`,
  kobold_warchief: `${UNIT_ANIMATED_ASSET_BASE_PATH}/kobold/warchief`,
  pig_mudwrestler: `${UNIT_ANIMATED_ASSET_BASE_PATH}/pig/mudwrestler`,
  pig_mudslinger: `${UNIT_ANIMATED_ASSET_BASE_PATH}/pig/mudslinger`,
  pig_mudking: `${UNIT_ANIMATED_ASSET_BASE_PATH}/pig/mudking`,
  frogman_bruiser: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/frogman`,
  frogman_spearhunter: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/frogman`,
  frogman_wardrummer: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/frogman`,
  frogman_bog_tyrant: `${UNIT_ANIMATED_ASSET_BASE_PATH}/goblin/frogman`,
};

const UNIT_IMAGE_SLUG_MAP: Record<string, string> = {
  frontline_bruiser_t1: 'goblin_bruiser',
  frontline_bruiser_t2: 'goblin_enforcer',
  frontline_pit_fighter_t2: 'goblin_pit_fighter',
  frontline_bruiser_t3: 'goblin_juggernaut',
  frontline_guardian_t1: 'goblin_guardian',
  frontline_guardian_t2: 'goblin_bulwark',
  frontline_shieldbreaker_t2: 'goblin_shieldbreaker',
  frontline_guardian_t3: 'goblin_ironwall',
  backline_marksman_t1: 'goblin_marksman',
  backline_marksman_t2: 'goblin_deadeye',
  backline_trapper_t2: 'goblin_trapper',
  backline_marksman_t3: 'goblin_sharpshot',
  support_banner_t1: 'goblin_bannerbearer',
  support_banner_t2: 'goblin_warcaller',
  support_mascot_t2: 'goblin_mascot',
  control_saboteur_t1: 'goblin_saboteur',
  control_saboteur_t2: 'goblin_trickshot',
  control_plaguehand_t2: 'goblin_plaguehand',
  bruiser: 'goblin_bruiser',
  enforcer: 'goblin_enforcer',
  'pit fighter': 'goblin_pit_fighter',
  'pit-fighter': 'goblin_pit_fighter',
  juggernaut: 'goblin_juggernaut',
  guardian: 'goblin_guardian',
  bulwark: 'goblin_bulwark',
  shieldbreaker: 'goblin_shieldbreaker',
  ironwall: 'goblin_ironwall',
  marksman: 'goblin_marksman',
  deadeye: 'goblin_deadeye',
  trapper: 'goblin_trapper',
  sharpshot: 'goblin_sharpshot',
  bannerbearer: 'goblin_bannerbearer',
  warcaller: 'goblin_warcaller',
  mascot: 'goblin_mascot',
  saboteur: 'goblin_saboteur',
  trickshot: 'goblin_trickshot',
  plaguehand: 'goblin_plaguehand',
  'goblin bruiser': 'goblin_bruiser',
  'goblin enforcer': 'goblin_enforcer',
  'goblin pit fighter': 'goblin_pit_fighter',
  'goblin juggernaut': 'goblin_juggernaut',
  'goblin guardian': 'goblin_guardian',
  'goblin bulwark': 'goblin_bulwark',
  'goblin shieldbreaker': 'goblin_shieldbreaker',
  'goblin ironwall': 'goblin_ironwall',
  'goblin marksman': 'goblin_marksman',
  'goblin deadeye': 'goblin_deadeye',
  'goblin trapper': 'goblin_trapper',
  'goblin sharpshot': 'goblin_sharpshot',
  'goblin bannerbearer': 'goblin_bannerbearer',
  'goblin warcaller': 'goblin_warcaller',
  'goblin mascot': 'goblin_mascot',
  'goblin saboteur': 'goblin_saboteur',
  'goblin trickshot': 'goblin_trickshot',
  'goblin plaguehand': 'goblin_plaguehand',
  goblin_raider: 'goblin_bruiser',
  'goblin raider': 'goblin_bruiser',
  toad_shaman: 'frogman_wardrummer',
  'toad shaman': 'frogman_wardrummer',
  mudwrestler: 'pig_mudwrestler',
  'mud wrestler': 'pig_mudwrestler',
  mudslinger: 'pig_mudslinger',
  'mud slinger': 'pig_mudslinger',
  mudking: 'pig_mudking',
  'mud king': 'pig_mudking',
  kobold_skirmisher: 'kobold_skirmisher',
  'kobold skirmisher': 'kobold_skirmisher',
  kobold_shieldbearer: 'kobold_shieldbearer',
  'kobold shieldbearer': 'kobold_shieldbearer',
  kobold_sharpshooter: 'kobold_sharpshooter',
  'kobold sharpshooter': 'kobold_sharpshooter',
  kobold_warchief: 'kobold_warchief',
  'kobold warchief': 'kobold_warchief',
  frogman_bruiser: 'frogman_bruiser',
  'frogman bruiser': 'frogman_bruiser',
  frogman_spearhunter: 'frogman_spearhunter',
  'frogman spearhunter': 'frogman_spearhunter',
  frogman_wardrummer: 'frogman_wardrummer',
  'frogman wardrummer': 'frogman_wardrummer',
  frogman_bog_tyrant: 'frogman_bog_tyrant',
  'frogman bog tyrant': 'frogman_bog_tyrant',
};

const GOBLIN_THUMBNAIL_SLUGS = new Set([
  'goblin_bannerbearer',
  'goblin_bruiser',
  'goblin_bulwark',
  'goblin_deadeye',
  'goblin_enforcer',
  'goblin_guardian',
  'goblin_ironwall',
  'goblin_juggernaut',
  'goblin_marksman',
  'goblin_mascot',
  'goblin_pit_fighter',
  'goblin_plaguehand',
  'goblin_saboteur',
  'goblin_sharpshot',
  'goblin_shieldbreaker',
  'goblin_trapper',
  'goblin_trickshot',
  'goblin_warcaller',
]);

export function resolveUnitImageSlug(value: string | null | undefined): string | null {
  const normalized = normalizeUnitImageKey(value);
  if (!normalized.length) {
    return null;
  }

  const mapped = UNIT_IMAGE_SLUG_MAP[normalized];
  if (mapped) {
    return mapped;
  }

  return normalized
    .replace(/-/g, '_')
    .replace(/\s+/g, '_');
}

export function resolveUnitImageUrl(value: string | null | undefined): string | null {
  const slug = resolveUnitImageSlug(value);
  if (!slug) {
    return null;
  }

  return resolveUnitAnimationFrameUrls(value)[0] ?? `${UNIT_ASSET_BASE_PATH}/${slug}.png`;
}

export function resolveUnitAnimationFrameUrls(value: string | null | undefined): string[] {
  const slug = resolveUnitImageSlug(value);
  if (!slug) {
    return [];
  }

  const folder = ANIMATED_UNIT_FOLDER_MAP[slug];
  if (!folder) {
    return [];
  }

  const frameCount = ANIMATED_UNIT_FRAME_COUNTS[slug] ?? 1;
  return Array.from({ length: frameCount }, (_, index) => `${folder}/frame_${index}.png`);
}

export function resolveUnitThumbnailUrl(value: string | null | undefined): string | null {
  const slug = resolveUnitImageSlug(value);
  if (!slug || !GOBLIN_THUMBNAIL_SLUGS.has(slug)) {
    return null;
  }

  return `${UNIT_THUMBNAIL_ASSET_BASE_PATH}/goblin/${slug.replace(/^goblin_/, '')}.png`;
}

export function resolveUnitSilhouetteUrl(): string {
  return `${UNIT_THUMBNAIL_ASSET_BASE_PATH}/goblin/silhouette.png`;
}

function normalizeUnitImageKey(value: string | null | undefined): string {
  return (value ?? '')
    .trim()
    .toLowerCase()
    // Combat logs and generated labels may suffix duplicate enemies with counters.
    .replace(/\s*#\d+\s*$/g, '')
    .replace(/\s+\d+\s*$/g, '');
}
