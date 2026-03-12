import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import ToastMessage from "../components/feedback/ToastMessage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import RegionSelectionPanel from "../components/navigation/RegionSelectionPanel";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { apiClient } from "../services/apiClient";


export default class RegionSelectScene extends Phaser.Scene {
  private toast?: ToastMessage;

  constructor() {
    super({ key: "RegionSelectScene" });
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Choose Region",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);

    const intelFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Region Intel",
      bodyColor: 0x4f5a65,
    });
    intelFrame.setDepth(-800);

    this.add.text(layout.buttons.x + 24, layout.buttons.y + 90, "Select a region to start a run.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "24px",
      color: "#111111",
      wordWrap: { width: layout.buttons.width - 48 },
    });

    const gap = 24;
    const panelWidth = Math.floor((layout.content.width - gap * 3) / 2);
    const panelHeight = Math.max(200, layout.content.height - 200);
    const panelY = layout.content.y + 90;

    new RegionSelectionPanel({
      scene: this,
      x: layout.content.x + gap,
      y: panelY,
      width: panelWidth,
      height: panelHeight,
      regionId: "mountain",
      label: "Mountain",
      textureKey: "column_mountain",
      locked: false,
      onSelect: async () => this.startRun("mountain"),
      onLockedSelect: () => this.showFeedback("Region locked."),
    });

    new RegionSelectionPanel({
      scene: this,
      x: layout.content.x + gap * 2 + panelWidth,
      y: panelY,
      width: panelWidth,
      height: panelHeight,
      regionId: "swamp",
      label: "Swamp",
      textureKey: "column_swamp",
      locked: false,
      onSelect: async () => this.startRun("swamp"),
      onLockedSelect: () => this.showFeedback("Region locked."),
    });

    markDebugSceneReady(this);
  }

  private async startRun(regionId: "mountain" | "swamp"): Promise<void> {
    try {
      const res = await apiClient.createRun(regionId);
      if (!res.ok) {
        this.showFeedback(`Cannot start run: ${res.error.message}`);
        return;
      }
      this.scene.start("MapExplorationScene");
    } catch (error) {
      const fallback = "Cannot start run right now. Please retry.";
      this.showFeedback(error instanceof Error ? error.message : fallback);
    }
  }

  private showFeedback(message: string): void {
    this.toast?.destroy();
    this.toast = new ToastMessage({
      scene: this,
      x: 24,
      y: this.scale.height - 84,
      message,
      severity: "warning",
      durationMs: 2600,
    });
  }
}







