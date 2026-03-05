import Phaser from "phaser";
import MetalActionButton from "./MetalActionButton";

type MetalActionButtonConfig = ConstructorParameters<typeof MetalActionButton>[0];

export type MetalActionButtonListItem = Omit<MetalActionButtonConfig, "scene" | "x" | "y">;

type MetalActionButtonListConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  buttons: MetalActionButtonListItem[];
  gapY?: number;
};

export default class MetalActionButtonList extends Phaser.GameObjects.Container {
  private readonly buttons: MetalActionButton[] = [];
  private readonly gapY: number;

  constructor(cfg: MetalActionButtonListConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.gapY = cfg.gapY ?? 5;

    let offsetY = 0;
    for (const buttonCfg of cfg.buttons) {
      const button = new MetalActionButton({
        ...buttonCfg,
        scene: cfg.scene,
        x: 0,
        y: offsetY,
      });
      this.buttons.push(button);
      this.add(button);
      offsetY += 75 + this.gapY;
    }

    this.setSize(300, Math.max(0, offsetY - this.gapY));
    cfg.scene.add.existing(this);
  }

  public getButtons(): MetalActionButton[] {
    return this.buttons;
  }
}
