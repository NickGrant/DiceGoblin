import Phaser from "phaser";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";
import Tooltip from "./feedback/Tooltip";
import { resolveEnergyTierIcon } from "./hudEnergy";

export default class HudPanel extends Phaser.GameObjects.Container {
  private cornerBg: Phaser.GameObjects.Image;
  private energyIcon: Phaser.GameObjects.Image;
  private energyTooltip: Tooltip;

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

    this.energyTooltip = new Tooltip({
      scene,
      x: iconMidX - (this.iconSize / 2) - 10,
      y: iconMidY,
      text: "",
      placement: "left",
      visible: false,
    });

    // Name display intentionally disabled for HUD simplification.
    // const nameText = scene.add.text(0, 0, RegistrySession.displayName(scene.registry).toUpperCase(), {});

    this.energyIcon.on("pointerover", () => this.energyTooltip.show());
    this.energyIcon.on("pointerout", () => this.energyTooltip.hide());

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
    const iconKey = resolveEnergyTierIcon(this.energyCurrent, this.energyMax);
    this.energyIcon.setTexture(iconKey);
    this.energyTooltip.setText(`Energy: ${this.energyCurrent} / ${this.energyMax}`);
  }
}
