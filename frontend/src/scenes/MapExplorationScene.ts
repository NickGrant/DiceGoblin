import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import NodeList from "../components/encounter-map/NodeList";
import { apiClient } from "../services/apiClient";
import type { CurrentRunNode, RunResponse } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

export default class MapExplorationScene extends Phaser.Scene {
  _run: RunResponse | null = null;
  _fallbackMessage: string | null = null;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_desk');
    new HudPanel(this);
    const layout = getPageLayout(this);
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });
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
      const layout = getPageLayout(this);
      new NodeList(this, 0, 0, run.data.run, run.data.map.nodes, {
        scatterRect: new Phaser.Geom.Rectangle(
          layout.content.x + 36,
          layout.content.y + 24,
          Math.max(160, layout.content.width - 72),
          Math.max(120, layout.content.height - 48)
        ),
        onNodeClick: (node) => this.handleNodeClick(node),
      });
    } catch {
      this.showFallback("Run data unavailable. Please retry.");
    }
  }

  private handleNodeClick(node: CurrentRunNode): void {
    if (!this._run?.ok || this._run.data.run === null) return;
    if (node.node_type === "rest") {
      this.scene.start("RestManagementScene", {
        runId: this._run.data.run.run_id,
        nodeId: node.id,
      });
      return;
    }
    if (node.node_type === "exit") {
      void this.handleExitNodeClick();
      return;
    }
    this.showFallback(`Node '${node.node_type}' is not wired yet.`);
  }

  private async handleExitNodeClick(): Promise<void> {
    if (!this._run?.ok || this._run.data.run === null) return;
    const runId = this._run.data.run.run_id;
    try {
      const exitRes = await apiClient.exitRun(runId);
      if (!exitRes.ok) {
        this.showFallback(`Exit failed: ${exitRes.error.message}`);
        return;
      }
      this.scene.start("RunEndSummaryScene", {
        status: exitRes.data.status,
        rewards: [],
        progression: [],
        survivors: [],
        defeated: [],
      });
    } catch {
      this.showFallback("Exit unavailable. Please retry.");
    }
  }

  private showFallback(message: string): void {
    this._fallbackMessage = message;
    const add = (this as Phaser.Scene & { add?: { text?: Function } }).add;
    if (!add || typeof add.text !== "function") return;

    const layout = getPageLayout(this);
    const fallback = add.text(layout.content.x, layout.content.y, message, {
      fontFamily: "monospace",
      fontSize: "16px",
      color: "#f5f5f5",
      align: "left",
      wordWrap: { width: Math.max(320, layout.content.width - 24) },
    }).setOrigin(0, 0);
    fallback.setPosition(layout.content.x, layout.content.y);
  }
}


