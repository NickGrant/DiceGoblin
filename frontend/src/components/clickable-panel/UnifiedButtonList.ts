import Phaser from "phaser";
import SharedActionButton, { type SharedButtonVariant } from "./SharedActionButton";

type SharedActionButtonConfig = ConstructorParameters<typeof SharedActionButton>[0];

export type UnifiedButtonListItem = Omit<SharedActionButtonConfig, "scene" | "x" | "y"> & {
  variant?: SharedButtonVariant;
};

type UnifiedButtonListConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  buttons: UnifiedButtonListItem[];
  gapY?: number;
  defaultVariant?: SharedButtonVariant;
};

export default class UnifiedButtonList extends Phaser.GameObjects.Container {
  private readonly buttons: SharedActionButton[] = [];

  constructor(cfg: UnifiedButtonListConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    const gapY = cfg.gapY ?? 16;
    const defaultVariant = cfg.defaultVariant ?? "default";

    let offsetY = 0;
    let maxListWidth = 0;
    for (const buttonCfg of cfg.buttons) {
      const variant = buttonCfg.variant ?? defaultVariant;
      const metrics = SharedActionButton.getVariantMetrics(variant);
      const button = new SharedActionButton({
        ...buttonCfg,
        scene: cfg.scene,
        x: 0,
        y: offsetY,
        variant,
      });
      this.buttons.push(button);
      this.add(button);
      maxListWidth = Math.max(maxListWidth, metrics.listWidth);
      offsetY += metrics.listRowHeight + gapY;
    }

    this.setSize(maxListWidth, Math.max(0, offsetY - gapY));
    cfg.scene.add.existing(this);
  }

  public getButtons(): SharedActionButton[] {
    return this.buttons;
  }
}