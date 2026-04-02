import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

const FRAME_BODY_TOP_OFFSET = 74;
const ACTION_BODY_TOP_OFFSET = 72;
const ACTION_BUTTON_WIDTH = 280;
const CHIP_GAP = 12;

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

    const heroCard = this.add
      .rectangle(layout.content.x + 12, bodyTop, layout.content.width - 24, 74, 0x173136, 0.58)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8ea1af, 0.42);
    this.add.text(layout.content.x + 24, bodyTop + 10, statusLabel, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "24px",
      color: statusColor,
    }).setOrigin(0, 0);
    this.add.text(layout.content.x + 24, bodyTop + 42, outcomeMessage, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#dddddd",
      align: "left",
      wordWrap: { width: layout.content.width - 48 },
    }).setOrigin(0, 0);

    const rewards = this.payload.rewards ?? [];
    const progression = this.payload.progression ?? [];
    const survivors = this.payload.survivors ?? [];
    const defeated = this.payload.defeated ?? [];

    const chips = [
      { label: "Rewards", value: rewards.length, accent: "#8bdfe0" },
      { label: "Progression", value: progression.length, accent: "#99e09c" },
      { label: "Survivors", value: survivors.length, accent: "#00e015" },
      { label: "Defeated", value: defeated.length, accent: "#ffb2b2" },
    ];
    const chipWidth = Math.floor((layout.content.width - 24 - CHIP_GAP * (chips.length - 1)) / chips.length);
    chips.forEach((chip, index) => {
      const chipX = layout.content.x + 12 + index * (chipWidth + CHIP_GAP);
      const chipY = bodyTop + 88;
      this.add
        .rectangle(chipX, chipY, chipWidth, 58, 0x14181b, 0.66)
        .setOrigin(0, 0)
        .setStrokeStyle(1, Phaser.Display.Color.HexStringToColor(chip.accent).color, 0.5);
      this.add.text(chipX + 12, chipY + 9, chip.label.toUpperCase(), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: chip.accent,
      }).setOrigin(0, 0);
      this.add.text(chipX + 12, chipY + 28, String(chip.value), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#f5f5f5",
      }).setOrigin(0, 0);
    });

    const leftLines = [
      "Rewards",
      ...(rewards.length > 0 ? rewards : ["- None"]),
      "",
      "XP / Level Progression",
      ...(progression.length > 0 ? progression : ["- No progression changes recorded"]),
    ];
    const rightLines = [
      "Surviving Units",
      ...(survivors.length > 0 ? survivors : ["- None"]),
      "",
      "Defeated Units",
      ...(defeated.length > 0 ? defeated : ["- None"]),
    ];

    const summaryPanelY = bodyTop + 158;
    const summaryPanelHeight = Math.max(120, layout.content.height - FRAME_BODY_TOP_OFFSET - 182);
    const gap = 16;
    const panelWidth = Math.floor((layout.content.width - 24 - gap) / 2);
    const leftPanel = this.add
      .rectangle(layout.content.x + 12, summaryPanelY, panelWidth, summaryPanelHeight, 0x14181b, 0.56)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8ea1af, 0.4);
    const rightPanel = this.add
      .rectangle(layout.content.x + 12 + panelWidth + gap, summaryPanelY, panelWidth, summaryPanelHeight, 0x14181b, 0.56)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8ea1af, 0.4);
    this.add.text(layout.content.x + 24, summaryPanelY - 24, "Rewards and Progression", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0d38a",
    }).setOrigin(0, 0);
    this.add.text(layout.content.x + 24 + panelWidth + gap, summaryPanelY - 24, "Warband Outcome", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0d38a",
    }).setOrigin(0, 0);
    this.add.text(layout.content.x + 24, summaryPanelY + 14, leftLines.join("\n"), {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#f5f5f5",
      lineSpacing: 5,
      wordWrap: { width: panelWidth - 24 },
    });
    this.add.text(layout.content.x + 24 + panelWidth + gap, summaryPanelY + 14, rightLines.join("\n"), {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#f5f5f5",
      lineSpacing: 5,
      wordWrap: { width: panelWidth - 24 },
    });

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2)),
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
      label: "Continue",
      onClick: () => this.scene.start("HomeScene"),
    });
    void heroCard;
    void leftPanel;
    void rightPanel;
    markDebugSceneReady(this, { status });
  }
}









