import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { markDebugSceneReady } from "../debug/debugHooks";
import { isDevPanelEnabled } from "../debug/devFlags";
import { apiClient } from "../services/apiClient";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";
import HomeNavigationPanel from "../components/navigation/HomeNavigationPanel";

const HOME_PANEL_TITLE_HEIGHT = 56;

export default class HomeScene extends Phaser.Scene {
  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this);
    const layout = getPageLayout(this);
    mountBottomCommandStrip(this);

    const contentArea: LayoutRect = {
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
    };

    this.renderDevPanelButton(layout);
    this.renderShopButton(layout);
    void this.renderDynamicRunArea(contentArea);
  }

  private renderDevPanelButton(layout: ReturnType<typeof getPageLayout>): void {
    if (!isDevPanelEnabled()) {
      return;
    }

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 74,
      label: "Dev Panel",
      onClick: () => {
        this.scene.start("DevPanelScene");
      },
    });
  }

  private renderShopButton(layout: ReturnType<typeof getPageLayout>): void {
    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 148,
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
    const fallbackHeight = contentArea.height;
    if (!this.textures.exists(bodyImageKey)) {
      return { ...contentArea, width: fallbackWidth, height: fallbackHeight };
    }

    const source = this.textures.get(bodyImageKey).getSourceImage() as { width?: number; height?: number } | undefined;
    const naturalBodyWidth = source?.width ?? fallbackWidth;
    const naturalBodyHeight = source?.height ?? Math.max(0, fallbackHeight - HOME_PANEL_TITLE_HEIGHT);

    const panelWidth = Math.min(naturalBodyWidth, contentArea.width);
    const panelHeight = Math.min(naturalBodyHeight + HOME_PANEL_TITLE_HEIGHT, contentArea.height);

    return {
      x: contentArea.x + Math.floor((contentArea.width - panelWidth) / 2),
      y: contentArea.y + Math.floor((contentArea.height - panelHeight) / 2),
      width: panelWidth,
      height: panelHeight,
    };
  }
}



