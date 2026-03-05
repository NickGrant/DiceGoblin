export const DICE_ATLAS_KEY = "dice_sheet" as const;

export const DICE_FRAME_NAMES = [
  "bone_d10",
  "bone_d10b",
  "bone_d12",
  "bone_d20",
  "bone_d4",
  "bone_d6",
  "bone_d8",
  "cardboard_d10",
  "cardboard_d10b",
  "cardboard_d12",
  "cardboard_d20",
  "cardboard_d4",
  "cardboard_d6",
  "cardboard_d8",
  "gemstone_d10",
  "gemstone_d10b",
  "gemstone_d12",
  "gemstone_d20",
  "gemstone_d4",
  "gemstone_d6",
  "gemstone_d8",
  "metal_d10",
  "metal_d10b",
  "metal_d12",
  "metal_d20",
  "metal_d4",
  "metal_d6",
  "metal_d8",
  "wood_d10",
  "wood_d10b",
  "wood_d12",
  "wood_d20",
  "wood_d4",
  "wood_d6",
  "wood_d8"
] as const;

export type DiceFrameName = typeof DICE_FRAME_NAMES[number];

export function getDiceFrameName(
  material: "cardboard" | "wood" | "bone" | "metal" | "gemstone",
  size: "d4" | "d6" | "d8" | "d10" | "d10b" | "d12" | "d20"
): DiceFrameName {
  return `${material}_${size}` as DiceFrameName;
}
