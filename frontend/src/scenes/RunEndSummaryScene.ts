import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import ActionButton from "../components/clickable-panel/ActionButton";
import { drawUxDualZones } from "../components/UxZonePanels";
import { getPageLayout } from "../layout/pageLayout";

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
    new HudPanel(this);
    const layout = getPageLayout(this);
    drawUxDualZones(this, {
      leftTitle: "Run Summary",
      rightTitle: "Continue",
      leftColor: 0x0600ff,
      rightColor: 0x00ff72,
    });
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    const status = this.payload.status ?? "completed";
    const statusLabel = String(status).toUpperCase();
    const outcomeMessage = status === "completed"
      ? "Run complete. Rewards have been granted."
      : status === "failed"
        ? "Run failed. Surviving units and progression have been recorded."
        : "Run ended early. Current progression has been recorded.";
    const statusColor = status === "completed" ? "#a7ffcf" : status === "failed" ? "#ffb2b2" : "#ffd89e";

    this.add.text(layout.content.x + 16, layout.content.y - 44, "END OF RUN SUMMARY", {
      fontFamily: "Arial",
      fontSize: "22px",
      color: "#ffffff",
    }).setOrigin(0, 0);

    this.add.text(layout.content.x + 16, layout.content.y - 10, statusLabel, {
      fontFamily: "Arial",
      fontSize: "16px",
      color: statusColor,
    }).setOrigin(0, 0);
    this.add.text(layout.content.x + 16, layout.content.y + 16, outcomeMessage, {
      fontFamily: "Arial",
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

    this.add.text(layout.content.x + 16, layout.content.y + 54, lines.join("\n"), {
      fontFamily: "monospace",
      fontSize: "13px",
      color: "#f5f5f5",
      wordWrap: { width: layout.content.width - 32 },
    });

    new ActionButton({
      scene: this,
      x: layout.buttons.x + 10,
      y: layout.buttons.y + 24,
      label: "Continue",
      onClick: () => this.scene.start("HomeScene"),
    });
  }
}



