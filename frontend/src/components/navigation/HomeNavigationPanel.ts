import Phaser from "phaser";
import type { LayoutRect } from "../../layout/pageLayout";
import ContentAreaFrame from "../layout/ContentAreaFrame";

export type HomeNavigationPanelConfig = {
  scene: Phaser.Scene;
  areaRect: LayoutRect;
  title: string;
  targetSceneKey: string;
  bodyImageKey?: string;
  bodyColor?: number;
  enabled?: boolean;
  onSelect?: () => void;
};

export default class HomeNavigationPanel extends Phaser.GameObjects.Container {
  private readonly cfg: HomeNavigationPanelConfig;
  private readonly frame: ContentAreaFrame;
  private readonly hitArea: Phaser.GameObjects.Zone;
  private readonly fallbackLabel?: Phaser.GameObjects.Text;
  private enabled: boolean;

  constructor(cfg: HomeNavigationPanelConfig) {
    super(cfg.scene, cfg.areaRect.x, cfg.areaRect.y);
    this.cfg = cfg;
    this.enabled = cfg.enabled ?? true;

    this.frame = new ContentAreaFrame({
      scene: cfg.scene,
      x: 0,
      y: 0,
      width: cfg.areaRect.width,
      height: cfg.areaRect.height,
      title: cfg.title,
      bodyColor: cfg.bodyColor ?? 0x23272a,
      bodyImageKey: cfg.bodyImageKey,
      useImageEdgeToEdge: true,
    });
    this.add(this.frame);

    if (!cfg.bodyImageKey || !cfg.scene.textures.exists(cfg.bodyImageKey)) {
      this.fallbackLabel = cfg.scene.add.text(24, 84, `Open ${cfg.title}`, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#23272A",
      });
      this.add(this.fallbackLabel);
    }

    this.hitArea = cfg.scene.add
      .zone(cfg.areaRect.width / 2, cfg.areaRect.height / 2, cfg.areaRect.width, cfg.areaRect.height)
      .setOrigin(0.5, 0.5)
      .setInteractive({ useHandCursor: true });

    this.hitArea.on("pointerover", () => {
      if (!this.enabled) return;
      this.setAlpha(0.95);
    });
    this.hitArea.on("pointerout", () => {
      this.setAlpha(this.enabled ? 1 : 0.55);
    });
    this.hitArea.on("pointerdown", () => {
      if (!this.enabled) return;
      this.setAlpha(0.88);
      this.handleSelect();
    });

    this.add(this.hitArea);
    cfg.scene.add.existing(this);
    this.setDepth(10);
    this.setEnabled(this.enabled);
  }

  setEnabled(enabled: boolean): this {
    this.enabled = enabled;
    this.setAlpha(enabled ? 1 : 0.55);
    if (enabled) this.hitArea.setInteractive({ useHandCursor: true });
    else this.hitArea.disableInteractive();
    return this;
  }

  private handleSelect(): void {
    if (this.cfg.onSelect) {
      this.cfg.onSelect();
      return;
    }
    this.cfg.scene.scene.start(this.cfg.targetSceneKey);
  }
}
