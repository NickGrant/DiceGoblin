import Phaser from "phaser";
import ClickablePanel from "../clickable-panel/ClickablePanel";

export type RegionSelectionPanelConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  regionId: string;
  label: string;
  locked: boolean;
  onSelect: (regionId: string) => void | Promise<void>;
  onLockedSelect?: (regionId: string) => void;
  textureKey?: string;
};

export default class RegionSelectionPanel extends Phaser.GameObjects.Container {
  private readonly locked: boolean;
  private readonly regionId: string;
  private readonly onSelect: (regionId: string) => void | Promise<void>;
  private readonly onLockedSelect?: (regionId: string) => void;
  private readonly panel: ClickablePanel;
  private readonly labelText: Phaser.GameObjects.Text;
  private readonly lockText?: Phaser.GameObjects.Text;

  constructor(cfg: RegionSelectionPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.locked = cfg.locked;
    this.regionId = cfg.regionId;
    this.onSelect = cfg.onSelect;
    this.onLockedSelect = cfg.onLockedSelect;

    this.panel = new ClickablePanel(cfg.scene, {
      x: 0,
      y: 0,
      width: cfg.width,
      height: cfg.height,
      textureKey: cfg.textureKey ?? "manifest_strip",
      clickHandler: () => {
        if (this.locked) {
          this.onLockedSelect?.(this.regionId);
          return;
        }
        void this.onSelect(this.regionId);
      },
      deferOverlay: true,
      enabled: true,
    });
    this.panel.setAlpha(this.locked ? 0.65 : 1);

    this.labelText = cfg.scene.add
      .text(16, 16, cfg.label.toUpperCase(), {
        fontFamily: "Arial",
        fontSize: "28px",
        color: "#f0f0f0",
      })
      .setOrigin(0, 0);

    if (this.locked) {
      this.lockText = cfg.scene.add
        .text(16, Math.max(24, cfg.height - 34), "LOCKED", {
          fontFamily: "Arial",
          fontSize: "18px",
          color: "#ffb3b3",
        })
        .setOrigin(0, 0);
    }

    this.add(this.panel);
    this.add(this.labelText);
    if (this.lockText) this.add(this.lockText);
    cfg.scene.add.existing(this);
  }
}
