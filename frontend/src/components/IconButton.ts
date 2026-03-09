import type Phaser from "phaser";

export type IconButtonConfig = {
  scene: Phaser.Scene;
  iconKey: string;
  tooltipText: string;
  onClick: () => void;
  iconSize?: number;
  hitWidth?: number;
  hitHeight?: number;
};

const DEFAULT_ICON_SIZE = 40;
const DEFAULT_HIT_WIDTH = 72;
const DEFAULT_HIT_HEIGHT = 64;

export default class IconButton {
  private readonly icon: Phaser.GameObjects.Image;
  private readonly hit: Phaser.GameObjects.Zone;
  private readonly tooltip: Phaser.GameObjects.Text;
  private x = 0;
  private y = 0;

  constructor(cfg: IconButtonConfig) {
    const iconSize = cfg.iconSize ?? DEFAULT_ICON_SIZE;
    const hitWidth = cfg.hitWidth ?? DEFAULT_HIT_WIDTH;
    const hitHeight = cfg.hitHeight ?? DEFAULT_HIT_HEIGHT;

    this.icon = cfg.scene.add.image(0, 0, cfg.iconKey).setDisplaySize(iconSize, iconSize).setOrigin(0.5, 0.5);
    this.hit = cfg.scene.add.zone(0, 0, hitWidth, hitHeight).setOrigin(0.5, 0.5).setInteractive({ useHandCursor: true });
    this.tooltip = cfg.scene.add.text(0, 0, cfg.tooltipText, {
      fontFamily: "Arial",
      fontSize: "14px",
      color: "#ffffff",
      backgroundColor: "rgba(0,0,0,0.78)",
      padding: { left: 6, right: 6, top: 4, bottom: 4 },
    }).setOrigin(0.5, 1).setVisible(false);

    this.hit.on("pointerover", () => {
      this.icon.setAlpha(0.9);
      this.tooltip.setVisible(true);
    });
    this.hit.on("pointerout", () => {
      this.icon.setAlpha(1);
      this.tooltip.setVisible(false);
    });
    this.hit.on("pointerup", cfg.onClick);
  }

  setPosition(x: number, y: number): this {
    this.x = x;
    this.y = y;
    this.icon.setPosition(x, y);
    this.hit.setPosition(x, y);
    this.tooltip.setPosition(x, y - 26);
    return this;
  }

  setDepth(depth: number): this {
    this.icon.setDepth(depth);
    this.hit.setDepth(depth);
    this.tooltip.setDepth(depth);
    return this;
  }

  setScrollFactor(factor: number): this {
    this.icon.setScrollFactor(factor);
    this.hit.setScrollFactor(factor);
    this.tooltip.setScrollFactor(factor);
    return this;
  }

  destroy(): void {
    this.icon.destroy();
    this.hit.destroy();
    this.tooltip.destroy();
  }
}
