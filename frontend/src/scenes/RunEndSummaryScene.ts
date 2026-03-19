import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

const FRAME_BODY_TOP_OFFSET = 74;
const ACTION_BODY_TOP_OFFSET = 72;
const ACTION_BUTTON_WIDTH = 280;

type RunEndSummaryData = {
  status?: "completed" | "failed" | "abandoned" | string;
  rewards?: string[];
  progression?: string[];
  survivors?: string[];
  defeated?: string[];
};

export default class RunEndSummaryScene extends Phaser.Scene {
  private payload: RunEndSummaryData = {};

  constructor() {
    super({ key: "RunEndSummaryScene" });
  }

  init(data: RunEndSummaryData): void {
    this.payload = data ?? {};
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
      title: "Run Summary",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Continue",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    const status = this.payload.status ?? "completed";
    const bodyTop = layout.content.y + FRAME_BODY_TOP_OFFSET;
    const statusLabel = String(status).toUpperCase();
    const outcomeMessage = status === "completed"
      ? "Run complete. Rewards have been granted."
      : status === "failed"
        ? "Run failed. Surviving units and progression have been recorded."
        : "Run ended early. Current progression has been recorded.";
    const statusColor = status === "completed" ? "#a7ffcf" : status === "failed" ? "#ffb2b2" : "#ffd89e";

    this.add.text(layout.content.x + 16, bodyTop, statusLabel, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: statusColor,
    }).setOrigin(0, 0);
    this.add.text(layout.content.x + 16, bodyTop + 24, outcomeMessage, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "12px",
      color: "#dddddd",
      align: "left",
      wordWrap: { width: layout.content.width - 32 },
    }).setOrigin(0, 0);

    const rewards = this.payload.rewards ?? [];
    const progression = this.payload.progression ?? [];
    const survivors = this.payload.survivors ?? [];
    const defeated = this.payload.defeated ?? [];

    const lines = [
      "Rewards:",
      ...(rewards.length > 0 ? rewards : ["- None"]),
      "",
      "XP / Level Progression:",
      ...(progression.length > 0 ? progression : ["- No progression changes recorded"]),
      "",
      "Surviving Units:",
      ...(survivors.length > 0 ? survivors : ["- None"]),
      "",
      "Defeated Units:",
      ...(defeated.length > 0 ? defeated : ["- None"]),
    ];

    const summaryPanelY = bodyTop + 66;
    const summaryPanelHeight = Math.max(120, layout.content.height - FRAME_BODY_TOP_OFFSET - 90);
    const summaryPanel = this.add
      .rectangle(layout.content.x + 12, summaryPanelY, layout.content.width - 24, summaryPanelHeight, 0x14181b, 0.56)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8ea1af, 0.4);
    this.add.text(layout.content.x + 20, summaryPanelY + 12, lines.join("\n"), {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "12px",
      color: "#f5f5f5",
      lineSpacing: 4,
      wordWrap: { width: layout.content.width - 40 },
    });

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2)),
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
      label: "Continue",
      onClick: () => this.scene.start("HomeScene"),
    });
    markDebugSceneReady(this, { status });
  }
}









