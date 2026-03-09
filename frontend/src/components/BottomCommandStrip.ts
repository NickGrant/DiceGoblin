import type Phaser from "phaser";
import { TEXT_BODY } from "../const/Text";
import { apiClient } from "../services/apiClient";
import { RegistrySession } from "../state/RegistrySession";

const ICON_SIZE = 40;
const BAR_TARGET_WIDTH = 960;
const BAR_BOTTOM_PADDING = 10;
const MIN_BAR_WIDTH = 620;
const BAR_HEIGHT = 96;
const ENERGY_LABEL_FALLBACK = "ENERGY: -- / --";

export function mountBottomCommandStrip(scene: Phaser.Scene): void {
  const addApi = (scene as unknown as { add?: { image?: unknown; text?: unknown; zone?: unknown } }).add;
  if (!addApi) return;
  if (typeof addApi.image !== "function") return;
  if (typeof addApi.text !== "function") return;
  if (typeof addApi.zone !== "function") return;
  new BottomCommandStrip(scene);
}

export default class BottomCommandStrip {
  private readonly scene: Phaser.Scene;
  private readonly barBg: Phaser.GameObjects.Image | Phaser.GameObjects.Rectangle;
  private readonly warbandIcon: Phaser.GameObjects.Image;
  private readonly warbandText: Phaser.GameObjects.Text;
  private readonly inventoryIcon: Phaser.GameObjects.Image;
  private readonly inventoryText: Phaser.GameObjects.Text;
  private readonly logoutIcon: Phaser.GameObjects.Image;
  private readonly logoutText: Phaser.GameObjects.Text;
  private readonly playerNameText: Phaser.GameObjects.Text;
  private readonly energyText: Phaser.GameObjects.Text;
  private readonly warbandHit: Phaser.GameObjects.Zone;
  private readonly inventoryHit: Phaser.GameObjects.Zone;
  private readonly logoutHit: Phaser.GameObjects.Zone;
  private barWidth = BAR_TARGET_WIDTH;
  private centerX = 0;
  private centerY = 0;

  constructor(scene: Phaser.Scene) {
    this.scene = scene;

    const hasBaseBar = scene.textures?.exists?.("base_bar") ?? false;
    this.barBg = scene.add.image(0, 0, hasBaseBar ? "base_bar" : "manifest_strip").setOrigin(0.5, 0.5);

    this.warbandIcon = scene.add.image(0, 0, "icon_warband").setDisplaySize(ICON_SIZE, ICON_SIZE).setOrigin(0.5, 0.5);
    this.warbandText = this.createActionText("WARBAND");
    this.inventoryIcon = scene.add.image(0, 0, "icon_inventory").setDisplaySize(ICON_SIZE, ICON_SIZE).setOrigin(0.5, 0.5);
    this.inventoryText = this.createActionText("DICE");
    this.energyText = scene.add.text(0, 0, ENERGY_LABEL_FALLBACK, {
      ...TEXT_BODY,
      fontSize: "20px",
      color: "#F3EFE0",
      strokeThickness: 0,
      shadow: undefined,
    }).setOrigin(0, 0.5);
    this.logoutIcon = scene.add.image(0, 0, "icon_logout").setDisplaySize(ICON_SIZE, ICON_SIZE).setOrigin(0.5, 0.5);
    this.logoutText = this.createActionText("LOGOUT");
    this.playerNameText = scene.add.text(0, 0, this.resolvePlayerName(), {
      ...TEXT_BODY,
      fontSize: "20px",
      color: "#F3EFE0",
      strokeThickness: 0,
      shadow: undefined,
    }).setOrigin(1, 0.5);

    this.warbandHit = scene.add.zone(0, 0, 170, 52).setOrigin(0.5, 0.5).setInteractive({ useHandCursor: true });
    this.inventoryHit = scene.add.zone(0, 0, 170, 52).setOrigin(0.5, 0.5).setInteractive({ useHandCursor: true });
    this.logoutHit = scene.add.zone(0, 0, 170, 52).setOrigin(0.5, 0.5).setInteractive({ useHandCursor: true });

    this.bindAction(this.warbandIcon, this.warbandText, this.warbandHit, () => scene.scene.start("WarbandManagementScene"));
    this.bindAction(this.inventoryIcon, this.inventoryText, this.inventoryHit, () => scene.scene.start("DiceInventoryScene"));
    this.bindAction(this.logoutIcon, this.logoutText, this.logoutHit, () => void this.handleLogout());

    this.setLayerProps();
    this.reposition();
    this.syncProfileData();

    scene.scale.on("resize", this.reposition, this);
    scene.events.once("shutdown", () => this.destroy());
    scene.events.once("destroy", () => this.destroy());
  }

  private destroy(): void {
    this.scene.scale.off("resize", this.reposition, this);
    this.barBg.destroy();
    this.warbandIcon.destroy();
    this.warbandText.destroy();
    this.inventoryIcon.destroy();
    this.inventoryText.destroy();
    this.logoutIcon.destroy();
    this.logoutText.destroy();
    this.playerNameText.destroy();
    this.energyText.destroy();
    this.warbandHit.destroy();
    this.inventoryHit.destroy();
    this.logoutHit.destroy();
  }

  private setLayerProps(): void {
    const all = [
      this.barBg,
      this.warbandIcon,
      this.warbandText,
      this.inventoryIcon,
      this.inventoryText,
      this.energyText,
      this.logoutIcon,
      this.logoutText,
      this.playerNameText,
      this.warbandHit,
      this.inventoryHit,
      this.logoutHit,
    ];

    all.forEach((obj) => {
      obj.setScrollFactor(0);
      obj.setDepth(100000);
    });
  }

  private createActionText(label: string): Phaser.GameObjects.Text {
    return this.scene.add.text(0, 0, label, {
      ...TEXT_BODY,
      fontSize: "20px",
      color: "#F3EFE0",
      strokeThickness: 0,
      shadow: undefined,
      letterSpacing: 1,
    }).setOrigin(0, 0.5);
  }

  private bindAction(
    icon: Phaser.GameObjects.Image,
    label: Phaser.GameObjects.Text,
    hit: Phaser.GameObjects.Zone,
    onClick: () => void
  ): void {
    hit.on("pointerover", () => {
      icon.setAlpha(0.9);
      label.setAlpha(0.9);
    });
    hit.on("pointerout", () => {
      icon.setAlpha(1);
      label.setAlpha(1);
    });
    hit.on("pointerup", onClick);
  }

  private reposition(): void {
    const width = this.scene.scale.width;
    const height = this.scene.scale.height;
    this.barWidth = Math.max(MIN_BAR_WIDTH, Math.min(BAR_TARGET_WIDTH, Math.floor(width * 0.82)));
    this.centerX = width / 2;
    this.centerY = height - BAR_BOTTOM_PADDING - BAR_HEIGHT / 2;

    const bgObj = this.barBg as unknown as { setDisplaySize?: (w: number, h: number) => void; setSize?: (w: number, h: number) => void };
    if (typeof bgObj.setDisplaySize === "function") bgObj.setDisplaySize(this.barWidth, BAR_HEIGHT);
    else if (typeof bgObj.setSize === "function") bgObj.setSize(this.barWidth, BAR_HEIGHT);
    this.barBg.setPosition(this.centerX, this.centerY);

    this.layoutChildren();
  }

  private layoutChildren(): void {
    const leftStart = this.centerX - this.barWidth / 2 + 36;
    const rightEnd = this.centerX + this.barWidth / 2 - 28;
    const y = this.centerY;

    this.warbandIcon.setPosition(leftStart, y);
    this.warbandText.setPosition(leftStart + 28, y);
    this.warbandHit.setPosition(leftStart + 56, y);

    const invX = leftStart + 200;
    this.inventoryIcon.setPosition(invX, y);
    this.inventoryText.setPosition(invX + 28, y);
    this.inventoryHit.setPosition(invX + 56, y);

    this.energyText.setPosition(invX + 190, y);

    const logoutIconX = rightEnd - 220;
    this.logoutIcon.setPosition(logoutIconX, y);
    this.logoutText.setPosition(logoutIconX + 28, y);
    this.logoutHit.setPosition(logoutIconX + 56, y);
    this.playerNameText.setPosition(rightEnd, y);
  }

  private async handleLogout(): Promise<void> {
    try {
      await apiClient.logout();
    } catch {
      // Continue local logout flow even if network/logout endpoint fails.
    }
    RegistrySession.clear(this.scene.registry);
    this.scene.scene.start("LandingScene");
  }

  private resolvePlayerName(): string {
    return RegistrySession.displayName(this.scene.registry).toUpperCase();
  }

  private syncProfileData(): void {
    void apiClient.getProfile({ allowStaleOnError: true }).then((profile) => {
      if (!profile.ok) return;
      this.energyText.setText(`ENERGY: ${profile.data.energy.current} / ${profile.data.energy.max}`);
      this.playerNameText.setText(this.resolvePlayerName());
      this.layoutChildren();
    }).catch(() => {
      this.playerNameText.setText(this.resolvePlayerName());
      this.layoutChildren();
    });
  }
}
