const ATLAS_PATH = '/assets/ui/dice/dice_sheet.png';

const MATERIAL_BY_RARITY: Record<string, string> = {
  common: 'cardboard',
  uncommon: 'wood',
  rare: 'bone',
  epic: 'metal',
  legendary: 'gemstone',
};

const FRAME_COLUMN_BY_SIDES: Record<number, number> = {
  4: 0,
  6: 1,
  8: 2,
  10: 3,
  12: 5,
  20: 6,
};

const FRAME_ROW_BY_MATERIAL: Record<string, number> = {
  bone: 0,
  cardboard: 1,
  gemstone: 2,
  metal: 3,
  wood: 4,
};

function normalizeSides(sides: number | null | undefined): number {
  if (!sides) {
    return 6;
  }

  if (sides <= 4) {
    return 4;
  }
  if (sides <= 6) {
    return 6;
  }
  if (sides <= 8) {
    return 8;
  }
  if (sides <= 10) {
    return 10;
  }
  if (sides <= 12) {
    return 12;
  }

  return 20;
}

function resolveDiceMaterial(rarity: string | null | undefined): string {
  const normalizedRarity = rarity?.toLowerCase() ?? 'common';
  return MATERIAL_BY_RARITY[normalizedRarity] ?? MATERIAL_BY_RARITY['common'];
}

export interface DiceArtStyles {
  backgroundImage: string;
  backgroundPosition: string;
  backgroundSize: string;
}

export function resolveDiceArtStyles(
  rarity: string | null | undefined,
  sides: number | null | undefined,
  displaySize: number,
): DiceArtStyles {
  const material = resolveDiceMaterial(rarity);
  const normalizedSides = normalizeSides(sides);
  const column = FRAME_COLUMN_BY_SIDES[normalizedSides] ?? FRAME_COLUMN_BY_SIDES[6];
  const row = FRAME_ROW_BY_MATERIAL[material] ?? FRAME_ROW_BY_MATERIAL['cardboard'];
  const atlasWidth = 7 * displaySize;
  const atlasHeight = 5 * displaySize;

  return {
    backgroundImage: `url('${ATLAS_PATH}')`,
    backgroundPosition: `${-(column * displaySize)}px ${-(row * displaySize)}px`,
    backgroundSize: `${atlasWidth}px ${atlasHeight}px`,
  };
}
