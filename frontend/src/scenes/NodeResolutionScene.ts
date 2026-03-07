import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HudPanel from "../components/HudPanel";
import ActionButton from "../components/clickable-panel/ActionButton";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";
import HomeCornerButton from "../components/navigation/HomeCornerButton";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import {
  deriveSummaryStatus,
  formatUnlockedNodes,
  isNodeResolutionType,
  type NodeResolutionType,
} from "./nodeResolutionFlow";

type NodeResolutionSceneData = {
  runId?: string;
  nodeId?: string;
  nodeType?: string;
};

export default class NodeResolutionScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private nodeType: NodeResolutionType | null = null;
  private hasResolved = false;
  private actionButton?: ActionButton;
  private actionHandler: (() => void) | null = null;

  private statusText?: Phaser.GameObjects.Text;
  private detailText?: Phaser.GameObjects.Text;
  private errorText?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "NodeResolutionScene" });
  }

  init(data: NodeResolutionSceneData): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    const typeValue = String(data?.nodeType ?? "");
    this.nodeType = isNodeResolutionType(typeValue) ? typeValue : null;
  }

  create(): void {
    new BackgroundImage(this);
    new HudPanel(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Resolve Node",
      bodyColor: 0x0600ff,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Node Actions",
      bodyColor: 0x00ff72,
    });
    actionsFrame.setDepth(-800);
    new HomeCornerButton({
      scene: this,
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    this.statusText = this.add
      .text(layout.content.x + 16, layout.content.y + 12, "Resolving node...", {
        fontFamily: "Arial",
        fontSize: "24px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    this.detailText = this.add
      .text(layout.content.x + 16, layout.content.y + 56, "", {
        fontFamily: "monospace",
        fontSize: "14px",
        color: "#e8e8e8",
        wordWrap: { width: Math.max(300, layout.content.width - 32) },
      })
      .setOrigin(0, 0);

    this.errorText = this.add
      .text(layout.content.x + 16, layout.content.y + layout.content.height - 28, "", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#ffb3b3",
        wordWrap: { width: Math.max(300, layout.content.width - 32) },
      })
      .setOrigin(0, 0);

    this.actionButton = new ActionButton({
      scene: this,
      x: layout.buttons.x + 10,
      y: layout.buttons.y + 24,
      label: "Resolving...",
      enabled: false,
      onClick: () => this.actionHandler?.(),
    });

    void this.resolveNode();
  }

  private async resolveNode(): Promise<void> {
    if (!this.nodeType || !this.runId || !this.nodeId) {
      this.showError("Node resolution unavailable: missing run context.");
      this.configureButton("Back to Map", true, () => this.returnToMap());
      return;
    }

    if (this.hasResolved) {
      return;
    }
    this.hasResolved = true;
    this.clearError();

    try {
      if (this.nodeType === "exit") {
        const exitRes = await apiClient.exitRun(this.runId);
        if (!exitRes.ok) {
          this.showError(`Exit failed: ${exitRes.error.message}`);
          this.configureButton("Retry", true, () => {
            this.hasResolved = false;
            void this.resolveNode();
          });
          return;
        }

        const status = deriveSummaryStatus({
          nodeType: this.nodeType,
          exitStatus: exitRes.data.status,
        });

        this.statusText?.setText("Exit resolved.");
        this.detailText?.setText([
          `Run status: ${exitRes.data.status}`,
          "This run endpoint has been finalized.",
        ].join("\n"));
        this.configureButton("Continue", true, () => {
          this.scene.start("RunEndSummaryScene", {
            status,
            rewards: [],
            progression: [],
            survivors: [],
            defeated: [],
          });
        });
        return;
      }

      const resolveRes = await apiClient.resolveRunNode(this.runId, this.nodeId);
      if (!resolveRes.ok) {
        this.showError(`Resolve failed: ${resolveRes.error.message}`);
        this.configureButton("Retry", true, () => {
          this.hasResolved = false;
          void this.resolveNode();
        });
        return;
      }

      const outcome = resolveRes.data.battle.outcome;
      const unlockedMsg = formatUnlockedNodes(resolveRes.data.next.unlocked_node_ids);
      this.statusText?.setText(`Node resolved: ${String(outcome).toUpperCase()}`);
      this.detailText?.setText([
        `Battle id: ${resolveRes.data.battle.battle_id}`,
        `Outcome: ${outcome}`,
        `Rounds: ${resolveRes.data.battle.rounds}`,
        `Ticks: ${resolveRes.data.battle.ticks}`,
        unlockedMsg,
      ].join("\n"));

      const refreshed = await apiClient.getCurrentRun();
      if (refreshed.ok && refreshed.data.run === null) {
        const status = deriveSummaryStatus({
          nodeType: this.nodeType,
          outcome,
        });
        this.configureButton("Continue", true, () => {
          this.scene.start("RunEndSummaryScene", {
            status,
            rewards: [],
            progression: [],
            survivors: [],
            defeated: [],
          });
        });
        return;
      }

      this.configureButton("Back to Map", true, () => {
        this.scene.start("MapExplorationScene", {
          resolutionMessage: `Node ${this.nodeId} resolved (${outcome}). ${unlockedMsg}`,
          resolutionColor: outcome === "victory" ? "#ccffcc" : "#ffd89e",
        });
      });
    } catch {
      this.showError("Node resolution unavailable. Please retry.");
      this.configureButton("Retry", true, () => {
        this.hasResolved = false;
        void this.resolveNode();
      });
    }
  }

  private configureButton(label: string, enabled: boolean, onClick: () => void): void {
    this.actionHandler = onClick;
    this.actionButton?.setText(label).setEnabled(enabled);
  }

  private showError(message: string): void {
    this.errorText?.setText(message);
  }

  private clearError(): void {
    this.errorText?.setText("");
  }

  private returnToMap(): void {
    this.scene.start("MapExplorationScene");
  }
}
