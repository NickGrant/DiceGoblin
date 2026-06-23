const DICE_ASSET_BASE_PATH = '/assets/ui/dice';

const MATERIAL_BY_RARITY: Record<string, string> = {
  common: 'cardboard',
  uncommon: 'wood',
  rare: 'bone',
  epic: 'metal',
  legendary: 'gemstone',
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
  imageUrl: string;
}

export function resolveDiceArtStyles(
  rarity: string | null | undefined,
  sides: number | null | undefined,
  _displaySize: number,
): DiceArtStyles {
  const material = resolveDiceMaterial(rarity);
  const normalizedSides = normalizeSides(sides);

  return {
    imageUrl: `${DICE_ASSET_BASE_PATH}/${material}_d${normalizedSides}.png`,
  };
}
