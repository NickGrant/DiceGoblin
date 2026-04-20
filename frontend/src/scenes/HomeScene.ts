import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import { markDebugSceneReady } from "../debug/debugHooks";
import { isDevPanelEnabled } from "../debug/devFlags";
import { apiClient } from "../services/apiClient";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";

const PANEL_GAP = 10;
const WELCOME_COLOR = 0x99e09c;
const START_RUN_COLOR = 0x8bdfe0;
const WARBAND_COLOR = 0x02e0c8;
const SHOP_COLOR = 0xc903e0;
const INVENTORY_COLOR = 0x3a00e0;
const DEV_COLOR = 0x678387;

type HomePanelConfig = {
  rect: LayoutRect;
  color: number;
  tooltip: string;
  enabled?: boolean;
  textureKey?: string;
  onClick: () => void;
};

export default class HomeScene extends Phaser.Scene {
  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);

    this.renderWelcomeBand();
    void this.renderNavigationPanels();
  }

  private renderWelcomeBand(): void {
    const layout = getPageLayout(this);
    const pad = layout.padding.x;
    const safe = layout.padding;
    const welcomeHeight = 128;
    const hasBannerTexture = typeof this.textures?.exists === "function" && this.textures.exists("banner_background");
    if (hasBannerTexture) {
      this.add.image(0, safe.y, "banner_background")
        .setOrigin(0, 0)
        .setDisplaySize(this.scale.width, welcomeHeight);
    } else {
      // Missing welcome banner asset: use the requested color block until the art pass lands.
      this.add.rectangle(0, safe.y, this.scale.width, welcomeHeight, WELCOME_COLOR, 1).setOrigin(0, 0);
    }
    this.add.rectangle(0, safe.y, this.scale.width, welcomeHeight, 0x111111, 0.34).setOrigin(0, 0);

    const textInset = 24;
    const columnGap = 32;
    const textBandWidth = this.scale.width - pad * 2;
    const columnWidth = Math.floor((textBandWidth - textInset * 2 - columnGap) / 2);
    const textStyle = {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "25px",
      color: "#f8f1de",
      stroke: "#121212",
      strokeThickness: 4,
      shadow: { color: "#000000", blur: 2, fill: true, offsetX: 0, offsetY: 2 },
      lineSpacing: 7,
    } as const;

    this.add.text(safe.x + textInset, safe.y + 18, "Welcome Back to HQ", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "30px",
      color: "#f8f1de",
      stroke: "#121212",
      strokeThickness: 5,
      shadow: { color: "#000000", blur: 2, fill: true, offsetX: 0, offsetY: 2 },
      wordWrap: { width: columnWidth },
    }).setOrigin(0, 0);

    this.add.text(safe.x + textInset, safe.y + 58, "Prep your squad, then start a run and go raid.", {
      ...textStyle,
      wordWrap: { width: columnWidth },
    }).setOrigin(0, 0);

    this.add.text(
      safe.x + textInset + columnWidth + columnGap,
      safe.y + 22,
      "Warband and Inventory are your management spaces, and Shop is where you spend teeth between expeditions.",
      {
        ...textStyle,
        wordWrap: { width: columnWidth },
      }
    ).setOrigin(0, 0);
  }

  private async renderNavigationPanels(): Promise<void> {
    let hasActiveRun = false;
    try {
      const profile = await apiClient.getProfile({ force: true, allowStaleOnError: true });
      hasActiveRun = profile.ok && profile.data.active_run !== null;
    } catch {
      hasActiveRun = false;
    }

    const panels = this.resolvePanelRects();
    this.createPanel({
      rect: panels.startRun,
      color: START_RUN_COLOR,
      tooltip: hasActiveRun ? "Continue Run" : "Start Run",
      textureKey: hasActiveRun ? "home_panel_continue_run" : "home_panel_start_run",
      onClick: () => this.scene.start(hasActiveRun ? "MapExplorationScene" : "RegionSelectScene"),
    });
    this.createPanel({
      rect: panels.warband,
      color: WARBAND_COLOR,
      tooltip: "Warband",
      textureKey: "home_panel_warband",
      onClick: () => this.scene.start("WarbandManagementScene"),
    });
    this.createPanel({
      rect: panels.shop,
      color: SHOP_COLOR,
      tooltip: "Shop",
      textureKey: "home_panel_shop",
      onClick: () => this.scene.start("ShopScene"),
    });
    this.createPanel({
      rect: panels.inventory,
      color: INVENTORY_COLOR,
      tooltip: "Inventory",
      textureKey: "home_panel_inventory",
      onClick: () => this.scene.start("InventoryScene"),
    });
    this.createPanel({
      rect: panels.dev,
      color: DEV_COLOR,
      tooltip: isDevPanelEnabled() ? "Dev Panel" : "Dev Panel Disabled",
      enabled: isDevPanelEnabled(),
      onClick: () => this.scene.start("DevPanelScene"),
    });

    markDebugSceneReady(this, { hasActiveRun });
  }

  private resolvePanelRects(): Record<"startRun" | "warband" | "shop" | "inventory" | "dev", LayoutRect> {
    const layout = getPageLayout(this);
    const safe = layout.padding;
    const welcomeHeight = 128;
    const rowY = safe.y + welcomeHeight + PANEL_GAP;
    const rowHeight = layout.bottomStrip.y - rowY - PANEL_GAP;
    const leftWidth = Math.floor((safe.width - PANEL_GAP) * 0.495);
    const rightWidth = safe.width - leftWidth - PANEL_GAP;
    const cardHeight = Math.floor((rowHeight - PANEL_GAP) / 2);
    const smallWidth = Math.floor((rightWidth - PANEL_GAP) / 2);
    const rightX = safe.x + leftWidth + PANEL_GAP;
    const bottomY = rowY + cardHeight + PANEL_GAP;

    return {
      startRun: {
        x: safe.x,
        y: rowY,
        width: leftWidth,
        height: rowHeight,
      },
      warband: {
        x: rightX,
        y: rowY,
        width: smallWidth,
        height: cardHeight,
      },
      shop: {
        x: rightX + smallWidth + PANEL_GAP,
        y: rowY,
        width: smallWidth,
        height: cardHeight,
      },
      inventory: {
        x: rightX,
        y: bottomY,
        width: smallWidth,
        height: cardHeight,
      },
      dev: {
        x: rightX + smallWidth + PANEL_GAP,
        y: bottomY,
        width: smallWidth,
        height: cardHeight,
      },
    };
  }

  private createPanel(config: HomePanelConfig): void {
    const disabledAlpha = 1;
    const hasTexture = typeof this.textures?.exists === "function" && !!config.textureKey && this.textures.exists(config.textureKey);
    const panel = hasTexture
      ? this.add.image(config.rect.x, config.rect.y, config.textureKey!)
        .setOrigin(0, 0)
        .setDisplaySize(config.rect.width, config.rect.height)
        .setAlpha(config.enabled === false ? disabledAlpha : 1)
      // Missing destination card art: use the requested placeholder block until the home asset set is generated.
      : this.add.rectangle(config.rect.x, config.rect.y, config.rect.width, config.rect.height, config.color, config.enabled === false ? disabledAlpha : 1).setOrigin(0, 0);
    const hoverOverlay = this.add.rectangle(config.rect.x, config.rect.y, config.rect.width, config.rect.height, 0x000000, 0.38)
      .setOrigin(0, 0)
      .setVisible(false);
    const hitZone = this.add.zone(config.rect.x, config.rect.y, config.rect.width, config.rect.height)
      .setOrigin(0, 0)
      .setInteractive({ useHandCursor: config.enabled !== false });
    const tooltip = this.add.text(config.rect.x + config.rect.width / 2, config.rect.y + config.rect.height / 2, config.tooltip, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: config.rect.width > 400 ? "34px" : "24px",
      color: "#ffffff",
      stroke: "#111111",
      strokeThickness: 4,
      align: "center",
      wordWrap: { width: config.rect.width - 48 },
    }).setOrigin(0.5, 0.5).setVisible(false);

    hitZone.on("pointerover", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.96);
      hoverOverlay.setVisible(true);
      tooltip.setVisible(true);
    });
    hitZone.on("pointerout", () => {
      panel.setAlpha(config.enabled === false ? disabledAlpha : 1);
      hoverOverlay.setVisible(false);
      tooltip.setVisible(false);
    });
    hitZone.on("pointerdown", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.7);
      hoverOverlay.setVisible(true);
    });
    hitZone.on("pointerup", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.96);
      hoverOverlay.setVisible(true);
      config.onClick();
    });
  }
}
