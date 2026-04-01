import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { markDebugSceneReady } from "../debug/debugHooks";
import { isDevPanelEnabled } from "../debug/devFlags";
import { apiClient } from "../services/apiClient";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";
import HomeNavigationPanel from "../components/navigation/HomeNavigationPanel";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";

const HOME_PANEL_TITLE_HEIGHT = 56;

export default class HomeScene extends Phaser.Scene {
  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this);
    const layout = getPageLayout(this);
    mountBottomCommandStrip(this);

    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Camp Actions",
      bodyColor: 0x344046,
    });
    actionsFrame.setDepth(-700);

    const contentArea: LayoutRect = {
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
    };

    this.renderCampIntro(contentArea);
    this.renderDevPanelButton(layout);
    this.renderShopButton(layout);
    void this.renderDynamicRunArea(contentArea);
  }

  private renderCampIntro(contentArea: LayoutRect): void {
    this.add
      .rectangle(contentArea.x + 18, contentArea.y + 84, contentArea.width - 36, 86, 0x121a1f, 0.82)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xcaa860, 0.18);
    this.add
      .text(contentArea.x + 24, contentArea.y + 86, "CAMP OVERVIEW", {
        ...TEXT_BODY,
        fontSize: "18px",
        color: "#f0d38a",
      })
      .setOrigin(0, 0);

    this.add
      .text(contentArea.x + 24, contentArea.y + 112, "Choose the next run, then use the side actions to prep between expeditions.", {
        ...TEXT_HEADER,
        fontSize: "20px",
        color: "#f0f4f5",
        wordWrap: { width: contentArea.width - 80 },
      })
      .setOrigin(0, 0);
  }

  private renderDevPanelButton(layout: ReturnType<typeof getPageLayout>): void {
    if (!isDevPanelEnabled()) {
      return;
    }

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 278,
      label: "Dev Panel",
      onClick: () => {
        this.scene.start("DevPanelScene");
      },
    });
  }

  private renderShopButton(layout: ReturnType<typeof getPageLayout>): void {
    this.add
      .text(layout.buttons.x + 24, layout.buttons.y + 90, "Spend soft currency on early dice, starter recruits, and the daily deal before the next run.", {
        ...TEXT_BODY,
        fontSize: "19px",
        color: "#eef3f4",
        stroke: "#11181d",
        strokeThickness: 1,
        lineSpacing: 6,
        wordWrap: { width: layout.buttons.width - 48 },
      })
      .setOrigin(0, 0);

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 190,
      label: "Shop",
      onClick: () => {
        this.scene.start("ShopScene");
      },
    });
  }

  private async renderDynamicRunArea(contentArea: LayoutRect): Promise<void> {
    let hasActiveRun = false;
    try {
      const profile = await apiClient.getProfile({ force: true, allowStaleOnError: true });
      hasActiveRun = profile.ok && profile.data.active_run !== null;
    } catch {
      hasActiveRun = false;
    }

    const bodyImageKey = hasActiveRun ? "ux_continue_run" : "ux_start_run";
    const areaRect = this.resolveRunPanelArea(contentArea, "ux_start_run");
    new HomeNavigationPanel({
      scene: this,
      areaRect,
      title: hasActiveRun ? "Continue Run" : "Start Run",
      bodyColor: 0x23272a,
      targetSceneKey: hasActiveRun ? "MapExplorationScene" : "RegionSelectScene",
      bodyImageKey,
    });
    markDebugSceneReady(this, { hasActiveRun });
  }

  private resolveRunPanelArea(contentArea: LayoutRect, bodyImageKey: string): LayoutRect {
    const fallbackWidth = contentArea.width;
    const fallbackHeight = Math.max(0, contentArea.height - 178);
    if (!this.textures.exists(bodyImageKey)) {
      return { ...contentArea, y: contentArea.y + 178, width: fallbackWidth, height: fallbackHeight };
    }

    const source = this.textures.get(bodyImageKey).getSourceImage() as { width?: number; height?: number } | undefined;
    const naturalBodyWidth = source?.width ?? fallbackWidth;
    const naturalBodyHeight = source?.height ?? Math.max(0, fallbackHeight - HOME_PANEL_TITLE_HEIGHT);

    const panelWidth = Math.min(naturalBodyWidth, contentArea.width);
    const panelHeight = Math.min(naturalBodyHeight + HOME_PANEL_TITLE_HEIGHT, contentArea.height);

    return {
      x: contentArea.x + Math.floor((contentArea.width - panelWidth) / 2),
      y: contentArea.y + 178 + Math.max(0, Math.floor((fallbackHeight - panelHeight) / 2)),
      width: panelWidth,
      height: panelHeight,
    };
  }
}
