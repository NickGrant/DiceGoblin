import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import NodeList from "../components/encounter-map/NodeList";
import { apiClient } from "../services/apiClient";
import type { CurrentRunNode, RunResponse } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

export default class MapExplorationScene extends Phaser.Scene {
  private runEnvelope: RunResponse | null = null;
  private fallbackText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private nodeList?: NodeList;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this, "background_desk");
    new HudPanel(this);
    const layout = getPageLayout(this);
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    new ActionButtonList({
      scene: this,
      x: layout.buttons.x + 10,
      y: layout.buttons.y + 24,
      gapY: 5,
      buttons: [
        {
          label: "Refresh Map",
          onClick: () => void this.loadRunState(),
        },
        {
          label: "Abandon Run",
          onClick: () => void this.confirmAbandonRun(),
        },
      ],
    });

    void this.loadRunState();
  }

  private async loadRunState(): Promise<void> {
    this.clearMessages();
    this.nodeList?.destroy();
    this.nodeList = undefined;

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

      if (!Array.isArray(run.data.map.nodes) || !Array.isArray(run.data.map.edges)) {
        this.showFallback("Run map is syncing. Please retry.");
        return;
      }

      this.runEnvelope = run;

      const layout = getPageLayout(this);
      this.nodeList = new NodeList(
        this,
        0,
        0,
        run.data.run,
        run.data.map.nodes,
        run.data.map.edges,
        {
          scatterRect: new Phaser.Geom.Rectangle(
            layout.content.x + 48,
            layout.content.y + 48,
            Math.max(200, layout.content.width - 96),
            Math.max(180, layout.content.height - 96)
          ),
          nodeSize: 84,
          onNodeClick: (node) => this.handleNodeClick(node),
        }
      );
    } catch {
      this.showFallback("Run data unavailable. Please retry.");
    }
  }

  private async handleNodeClick(node: CurrentRunNode): Promise<void> {
    if (!this.runEnvelope?.ok || this.runEnvelope.data.run === null) return;
    const runId = this.runEnvelope.data.run.run_id;

    if (node.node_type === "rest") {
      this.scene.start("RestManagementScene", { runId, nodeId: node.id });
      return;
    }

    if (node.node_type === "exit") {
      await this.handleExitNodeClick(runId);
      return;
    }

    if (node.node_type === "combat" || node.node_type === "loot" || node.node_type === "boss") {
      await this.handleResolveNodeClick(runId, node.id);
      return;
    }

    this.showFallback(`Node '${node.node_type}' is not supported.`);
  }

  private async handleResolveNodeClick(runId: string, nodeId: string): Promise<void> {
    try {
      const resolveRes = await apiClient.resolveRunNode(runId, nodeId);
      if (!resolveRes.ok) {
        this.showFallback(`Resolve failed: ${resolveRes.error.message}`);
        return;
      }

      const unlocked = resolveRes.data.next.unlocked_node_ids;
      const unlockedMsg = unlocked.length > 0 ? ` Unlocked: ${unlocked.join(", ")}.` : "";
      this.showToast(
        `Node resolved (${resolveRes.data.battle.outcome}).${unlockedMsg}`,
        resolveRes.data.battle.outcome === "victory" ? "#ccffcc" : "#ffd89e"
      );

      const refreshed = await apiClient.getCurrentRun();
      if (refreshed.ok && refreshed.data.run === null) {
        const status = resolveRes.data.battle.outcome === "defeat" ? "failed" : "completed";
        this.scene.start("RunEndSummaryScene", {
          status,
          rewards: [],
          progression: [],
          survivors: [],
          defeated: [],
        });
        return;
      }

      await this.loadRunState();
    } catch {
      this.showFallback("Node resolve unavailable. Please retry.");
    }
  }

  private async handleExitNodeClick(runId: string): Promise<void> {
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

  private async confirmAbandonRun(): Promise<void> {
    if (!this.runEnvelope?.ok || this.runEnvelope.data.run === null) return;
    const confirm = window.confirm("Abandon this run? This will end the current run.");
    if (!confirm) return;

    try {
      const runId = this.runEnvelope.data.run.run_id;
      const res = await apiClient.abandonRun(runId);
      if (!res.ok) {
        this.showFallback(`Abandon failed: ${res.error.message}`);
        return;
      }
      this.scene.start("RunEndSummaryScene", {
        status: res.data.status,
        rewards: [],
        progression: [],
        survivors: [],
        defeated: [],
      });
    } catch {
      this.showFallback("Abandon unavailable. Please retry.");
    }
  }

  private clearMessages(): void {
    this.fallbackText?.destroy();
    this.fallbackText = undefined;
    this.toastText?.destroy();
    this.toastText = undefined;
  }

  private showFallback(message: string): void {
    this.fallbackText?.destroy();
    const layout = getPageLayout(this);
    this.fallbackText = this.add.text(layout.content.x, layout.content.y, message, {
      fontFamily: "monospace",
      fontSize: "16px",
      color: "#f5f5f5",
      align: "left",
      wordWrap: { width: Math.max(320, layout.content.width - 24) },
    }).setOrigin(0, 0);
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 8, layout.content.y + layout.content.height - 24, message, {
      fontFamily: "Arial",
      fontSize: "12px",
      color,
      wordWrap: { width: layout.content.width - 16 },
    });
    this.time.delayedCall(2800, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
