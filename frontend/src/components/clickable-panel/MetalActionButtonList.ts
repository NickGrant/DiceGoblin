import type Phaser from "phaser";
import UnifiedButtonList, { type UnifiedButtonListItem } from "./UnifiedButtonList";

export type MetalActionButtonListItem = Omit<UnifiedButtonListItem, "variant">;

type MetalActionButtonListConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  buttons: MetalActionButtonListItem[];
  gapY?: number;
};

export default class MetalActionButtonList extends UnifiedButtonList {
  constructor(cfg: MetalActionButtonListConfig) {
    super({
      scene: cfg.scene,
      x: cfg.x,
      y: cfg.y,
      gapY: cfg.gapY ?? 5,
      defaultVariant: "metal",
      buttons: cfg.buttons,
    });
  }
}
