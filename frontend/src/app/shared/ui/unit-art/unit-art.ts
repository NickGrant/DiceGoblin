const UNIT_ASSET_BASE_PATH = '/assets/ui/units';

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
  return slug ? `${UNIT_ASSET_BASE_PATH}/${slug}.png` : null;
}

function normalizeUnitImageKey(value: string | null | undefined): string {
  return (value ?? '')
    .trim()
    .toLowerCase()
    // Combat logs and generated labels may suffix duplicate enemies with counters.
    .replace(/\s*#\d+\s*$/g, '')
    .replace(/\s+\d+\s*$/g, '');
}
