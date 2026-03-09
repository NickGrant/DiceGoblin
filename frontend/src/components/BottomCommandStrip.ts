import type Phaser from "phaser";
import { TEXT_BODY } from "../const/Text";
import IconButton from "./IconButton";
import { getPageLayout } from "../layout/pageLayout";
import { apiClient } from "../services/apiClient";
import { RegistrySession } from "../state/RegistrySession";

const ICON_SIZE = 40;
const BAR_TARGET_WIDTH = 960;
const MIN_BAR_WIDTH = 320;
const BAR_HEIGHT = 96;
const ENERGY_LABEL_FALLBACK = "ENERGY: -- / --";

export function mountBottomCommandStrip(scene: Phaser.Scene): void {
  const addApi = (scene as unknown as {
    add?: { image?: unknown; text?: unknown; zone?: unknown; rectangle?: unknown; existing?: unknown };
  }).add;
  if (!addApi) return;
  if (typeof addApi.image !== "function") return;
  if (typeof addApi.text !== "function") return;
  if (typeof addApi.zone !== "function") return;
  if (typeof addApi.rectangle !== "function") return;
  if (typeof addApi.existing !== "function") return;
  new BottomCommandStrip(scene);
}

export default class BottomCommandStrip {
  private readonly scene: Phaser.Scene;
  private readonly barBg: Phaser.GameObjects.Image | Phaser.GameObjects.Rectangle;
  private readonly homeButton: IconButton;
  private readonly warbandButton: IconButton;
  private readonly inventoryButton: IconButton;
  private readonly logoutButton: IconButton;
  private readonly playerNameText: Phaser.GameObjects.Text;
  private readonly energyText: Phaser.GameObjects.Text;
  private barWidth = BAR_TARGET_WIDTH;
  private centerX = 0;
  private centerY = 0;

  constructor(scene: Phaser.Scene) {
    this.scene = scene;

    const hasBaseBar = scene.textures?.exists?.("base_bar") ?? false;
    this.barBg = scene.add.image(0, 0, hasBaseBar ? "base_bar" : "manifest_strip").setOrigin(0.5, 0.5);

    this.homeButton = new IconButton({
      scene,
      iconKey: "icon_home",
      tooltipText: "Home",
      iconSize: ICON_SIZE,
      onClick: () => scene.scene.start("HomeScene"),
    });
    this.warbandButton = new IconButton({
      scene,
      iconKey: "icon_warband",
      tooltipText: "Warband",
      iconSize: ICON_SIZE,
      onClick: () => scene.scene.start("WarbandManagementScene"),
    });
    this.inventoryButton = new IconButton({
      scene,
      iconKey: "icon_inventory",
      tooltipText: "Dice Inventory",
      iconSize: ICON_SIZE,
      onClick: () => scene.scene.start("DiceInventoryScene"),
    });
    this.energyText = scene.add.text(0, 0, ENERGY_LABEL_FALLBACK, {
      ...TEXT_BODY,
      fontSize: "20px",
      color: "#F3EFE0",
      strokeThickness: 0,
      shadow: undefined,
    }).setOrigin(0.5, 0.5);
    this.logoutButton = new IconButton({
      scene,
      iconKey: "icon_logout",
      tooltipText: "Logout",
      iconSize: ICON_SIZE,
      onClick: () => void this.handleLogout(),
    });
    this.playerNameText = scene.add.text(0, 0, this.resolvePlayerName(), {
      ...TEXT_BODY,
      fontSize: "20px",
      color: "#23272A",
      strokeThickness: 0,
      shadow: undefined,
    }).setOrigin(0, 0.5);

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
    this.homeButton.destroy();
    this.warbandButton.destroy();
    this.inventoryButton.destroy();
    this.logoutButton.destroy();
    this.playerNameText.destroy();
    this.energyText.destroy();
  }

  private setLayerProps(): void {
    const all = [this.barBg, this.energyText, this.playerNameText];

    all.forEach((obj) => {
      obj.setScrollFactor(0);
      obj.setDepth(100000);
    });
    this.homeButton.setScrollFactor(0).setDepth(100000);
    this.warbandButton.setScrollFactor(0).setDepth(100000);
    this.inventoryButton.setScrollFactor(0).setDepth(100000);
    this.logoutButton.setScrollFactor(0).setDepth(100000);
  }

  private reposition(): void {
    const layout = getPageLayout(this.scene);
    const targetWidth = Math.floor(layout.bottomStrip.width * 0.9);
    this.barWidth = Math.max(MIN_BAR_WIDTH, Math.min(BAR_TARGET_WIDTH, targetWidth));
    this.centerX = layout.bottomStrip.x + layout.bottomStrip.width / 2;
    this.centerY = layout.bottomStrip.y + layout.bottomStrip.height / 2;

    const bgObj = this.barBg as unknown as { setDisplaySize?: (w: number, h: number) => void; setSize?: (w: number, h: number) => void };
    if (typeof bgObj.setDisplaySize === "function") bgObj.setDisplaySize(this.barWidth, BAR_HEIGHT);
    else if (typeof bgObj.setSize === "function") bgObj.setSize(this.barWidth, BAR_HEIGHT);
    this.barBg.setPosition(this.centerX, this.centerY);

    this.layoutChildren();
  }

  private layoutChildren(): void {
    const leftEdge = this.centerX - this.barWidth / 2;
    const rightEdge = this.centerX + this.barWidth / 2;
    const y = this.centerY;
    const iconAndNameY = y - 10;
    const energyY = y + 3;

    const leftPanelLeft = leftEdge + this.barWidth * 0.035;
    const leftPanelRight = this.centerX - this.barWidth * 0.185;
    const leftPanelWidth = Math.max(1, leftPanelRight - leftPanelLeft);
    const homeX = leftPanelLeft + leftPanelWidth * 0.14;
    const warbandX = leftPanelLeft + leftPanelWidth * 0.41;
    const inventoryX = leftPanelLeft + leftPanelWidth * 0.68;
    this.homeButton.setPosition(homeX, iconAndNameY);
    this.warbandButton.setPosition(warbandX, iconAndNameY);
    this.inventoryButton.setPosition(inventoryX, iconAndNameY);

    this.energyText.setPosition(this.centerX, energyY);

    const rightPanelLeft = this.centerX + this.barWidth * 0.15;
    const rightPanelRight = rightEdge - this.barWidth * 0.035;
    const logoutX = rightPanelRight - this.barWidth * 0.045;
    this.playerNameText.setPosition(rightPanelLeft + 8, iconAndNameY);
    this.logoutButton.setPosition(logoutX, iconAndNameY);
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
