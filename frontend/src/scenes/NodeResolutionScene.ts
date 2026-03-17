import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugResolvedNodeFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import {
  deriveSummaryStatus,
  formatBattleLogSummary,
  formatUnlockedNodes,
  isNodeResolutionType,
  type NodeResolutionType,
} from "./nodeResolutionFlow";

const ACTION_BODY_TOP_OFFSET = 72;
const CONTENT_BODY_TOP_OFFSET = 74;
const CONTENT_BODY_BOTTOM_PADDING = 22;

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
  private actionButton?: SharedActionButton;
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
    const debugConfig = getDebugSceneConfig();
    if (debugConfig.enabled) {
      if (!this.runId) this.runId = "91";
      if (!this.nodeId) this.nodeId = "502";
      if (!this.nodeType) this.nodeType = "combat";
    }
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
      title: "Resolve Node",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Node Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.statusText = this.add
      .text(layout.content.x + 16, layout.content.y + CONTENT_BODY_TOP_OFFSET, "Resolving node...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "24px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    this.detailText = this.add
      .text(layout.content.x + 16, layout.content.y + CONTENT_BODY_TOP_OFFSET + 40, "", {
        fontFamily: "monospace",
        fontSize: "14px",
        color: "#e8e8e8",
        wordWrap: { width: Math.max(300, layout.content.width - 32) },
      })
      .setOrigin(0, 0);

    this.errorText = this.add
      .text(layout.content.x + 16, layout.content.y + layout.content.height - CONTENT_BODY_BOTTOM_PADDING - 12, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#ffb3b3",
        wordWrap: { width: Math.max(300, layout.content.width - 32) },
      })
      .setOrigin(0, 1);

    this.actionButton = new SharedActionButton({
      scene: this,
      x: layout.buttons.x + 10,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
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
      markDebugSceneReady(this, { state: "error" });
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
        markDebugSceneReady(this, { state: "exit-resolved", status });
        return;
      }

      const resolveRes = await apiClient.resolveRunNode(this.runId, this.nodeId).catch(() => {
        const debugConfig = getDebugSceneConfig();
        if (!debugConfig.enabled) {
          throw new Error("Failed to resolve");
        }
        return getDebugResolvedNodeFixture();
      });
      if (!resolveRes.ok) {
        const reason = String(resolveRes.error.message ?? "Unknown error");
        if (/no[\s_-]*enemies/i.test(reason)) {
          await this.handleNoEnemiesResolution(reason);
          return;
        }
        this.showError(`Resolve failed: ${reason}`);
        this.configureButton("Back to Map", true, () => this.returnToMap());
        markDebugSceneReady(this, { state: "error", reason });
        return;
      }

      const outcome = resolveRes.data.battle.outcome;
      const unlockedMsg = formatUnlockedNodes(resolveRes.data.next.unlocked_node_ids);
      const battleLogLines = formatBattleLogSummary(resolveRes.data.battle.log);
      this.statusText?.setText(`Node resolved: ${String(outcome).toUpperCase()}`);
      this.detailText?.setText([
        `Battle id: ${resolveRes.data.battle.battle_id}`,
        `Outcome: ${outcome}`,
        `Rounds: ${resolveRes.data.battle.rounds}`,
        `Ticks: ${resolveRes.data.battle.ticks}`,
        unlockedMsg,
        "",
        ...battleLogLines,
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
        markDebugSceneReady(this, { state: "terminal", outcome });
        return;
      }

      this.configureButton("Back to Map", true, () => {
        this.scene.start("MapExplorationScene", {
          resolutionMessage: `Node ${this.nodeId} resolved (${outcome}). ${unlockedMsg}`,
          resolutionColor: outcome === "victory" ? "#ccffcc" : "#ffd89e",
        });
      });
      markDebugSceneReady(this, { state: "resolved", outcome });
    } catch {
      this.showError("Node resolution unavailable. Please retry.");
      this.configureButton("Back to Map", true, () => this.returnToMap());
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private async handleNoEnemiesResolution(reason: string): Promise<void> {
    this.statusText?.setText("Node resolved: NO ENEMIES");
    this.detailText?.setText([
      "Encounter resolved without battle.",
      `Reason: ${reason}`,
      "Returning to map will show updated node state.",
    ].join("\n"));
    this.showError(`Reason: ${reason}`);

    try {
      const refreshed = await apiClient.getCurrentRun();
      if (refreshed.ok && refreshed.data.map?.nodes) {
        const node = refreshed.data.map.nodes.find((candidate) => String(candidate.id) === this.nodeId);
        if (node && String(node.status) === "cleared") {
          this.detailText?.setText([
            "Encounter resolved without battle.",
            `Reason: ${reason}`,
            "Node status is now cleared.",
          ].join("\n"));
        }
      }
    } catch {
      // Fallback to reason-only message when refresh is unavailable.
    }

    this.configureButton("Back to Map", true, () => {
      this.scene.start("MapExplorationScene", {
        resolutionMessage: `Node ${this.nodeId} resolved: ${reason}`,
        resolutionColor: "#ffd89e",
      });
    });
    markDebugSceneReady(this, { state: "resolved-no-enemies", reason });
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






