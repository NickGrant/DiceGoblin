import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeNavArea from "../components/HomeNavArea";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import { apiClient } from "../services/apiClient";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";

const AREA_GAP = 12;

export default class HomeScene extends Phaser.Scene {
  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this);
    const layout = getPageLayout(this);

    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    new HudPanel(this);

    const leftArea: LayoutRect = {
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
    };

    const rightTopHeight = Math.floor((layout.buttons.height - AREA_GAP) / 2);
    const rightTopArea: LayoutRect = {
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: rightTopHeight,
    };

    const rightBottomArea: LayoutRect = {
      x: layout.buttons.x,
      y: layout.buttons.y + rightTopHeight + AREA_GAP,
      width: layout.buttons.width,
      height: Math.max(0, layout.buttons.height - rightTopHeight - AREA_GAP),
    };

    void this.renderDynamicRunArea(leftArea);
    new HomeNavArea({
      scene: this,
      area: rightTopArea,
      title: "Manage Warband",
      bodyColor: 0x00f6ff,
      targetSceneKey: "WarbandManagementScene",
    });
    new HomeNavArea({
      scene: this,
      area: rightBottomArea,
      title: "Manage Inventory",
      bodyColor: 0x00ff72,
      targetSceneKey: "DiceInventoryScene",
    });
  }

  private async renderDynamicRunArea(leftArea: LayoutRect): Promise<void> {
    let hasActiveRun = false;
    try {
      const profile = await apiClient.getProfile({ allowStaleOnError: true });
      hasActiveRun = profile.ok && profile.data.active_run !== null;
    } catch {
      hasActiveRun = false;
    }

    new HomeNavArea({
      scene: this,
      area: leftArea,
      title: hasActiveRun ? "Continue Run" : "Start Run",
      bodyColor: 0x0600ff,
      targetSceneKey: hasActiveRun ? "MapExplorationScene" : "RegionSelectScene",
      bodyImageKey: hasActiveRun ? "ux_continue_run" : "ux_start_run",
    });
  }
}
