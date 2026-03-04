import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import UiButton from "../components/Button";

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
    new BackgroundImage(this, "background_desk");
    new HudPanel(this);
    new HomeButton(this, { x: 64, y: 52 }).setScale(0.5);

    const status = this.payload.status ?? "completed";
    const statusLabel = String(status).toUpperCase();
    const outcomeMessage = status === "completed"
      ? "Run complete. Rewards have been granted."
      : status === "failed"
        ? "Run failed. Surviving units and progression have been recorded."
        : "Run ended early. Current progression has been recorded.";
    const statusColor = status === "completed" ? "#a7ffcf" : status === "failed" ? "#ffb2b2" : "#ffd89e";

    this.add.text(480, 84, "END OF RUN SUMMARY", {
      fontFamily: "Arial",
      fontSize: "22px",
      color: "#ffffff",
    }).setOrigin(0.5);

    this.add.text(480, 118, statusLabel, {
      fontFamily: "Arial",
      fontSize: "16px",
      color: statusColor,
    }).setOrigin(0.5);
    this.add.text(480, 144, outcomeMessage, {
      fontFamily: "Arial",
      fontSize: "12px",
      color: "#dddddd",
      align: "center",
    }).setOrigin(0.5);

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

    this.add.text(160, 182, lines.join("\n"), {
      fontFamily: "monospace",
      fontSize: "13px",
      color: "#f5f5f5",
      wordWrap: { width: 640 },
    });

    new UiButton({
      scene: this,
      x: 860,
      y: 92,
      label: "Continue",
      size: "tiny",
      onClick: () => this.scene.start("HomeScene"),
    });
  }
}
