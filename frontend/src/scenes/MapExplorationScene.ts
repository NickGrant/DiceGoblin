import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import NodeList from "../components/encounter-map/NodeList";
import { drawUxDualZones } from "../components/UxZonePanels";
import { apiClient } from "../services/apiClient";
import type { CurrentRunNode, RunResponse } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

export default class MapExplorationScene extends Phaser.Scene {
  private runEnvelope: RunResponse | null = null;
  private fallbackText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private nodeList?: NodeList;
  private abandonDialog?: Phaser.GameObjects.Container;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this);
    new HudPanel(this);
    const layout = getPageLayout(this);
    drawUxDualZones(this, {
      leftTitle: "Start a Run",
      rightTitle: "Run Actions",
      leftColor: 0x0600ff,
      rightColor: 0x00ff72,
    });
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
    if (this.abandonDialog) return;
    this.showAbandonDialog();
  }

  private showAbandonDialog(): void {
    const layout = getPageLayout(this);
    const dialog = this.add.container(0, 0).setDepth(2000);

    const dimmer = this.add.rectangle(0, 0, this.scale.width, this.scale.height, 0x000000, 0.55).setOrigin(0, 0);
    dimmer.setInteractive();

    const panelW = 560;
    const panelH = 220;
    const panelX = layout.content.x + Math.max(0, (layout.content.width - panelW) / 2);
    const panelY = layout.content.y + Math.max(0, (layout.content.height - panelH) / 2);
    const panel = this.add
      .rectangle(panelX, panelY, panelW, panelH, 0x171717, 0.95)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.3);

    const title = this.add.text(panelX + 20, panelY + 18, "ABANDON RUN?", {
      fontFamily: "Arial",
      fontSize: "28px",
      color: "#ffffff",
    });

    const body = this.add.text(panelX + 20, panelY + 70, "This will end the current run immediately.", {
      fontFamily: "Arial",
      fontSize: "16px",
      color: "#dddddd",
      wordWrap: { width: panelW - 40 },
    });

    const cancelBtn = this.add
      .rectangle(panelX + 20, panelY + panelH - 58, 180, 40, 0x2a2a2a, 1)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xffffff, 0.25)
      .setInteractive({ useHandCursor: true });
    const cancelTxt = this.add.text(cancelBtn.x + 90, cancelBtn.y + 20, "Cancel", {
      fontFamily: "Arial",
      fontSize: "18px",
      color: "#f0f0f0",
    }).setOrigin(0.5, 0.5);

    const confirmBtn = this.add
      .rectangle(panelX + panelW - 200, panelY + panelH - 58, 180, 40, 0x6f1f1f, 1)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xffffff, 0.25)
      .setInteractive({ useHandCursor: true });
    const confirmTxt = this.add.text(confirmBtn.x + 90, confirmBtn.y + 20, "Abandon", {
      fontFamily: "Arial",
      fontSize: "18px",
      color: "#fff0f0",
    }).setOrigin(0.5, 0.5);

    const close = (): void => {
      this.abandonDialog?.destroy(true);
      this.abandonDialog = undefined;
    };

    cancelBtn.on("pointerdown", () => close());
    confirmBtn.on("pointerdown", () => {
      close();
      void this.executeAbandonRun();
    });

    dialog.add([dimmer, panel, title, body, cancelBtn, cancelTxt, confirmBtn, confirmTxt]);
    this.abandonDialog = dialog;
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
