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
    const safe = layout.padding;
    const welcomeHeight = 128;
    // Missing dedicated home hero asset: use the requested color block until the art pass lands.
    this.add.rectangle(safe.x, safe.y, safe.width, welcomeHeight, WELCOME_COLOR, 1).setOrigin(0, 0);

    const textInset = 24;
    const columnGap = 32;
    const columnWidth = Math.floor((safe.width - textInset * 2 - columnGap) / 2);

    this.add.text(safe.x + textInset, safe.y + 22, "Welcome back to camp. Pick the next route, then use the side panels to prep your squad between runs.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "25px",
      color: "#17341d",
      wordWrap: { width: columnWidth },
      lineSpacing: 7,
    }).setOrigin(0, 0);

    this.add.text(
      safe.x + textInset + columnWidth + columnGap,
      safe.y + 22,
      "Warband and Inventory are your management spaces, and Shop is where you spend currency between expeditions.",
      {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "25px",
        color: "#17341d",
        wordWrap: { width: columnWidth },
        lineSpacing: 7,
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
      onClick: () => this.scene.start(hasActiveRun ? "MapExplorationScene" : "RegionSelectScene"),
    });
    this.createPanel({
      rect: panels.warband,
      color: WARBAND_COLOR,
      tooltip: "Warband",
      onClick: () => this.scene.start("WarbandManagementScene"),
    });
    this.createPanel({
      rect: panels.shop,
      color: SHOP_COLOR,
      tooltip: "Shop",
      onClick: () => this.scene.start("ShopScene"),
    });
    this.createPanel({
      rect: panels.inventory,
      color: INVENTORY_COLOR,
      tooltip: "Inventory",
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
    // Missing destination card art: use the requested placeholder block until the home asset set is generated.
    const disabledAlpha = 1;
    const panel = this.add.rectangle(config.rect.x, config.rect.y, config.rect.width, config.rect.height, config.color, config.enabled === false ? disabledAlpha : 1).setOrigin(0, 0);
    const hitZone = this.add.zone(config.rect.x, config.rect.y, config.rect.width, config.rect.height)
      .setOrigin(0, 0)
      .setInteractive({ useHandCursor: config.enabled !== false });
    const tooltip = this.add.text(config.rect.x + config.rect.width / 2, config.rect.y + config.rect.height / 2, config.tooltip, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: "#ffffff",
      backgroundColor: "#22131b",
      padding: { left: 8, right: 8, top: 6, bottom: 6 },
    }).setOrigin(0.5, 0.5).setVisible(false);

    hitZone.on("pointerover", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.84);
      tooltip.setVisible(true);
    });
    hitZone.on("pointerout", () => {
      panel.setAlpha(config.enabled === false ? disabledAlpha : 1);
      tooltip.setVisible(false);
    });
    hitZone.on("pointerdown", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.7);
    });
    hitZone.on("pointerup", () => {
      if (config.enabled === false) {
        return;
      }
      panel.setAlpha(0.84);
      config.onClick();
    });
  }
}
