import type Phaser from "phaser";

type PortraitManifestEntry = {
  key: string;
  url: string;
};

const PORTRAIT_BASE_URL = "/assets/ui/portraits";

export const UNIT_PORTRAIT_MANIFEST: PortraitManifestEntry[] = [
  { key: "portrait_frogman_wardrummer", url: `${PORTRAIT_BASE_URL}/frogman_wardrummer.png` },
  { key: "portrait_frogman_spearhunter", url: `${PORTRAIT_BASE_URL}/frogman_spearhunter.png` },
  { key: "portrait_frogman_bruiser", url: `${PORTRAIT_BASE_URL}/frogman_bruiser.png` },
  { key: "portrait_frogman_bog_tyrant", url: `${PORTRAIT_BASE_URL}/frogman_bog_tyrant.png` },
  { key: "portrait_goblin_bulwark", url: `${PORTRAIT_BASE_URL}/goblin_bulwark.png` },
  { key: "portrait_goblin_bannerbearer", url: `${PORTRAIT_BASE_URL}/goblin_bannerbearer.png` },
  { key: "portrait_goblin_bruiser", url: `${PORTRAIT_BASE_URL}/gobling_bruiser.png` },
  { key: "portrait_goblin_deadeye", url: `${PORTRAIT_BASE_URL}/goblin_deadeye.png` },
  { key: "portrait_goblin_enforcer", url: `${PORTRAIT_BASE_URL}/goblin_enforcer.png` },
  { key: "portrait_goblin_guardian", url: `${PORTRAIT_BASE_URL}/goblin_guardian.png` },
  { key: "portrait_goblin_ironwall", url: `${PORTRAIT_BASE_URL}/goblin_ironwall.png` },
  { key: "portrait_goblin_warcaller", url: `${PORTRAIT_BASE_URL}/goblin_warcaller.png` },
  { key: "portrait_goblin_trickshot", url: `${PORTRAIT_BASE_URL}/goblin_trickshot.png` },
  { key: "portrait_goblin_sharpshot", url: `${PORTRAIT_BASE_URL}/goblin_sharpshot.png` },
  { key: "portrait_goblin_saboteur", url: `${PORTRAIT_BASE_URL}/goblin_saboteur.png` },
  { key: "portrait_goblin_marksman", url: `${PORTRAIT_BASE_URL}/goblin_marksman.png` },
  { key: "portrait_goblin_juggernaut", url: `${PORTRAIT_BASE_URL}/goblin_juggernaut.png` },
  { key: "portrait_kobold_warchief", url: `${PORTRAIT_BASE_URL}/kobold_warchief.png` },
  { key: "portrait_kobold_skirmisher", url: `${PORTRAIT_BASE_URL}/kobold_skirmisher.png` },
  { key: "portrait_kobold_shieldbearer", url: `${PORTRAIT_BASE_URL}/kobold_shieldbearer.png` },
  { key: "portrait_kobold_sharpshooter", url: `${PORTRAIT_BASE_URL}/kobold_sharpshooter.png` },
  { key: "portrait_pig_mudslinger", url: `${PORTRAIT_BASE_URL}/pig_mudslinger.png` },
  { key: "portrait_pig_mudking", url: `${PORTRAIT_BASE_URL}/pig_mudking.png` },
  { key: "portrait_pig_mudwrestler", url: `${PORTRAIT_BASE_URL}/pig_mudwrestler.png` },
];

const PORTRAIT_KEY_BY_SLUG: Record<string, string> = {
  frontline_bruiser_t1: "portrait_goblin_bruiser",
  frontline_bruiser_t2: "portrait_goblin_enforcer",
  frontline_bruiser_t3: "portrait_goblin_juggernaut",
  frontline_guardian_t1: "portrait_goblin_guardian",
  frontline_guardian_t2: "portrait_goblin_bulwark",
  frontline_guardian_t3: "portrait_goblin_ironwall",
  backline_marksman_t1: "portrait_goblin_marksman",
  backline_marksman_t2: "portrait_goblin_deadeye",
  backline_marksman_t3: "portrait_goblin_sharpshot",
  support_banner_t1: "portrait_goblin_bannerbearer",
  support_banner_t2: "portrait_goblin_warcaller",
  control_saboteur_t1: "portrait_goblin_saboteur",
  control_saboteur_t2: "portrait_goblin_trickshot",
  kobold_skirmisher: "portrait_kobold_skirmisher",
  kobold_shieldbearer: "portrait_kobold_shieldbearer",
  kobold_sharpshooter: "portrait_kobold_sharpshooter",
  kobold_warchief: "portrait_kobold_warchief",
  frogman_bruiser: "portrait_frogman_bruiser",
  frogman_spearhunter: "portrait_frogman_spearhunter",
  frogman_wardrummer: "portrait_frogman_wardrummer",
  frogman_bog_tyrant: "portrait_frogman_bog_tyrant",
  pig_mudslinger: "portrait_pig_mudslinger",
  pig_mudwrestler: "portrait_pig_mudwrestler",
  pig_mudking: "portrait_pig_mudking",
};

const PORTRAIT_KEY_BY_NAME: Record<string, string> = {
  bruiser: "portrait_goblin_bruiser",
  enforcer: "portrait_goblin_enforcer",
  juggernaut: "portrait_goblin_juggernaut",
  guardian: "portrait_goblin_guardian",
  bulwark: "portrait_goblin_bulwark",
  ironwall: "portrait_goblin_ironwall",
  marksman: "portrait_goblin_marksman",
  deadeye: "portrait_goblin_deadeye",
  sharpshot: "portrait_goblin_sharpshot",
  bannerbearer: "portrait_goblin_bannerbearer",
  warcaller: "portrait_goblin_warcaller",
  saboteur: "portrait_goblin_saboteur",
  trickshot: "portrait_goblin_trickshot",
  "kobold skirmisher": "portrait_kobold_skirmisher",
  "kobold shieldbearer": "portrait_kobold_shieldbearer",
  "kobold sharpshooter": "portrait_kobold_sharpshooter",
  "kobold warchief": "portrait_kobold_warchief",
  "frogman bruiser": "portrait_frogman_bruiser",
  "frogman spearhunter": "portrait_frogman_spearhunter",
  "frogman wardrummer": "portrait_frogman_wardrummer",
  "bog tyrant": "portrait_frogman_bog_tyrant",
  mudslinger: "portrait_pig_mudslinger",
  mudwrestler: "portrait_pig_mudwrestler",
  mudking: "portrait_pig_mudking",
};

function normalizePortraitLookup(value: string | null | undefined): string {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
}

export function preloadUnitPortraits(scene: Phaser.Scene): void {
  for (const portrait of UNIT_PORTRAIT_MANIFEST) {
    if (scene.textures.exists(portrait.key)) {
      continue;
    }
    scene.load.image(portrait.key, portrait.url);
  }
}

export function resolveUnitPortraitKey(
  unitTypeSlug?: string | null,
  unitTypeName?: string | null,
): string {
  const slugKey = normalizePortraitLookup(unitTypeSlug);
  if (slugKey && PORTRAIT_KEY_BY_SLUG[slugKey]) {
    return PORTRAIT_KEY_BY_SLUG[slugKey];
  }

  const nameKey = normalizePortraitLookup(unitTypeName).replace(/_/g, " ");
  if (nameKey && PORTRAIT_KEY_BY_NAME[nameKey]) {
    return PORTRAIT_KEY_BY_NAME[nameKey];
  }

  return "icon_warband";
}

export function fitPortraitImage(
  image: Phaser.GameObjects.Image,
  textureKey: string,
  maxWidth: number,
  maxHeight: number,
): Phaser.GameObjects.Image {
  const safeKey = image.scene.textures.exists(textureKey) ? textureKey : "icon_warband";
  image.setTexture(safeKey);
  image.setScale(1);

  const scale = Math.min(
    maxWidth / Math.max(1, image.width),
    maxHeight / Math.max(1, image.height),
    1,
  );
  image.setDisplaySize(
    Math.max(1, Math.round(image.width * scale)),
    Math.max(1, Math.round(image.height * scale)),
  );
  return image;
}
