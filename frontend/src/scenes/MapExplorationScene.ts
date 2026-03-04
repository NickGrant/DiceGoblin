import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import NodeList from "../components/encounter-map/NodeList";
import { apiClient } from "../services/apiClient";
import type { RunResponse } from "../types/ApiResponse";

export default class MapExplorationScene extends Phaser.Scene {
  _run: RunResponse | null = null;
  _fallbackMessage: string | null = null;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_desk');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
    void this.loadRunState();
  }

  private async loadRunState(): Promise<void> {
    try {
      const run = await apiClient.getCurrentRun();
      if (!run.ok) {
        this.showFallback(`Run unavailable: ${run.error.message}`);
        return;
      }

      if (run.data.run === null || run.data.map === null) {
        this.showFallback("No active run. Start one from Regions.");
        return;
      }

      if (!Array.isArray(run.data.map.nodes)) {
        this.showFallback("Run map is syncing. Please retry.");
        return;
      }

      this._run = run;
      new NodeList(this, 0, 0, run.data.run, run.data.map.nodes);
    } catch {
      this.showFallback("Run data unavailable. Please retry.");
    }
  }

  private showFallback(message: string): void {
    this._fallbackMessage = message;
    const add = (this as Phaser.Scene & { add?: { text?: Function } }).add;
    if (!add || typeof add.text !== "function") return;

    add.text(this.cameras.main.centerX, 96, message, {
      fontFamily: "monospace",
      fontSize: "16px",
      color: "#f5f5f5",
      align: "center",
      wordWrap: { width: Math.max(320, this.scale.width - 80) },
    }).setOrigin(0.5, 0);
  }
}
