import Phaser from "phaser";
import { TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";

export default class HudPanel extends Phaser.GameObjects.Container {
  private nameText: Phaser.GameObjects.Text;

  private energyIcon: Phaser.GameObjects.Image;
  private energyText: Phaser.GameObjects.Text;
  private bar: Phaser.GameObjects.Graphics;

  private energyCurrent = 0;
  private energyMax = 1;

  private bgW = 320;
  private readonly panelHeight = 100;
  private readonly iconSize = 75;
  private readonly rightPad = 8;
  private readonly rowHeight = 37.5;
  private readonly rightAreaX = this.iconSize + this.rightPad;
  private readonly rightAreaW = this.bgW - this.rightAreaX;

  constructor(scene: Phaser.Scene) {
    super(scene, 0, 0);

    this.width = this.bgW;
    this.height = this.panelHeight;

    // Left icon column: fixed 100x100
    this.energyIcon = scene.add.image(0, 0, "icon_energy").setDisplaySize(this.iconSize, this.iconSize).setOrigin(0, 0);

    // Top row: energy bars + current/max text
    this.bar = scene.add.graphics();

    // Bottom row: left-aligned player name
    this.nameText = scene.add
      .text(this.rightAreaX, this.rowHeight + 2, RegistrySession.displayName(scene.registry).toUpperCase(), {
        ...TEXT_BODY,
        wordWrap: { width: this.rightAreaW - 8 },
      })
      .setOrigin(0, 0);

    this.energyText = scene.add
      .text(this.rightAreaX, 12, "", {
        fontFamily: `"Roboto Condensed", system-ui, -apple-system, Segoe UI, Roboto, Arial`,
        fontSize: "18px",
        color: "#e8e8ff",
        stroke: "#1A0E0A",
        strokeThickness: 3
      }).setOrigin(0, 0);

    // Assemble (order matters)
    this.add([this.energyIcon, this.bar, this.energyText, this.nameText]);

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
      this.redrawEnergy();
    });
  }

  override destroy(fromScene?: boolean): void {
    this.scene.scale.off("resize", this.reposition, this);
    super.destroy(fromScene);
  }

  setUserName(name: string): void {
    this.nameText.setText(name.toUpperCase());
  }

  setEnergy(current: number, max?: number): void {
    this.energyCurrent = Math.max(0, current);
    if (typeof max === "number") this.energyMax = Math.max(1, max);
    this.redrawEnergy();
  }

  private reposition(): void {
    const layout = getPageLayout(this.scene);
    const x = layout.hud.x + Math.max(0, layout.hud.width - this.bgW);
    const y = layout.hud.y;
    this.setPosition(x, y);
  }

  private redrawEnergy(): void {
    this.energyText.setText(`${this.energyCurrent} / ${this.energyMax}`);
    const barX = this.rightAreaX;
    const barY = 12;
    const barW = 150;
    const barH = 22;
    const segments = 10;
    const gap = 4;

    const pct = Phaser.Math.Clamp(this.energyCurrent / this.energyMax, 0, 1);
    const filled = Math.round(pct * segments);
    const segW = (barW - (segments - 1) * gap) / segments;

    this.bar.clear();

    // Outline + background
    this.bar.lineStyle(2, 0x1a0e0a, 1);
    this.bar.strokeRoundedRect(barX, barY, barW, barH, 3);

    this.bar.fillStyle(0x2b1b14, 1);
    this.bar.fillRoundedRect(barX, barY, barW, barH, 3);

    // Segments
    for (let i = 0; i < segments; i++) {
      const sx = barX + i * (segW + gap);
      const sy = barY;

      this.bar.fillStyle(i < filled ? 0xd1a84a : 0x5e1f1b, 1);
      this.bar.fillRoundedRect(sx, sy, segW, barH, 2);
    }

    this.energyText.setX(barX + barW + 8);
    this.energyText.setY(barY + 1);
  }
}

