import Phaser from "phaser";

export type TooltipPlacement = "left" | "right" | "top" | "bottom";

export type TooltipConfig = {
  scene: Phaser.Scene;
  text: string;
  x: number;
  y: number;
  placement?: TooltipPlacement;
  visible?: boolean;
};

export default class Tooltip extends Phaser.GameObjects.Container {
  private readonly bg: Phaser.GameObjects.Rectangle;
  private readonly label: Phaser.GameObjects.Text;
  private placement: TooltipPlacement;

  constructor(cfg: TooltipConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.label = cfg.scene.add.text(0, 0, cfg.text, {
      fontFamily: "Arial",
      fontSize: "14px",
      color: "#ffffff",
      padding: { left: 6, right: 6, top: 4, bottom: 4 },
    }).setOrigin(0, 0);

    const padding = 2;
    this.bg = cfg.scene.add
      .rectangle(-padding, -padding, this.label.width + padding * 2, this.label.height + padding * 2, 0x000000, 0.78)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xffffff, 0.2);

    this.add([this.bg, this.label]);
    this.placement = cfg.placement ?? "top";
    this.applyPlacement();
    this.setVisible(cfg.visible ?? false);
    cfg.scene.add.existing(this);
  }

  setText(text: string): this {
    this.label.setText(text);
    this.bg.setSize(this.label.width + 4, this.label.height + 4);
    this.applyPlacement();
    return this;
  }

  show(): this {
    this.setVisible(true);
    return this;
  }

  hide(): this {
    this.setVisible(false);
    return this;
  }

  private applyPlacement(): void {
    const w = this.label.width + 4;
    const h = this.label.height + 4;
    let offsetX = 0;
    let offsetY = 0;
    if (this.placement === "left") {
      offsetX = -w;
      offsetY = -h / 2;
    } else if (this.placement === "right") {
      offsetX = 0;
      offsetY = -h / 2;
    } else if (this.placement === "top") {
      offsetX = -w / 2;
      offsetY = -h;
    } else {
      offsetX = -w / 2;
      offsetY = 0;
    }
    this.bg.setPosition(offsetX, offsetY);
    this.label.setPosition(offsetX + 2, offsetY + 2);
  }
}
