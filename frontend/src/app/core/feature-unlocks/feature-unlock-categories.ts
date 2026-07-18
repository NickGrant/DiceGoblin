export const FEATURE_UNLOCK_CATEGORY_BY_PRODUCT_ID = {
  academy: 'Feature Unlock',
  bigger_squad: 'Squad Upgrade',
  biggerest_squad: 'Squad Upgrade',
  shop_discount: 'Economy Upgrade',
  sell_bonus: 'Economy Upgrade',
  market_mastery: 'Economy Upgrade',
  second_daily_deal: 'Feature Unlock',
  energy_cap_75: 'Energy Upgrade',
  energy_cap_100: 'Energy Upgrade',
  explode_d4s: 'Dice Upgrade',
} as const;

export type FeatureUnlockProductId = keyof typeof FEATURE_UNLOCK_CATEGORY_BY_PRODUCT_ID;
export type FeatureUnlockCategoryLabel = (typeof FEATURE_UNLOCK_CATEGORY_BY_PRODUCT_ID)[FeatureUnlockProductId];

export const FEATURE_UNLOCK_CATEGORY_DETAILS: ReadonlyArray<{
  label: FeatureUnlockCategoryLabel;
  description: string;
}> = [
  { label: 'Feature Unlock', description: 'New account systems or shop capabilities.' },
  { label: 'Squad Upgrade', description: 'Warband capacity upgrades that let you field larger squads.' },
  { label: 'Economy Upgrade', description: 'Shop and sale upgrades that improve long-term buying power.' },
  { label: 'Energy Upgrade', description: 'Account stamina upgrades that let you run longer routes.' },
  { label: 'Dice Upgrade', description: 'Dice rule upgrades that change how rolls can pay off.' },
];

export function resolveFeatureUnlockCategory(productId: string): FeatureUnlockCategoryLabel {
  if (Object.prototype.hasOwnProperty.call(FEATURE_UNLOCK_CATEGORY_BY_PRODUCT_ID, productId)) {
    return FEATURE_UNLOCK_CATEGORY_BY_PRODUCT_ID[productId as FeatureUnlockProductId];
  }

  return 'Feature Unlock';
}
