import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import NodeList from "../components/encounter-map/NodeList";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { apiClient } from "../services/apiClient";
import type { CurrentRunNode, RunResponse } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import { isNodeResolutionType } from "./nodeResolutionFlow";
import ConfirmationDialog from "../components/feedback/ConfirmationDialog";
import ToastMessage from "../components/feedback/ToastMessage";

export default class MapExplorationScene extends Phaser.Scene {
  private runEnvelope: RunResponse | null = null;
  private fallbackText?: Phaser.GameObjects.Text;
  private toast?: ToastMessage;
  private nodeList?: NodeList;
  private abandonDialog?: ConfirmationDialog;
  private incomingResolutionMessage = "";
  private incomingResolutionColor = "#ffd89e";

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  init(data: { resolutionMessage?: string; resolutionColor?: string } = {}): void {
    this.incomingResolutionMessage = String(data.resolutionMessage ?? "");
    this.incomingResolutionColor = String(data.resolutionColor ?? "#ffd89e");
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);

    const runFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Run Map",
      bodyColor: 0x23272a,
    });
    runFrame.setDepth(-800);

    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Run Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
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

    if (this.incomingResolutionMessage) {
      this.showToast(this.incomingResolutionMessage, this.incomingResolutionColor);
      this.incomingResolutionMessage = "";
    }

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

    const nodeType = String(node.node_type);
    if (isNodeResolutionType(nodeType)) {
      this.scene.start("NodeResolutionScene", {
        runId,
        nodeId: node.id,
        nodeType,
      });
      return;
    }

    this.showFallback(`Node '${node.node_type}' is not supported.`);
  }

  private async confirmAbandonRun(): Promise<void> {
    if (!this.runEnvelope?.ok || this.runEnvelope.data.run === null) return;
    if (this.abandonDialog) return;
    this.showAbandonDialog();
  }

  private showAbandonDialog(): void {
    this.abandonDialog = new ConfirmationDialog({
      scene: this,
      title: "ABANDON RUN?",
      message: "This will end the current run immediately.",
      acceptLabel: "Abandon",
      rejectLabel: "Cancel",
      onReject: () => {
        this.abandonDialog = undefined;
      },
      onAccept: async () => {
        this.abandonDialog?.close();
        this.abandonDialog = undefined;
        await this.executeAbandonRun();
      },
    });
  }

  private async executeAbandonRun(): Promise<void> {
    try {
      if (!this.runEnvelope?.ok || this.runEnvelope.data.run === null) return;
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
    this.toast?.destroy();
    this.toast = undefined;
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
    this.toast?.destroy();
    const layout = getPageLayout(this);
    const severity = color === "#ffd89e" ? "info" : "warning";
    this.toast = new ToastMessage({
      scene: this,
      x: layout.content.x + 12,
      y: layout.content.y + layout.content.height - 60,
      message,
      severity,
      durationMs: 2800,
    });
  }
}





