import type Phaser from "phaser";
import UnifiedButtonList, { type UnifiedButtonListItem } from "./UnifiedButtonList";

export type ActionButtonListItem = Omit<UnifiedButtonListItem, "variant"> & {
  buttonType?: "default" | "accept" | "reject";
};

type ActionButtonListConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  buttons: ActionButtonListItem[];
  gapY?: number;
};

export default class ActionButtonList extends UnifiedButtonList {
  constructor(cfg: ActionButtonListConfig) {
    super({
      scene: cfg.scene,
      x: cfg.x,
      y: cfg.y,
      gapY: cfg.gapY,
      buttons: cfg.buttons.map((button) => ({
        ...button,
        variant: button.buttonType ?? "default",
      })),
      defaultVariant: "default",
    });
  }
}
