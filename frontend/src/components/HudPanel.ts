import Phaser from "phaser";
import { TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";
import { RegistryEnergy } from "../state/RegistryEnergy";
import { apiClient } from "../services/apiClient";

export default class HudPanel extends Phaser.GameObjects.Container {
  private nameText: Phaser.GameObjects.Text;

  private energyIcon: Phaser.GameObjects.Image;
  private energyText: Phaser.GameObjects.Text;
  private bar: Phaser.GameObjects.Graphics;

  private energyCurrent = 0;
  private energyMax = 1;

  private bgW = 320;

  private profile;

  constructor(scene: Phaser.Scene) {
    super(scene, 0, 0);
    this.profile = apiClient.getProfile();

    this.width = this.bgW;
    this.height = 128;
    
    // Name
    this.nameText = scene.add.text(0, 32, RegistrySession.displayName(scene.registry).toUpperCase(), TEXT_BODY).setOrigin(0,0);
    this.nameText.setX(this.bgW - this.nameText.width - 32);

    // Energy icon
    this.energyIcon = scene.add.image(this.bgW - 64, 0, "icon_energy").setScale(.5, .5).setOrigin(0,0);

    // Energy bar (simple)
    this.bar = scene.add.graphics();

    // Energy text
    this.energyText = scene.add
      .text(this.bgW - 140, 2, "", {
        fontFamily: `"Roboto Condensed", system-ui, -apple-system, Segoe UI, Roboto, Arial`,
        fontSize: "18px",
        color: "#e8e8ff",
        stroke: "#1A0E0A",
        strokeThickness: 3
      }).setOrigin(0,0);

    // Assemble (order matters)
    this.add([this.nameText, this.energyIcon, this.bar, this.energyText]);

    // Add to scene + HUD behavior
    scene.add.existing(this);
    this.setScrollFactor(0);
    this.setDepth(100000);

    // Anchor top-right + keep anchored on resize
    this.reposition();
    scene.scale.on("resize", this.reposition, this);

    apiClient.getProfile().then((profile) => {
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
    const marginTop = 24;
    const marginRight = 12;

    this.setPosition(this.scene.scale.width - this.bgW + marginRight, marginTop);
  }

  private redrawEnergy(): void {
    this.energyText.setText(`${this.energyCurrent} / ${this.energyMax}`);


    const x = this.bgW - 302;
    const y = 14;

    const width = 160;
    const height = 17;
    const segments = 10;
    const gap = 4;

    const pct = Phaser.Math.Clamp(this.energyCurrent / this.energyMax, 0, 1);
    const filled = Math.round(pct * segments);
    const segW = (width - (segments - 1) * gap) / segments;

    this.bar.clear();

    // Outline + background
    this.bar.lineStyle(2, 0x1a0e0a, 1);
    this.bar.strokeRoundedRect(x, y - height / 2, width, height, 3);

    this.bar.fillStyle(0x2b1b14, 1);
    this.bar.fillRoundedRect(x, y - height / 2, width, height, 3);

    // Segments
    for (let i = 0; i < segments; i++) {
      const sx = x + i * (segW + gap);
      const sy = y - height / 2;

      this.bar.fillStyle(i < filled ? 0xd1a84a : 0x5e1f1b, 1);
      this.bar.fillRoundedRect(sx, sy, segW, height, 2);
    }
  }
}
