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
  onSelect: (regionId: string) => void;
  onActivate?: (regionId: string) => void | Promise<void>;
  onLockedSelect?: (regionId: string) => void;
  onUnavailableSelect?: (regionId: string) => void;
  textureKey?: string;
};

export default class RegionSelectionPanel extends Phaser.GameObjects.Container {
  private locked: boolean;
  private startable = true;
  private readonly regionId: string;
  private readonly onSelect: (regionId: string) => void;
  private readonly onActivate?: (regionId: string) => void | Promise<void>;
  private readonly onLockedSelect?: (regionId: string) => void;
  private readonly onUnavailableSelect?: (regionId: string) => void;
  private readonly panel: ClickablePanel;
  private readonly labelText: Phaser.GameObjects.Text;
  private readonly titleBackground: Phaser.GameObjects.Rectangle;
  private readonly lockOverlay: Phaser.GameObjects.Rectangle;
  private readonly lockText: Phaser.GameObjects.Text;
  private readonly unavailableOverlay: Phaser.GameObjects.Rectangle;
  private readonly unavailableText: Phaser.GameObjects.Text;
  private readonly selectionOutline: Phaser.GameObjects.Rectangle;
  private readonly panelWidth: number;
  private readonly panelHeight: number;
  private lastSelectAt = 0;

  constructor(cfg: RegionSelectionPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.locked = cfg.locked;
    this.panelWidth = cfg.width;
    this.panelHeight = cfg.height;
    this.regionId = cfg.regionId;
    this.onSelect = cfg.onSelect;
    this.onActivate = cfg.onActivate;
    this.onLockedSelect = cfg.onLockedSelect;
    this.onUnavailableSelect = cfg.onUnavailableSelect;

    this.panel = new ClickablePanel(cfg.scene, {
      x: 0,
      y: 0,
      width: cfg.width,
      height: cfg.height,
      textureKey: cfg.textureKey ?? "manifest_strip",
      clickHandler: () => {
        if (this.locked) {
          this.onSelect(this.regionId);
          this.onLockedSelect?.(this.regionId);
          return;
        }
        if (!this.startable) {
          this.onSelect(this.regionId);
          this.onUnavailableSelect?.(this.regionId);
          return;
        }
        const now = Date.now();
        this.onSelect(this.regionId);
        if (now - this.lastSelectAt <= 320) {
          void this.onActivate?.(this.regionId);
        }
        this.lastSelectAt = now;
      },
      deferOverlay: true,
      enabled: true,
    });
    this.panel.setAlpha(1);

    this.titleBackground = cfg.scene.add
      .rectangle(14, 12, cfg.width - 28, 52, 0x000000, 0.45)
      .setOrigin(0, 0);

    this.labelText = cfg.scene.add
      .text(Math.floor(cfg.width / 2), 20, cfg.label.toUpperCase(), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: cfg.width < 260 ? "28px" : "36px",
        color: "#f0f0f0",
        wordWrap: { width: cfg.width - 48 },
        align: "center",
      })
      .setOrigin(0.5, 0);

    this.lockOverlay = cfg.scene.add
      .rectangle(0, 0, cfg.width, cfg.height, 0x0b1114, 0.5)
      .setOrigin(0, 0);

    this.lockText = cfg.scene.add
      .text(Math.floor(cfg.width / 2), cfg.height - 34, "LOCKED", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "24px",
        color: "#ffb3b3",
        stroke: "#240f10",
        strokeThickness: 4,
      })
      .setOrigin(0.5, 1);

    this.unavailableOverlay = cfg.scene.add
      .rectangle(0, cfg.height - 94, cfg.width, 94, 0x1a0f0f, 0.62)
      .setOrigin(0, 0)
      .setVisible(false);

    this.unavailableText = cfg.scene.add
      .text(Math.floor(cfg.width / 2), cfg.height - 26, "NEED MORE ENERGY", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "22px",
        color: "#ffd6a8",
        stroke: "#2f1710",
        strokeThickness: 4,
      })
      .setOrigin(0.5, 1)
      .setVisible(false);

    this.selectionOutline = cfg.scene.add
      .rectangle(0, 0, cfg.width, cfg.height)
      .setOrigin(0, 0)
      .setStrokeStyle(4, 0xe6f3ff, 0.95)
      .setVisible(false);

    this.add(this.panel);
    this.add(this.titleBackground);
    this.add(this.labelText);
    this.add(this.lockOverlay);
    this.add(this.lockText);
    this.add(this.unavailableOverlay);
    this.add(this.unavailableText);
    this.add(this.selectionOutline);
    cfg.scene.add.existing(this);
    this.applyLockedState();
  }

  setLocked(locked: boolean): this {
    this.locked = locked;
    this.applyLockedState();
    return this;
  }

  setSelected(selected: boolean): this {
    this.selectionOutline.setVisible(selected);
    return this;
  }

  setStartable(startable: boolean, unavailableLabel = "NEED MORE ENERGY"): this {
    this.startable = startable;
    this.unavailableText.setText(unavailableLabel.toUpperCase());
    this.applyLockedState();
    return this;
  }

  isLocked(): boolean {
    return this.locked;
  }

  private applyLockedState(): void {
    this.lockOverlay.setVisible(this.locked);
    this.lockText.setVisible(this.locked);
    const showUnavailable = !this.locked && !this.startable;
    this.unavailableOverlay.setVisible(showUnavailable);
    this.unavailableText.setVisible(showUnavailable);
    this.panel.setAlpha(this.locked ? 0.78 : (showUnavailable ? 0.9 : 1));
  }
}
