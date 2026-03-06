import Phaser from "phaser";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";

export default class HudPanel extends Phaser.GameObjects.Container {
  private cornerBg: Phaser.GameObjects.Image;
  private energyIcon: Phaser.GameObjects.Image;
  private energyTooltip: Phaser.GameObjects.Text;

  private energyCurrent = 0;
  private energyMax = 1;

  private readonly bgW = 300;
  private readonly panelHeight = 218;
  private readonly iconSize = 72;

  constructor(scene: Phaser.Scene) {
    super(scene, 0, 0);

    this.width = this.bgW;
    this.height = this.panelHeight;

    this.cornerBg = scene.add
      .image(0, 0, "ux_corner_right")
      .setOrigin(0, 0)
      .setDisplaySize(this.bgW, this.panelHeight);

    const iconMidX = 226;
    const iconMidY = 72;

    this.energyIcon = scene.add
      .image(iconMidX, iconMidY, "icon_energy")
      .setDisplaySize(this.iconSize, this.iconSize)
      .setOrigin(0.5, 0.5)
      .setInteractive();

    this.energyTooltip = scene.add
      .text(iconMidX - (this.iconSize / 2) - 10, iconMidY, "", {
        fontFamily: "Arial",
        fontSize: "14px",
        color: "#ffffff",
        backgroundColor: "rgba(0,0,0,0.7)",
        padding: { left: 6, right: 6, top: 4, bottom: 4 },
      })
      .setOrigin(1, 0.5)
      .setVisible(false);

    // Name display intentionally disabled for HUD simplification.
    // const nameText = scene.add.text(0, 0, RegistrySession.displayName(scene.registry).toUpperCase(), {});

    this.energyIcon.on("pointerover", () => {
      this.energyTooltip.setVisible(true);
    });
    this.energyIcon.on("pointerout", () => {
      this.energyTooltip.setVisible(false);
    });

    this.add([this.cornerBg, this.energyIcon, this.energyTooltip]);

    // Add to scene + HUD behavior
    scene.add.existing(this);
    this.setScrollFactor(0);
    this.setDepth(100000);

    // Anchor top-right + keep anchored on resize
    this.reposition();
    scene.scale.on("resize", this.reposition, this);

    apiClient.getProfile().then((profile) => {
      if (!profile.ok) return;
      this.energyCurrent = profile.data.energy.current;
      this.energyMax = profile.data.energy.max;
      this.updateEnergyDisplay();
    });
  }

  override destroy(fromScene?: boolean): void {
    this.scene.scale.off("resize", this.reposition, this);
    super.destroy(fromScene);
  }

  setUserName(_name: string): void {}

  setEnergy(current: number, max?: number): void {
    this.energyCurrent = Math.max(0, current);
    if (typeof max === "number") this.energyMax = Math.max(1, max);
    this.updateEnergyDisplay();
  }

  private reposition(): void {
    const layout = getPageLayout(this.scene);
    const x = layout.hud.x + Math.max(0, layout.hud.width - this.bgW);
    const y = layout.hud.y;
    this.setPosition(x, y);
  }

  private updateEnergyDisplay(): void {
    const pct = Phaser.Math.Clamp(this.energyCurrent / this.energyMax, 0, 1) * 100;
    const iconKey = this.getEnergyIconKey(pct);
    this.energyIcon.setTexture(iconKey);
    this.energyTooltip.setText(`Energy: ${this.energyCurrent} / ${this.energyMax}`);
  }

  private getEnergyIconKey(pct: number): string {
    if (pct >= 100) return "icon_energy";
    if (pct >= 75) return "icon_energy_75";
    if (pct >= 50) return "icon_energy_50";
    if (pct >= 25) return "icon_energy_25";
    return "icon_energy_0";
  }
}
