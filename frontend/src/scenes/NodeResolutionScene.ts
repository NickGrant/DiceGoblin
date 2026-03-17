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
const RESOLVE_TIMEOUT_MS = 12_000;

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
  private resolutionUiObjects: Phaser.GameObjects.GameObject[] = [];
  private resolveTimeoutMs = RESOLVE_TIMEOUT_MS;

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
    this.configureButton("Resolving...", false, () => {
      // Intentionally disabled during active resolve.
    });

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

      const resolveRes = await this.withTimeout(
        apiClient.resolveRunNode(this.runId, this.nodeId),
        this.resolveTimeoutMs,
        "resolve-node"
      ).catch((error: unknown) => {
        const message = (error as Error)?.message ?? "";
        if (/timeout/i.test(message)) {
          throw error;
        }
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
      this.renderResolutionPanels(resolveRes.data.battle.log, [
        `Battle id: ${resolveRes.data.battle.battle_id}`,
        `Outcome: ${outcome}`,
        `Rounds: ${resolveRes.data.battle.rounds}`,
        `Ticks: ${resolveRes.data.battle.ticks}`,
        unlockedMsg,
        "",
        ...battleLogLines,
      ]);

      const refreshed = await this.withTimeout(
        apiClient.getCurrentRun(),
        this.resolveTimeoutMs,
        "refresh-current-run"
      );
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
    } catch (error) {
      const message = (error as Error)?.message ?? "";
      const timedOut = /timeout/i.test(message);
      this.hasResolved = false;
      if (timedOut) {
        this.showError("Resolution timed out. Retry or return to map.");
        this.configureButton("Retry Resolve", true, () => {
          this.hasResolved = false;
          void this.resolveNode();
        });
      } else {
        this.showError("Node resolution unavailable. Please retry.");
        this.configureButton("Back to Map", true, () => this.returnToMap());
      }
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private withTimeout<T>(promise: Promise<T>, timeoutMs: number, label: string): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      const timer = setTimeout(() => {
        reject(new Error(`${label} timeout`));
      }, timeoutMs);
      promise
        .then((value) => {
          clearTimeout(timer);
          resolve(value);
        })
        .catch((error: unknown) => {
          clearTimeout(timer);
          reject(error);
        });
    });
  }

  private async handleNoEnemiesResolution(reason: string): Promise<void> {
    this.statusText?.setText("Node resolved: NO ENEMIES");
    this.renderResolutionPanels(null, [
      "Encounter resolved without battle.",
      `Reason: ${reason}`,
      "Returning to map will show updated node state.",
    ]);
    this.showError(`Reason: ${reason}`);

    try {
      const refreshed = await apiClient.getCurrentRun();
      if (refreshed.ok && refreshed.data.map?.nodes) {
        const node = refreshed.data.map.nodes.find((candidate) => String(candidate.id) === this.nodeId);
        if (node && String(node.status) === "cleared") {
          this.renderResolutionPanels(null, [
            "Encounter resolved without battle.",
            `Reason: ${reason}`,
            "Node status is now cleared.",
          ]);
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

  private renderResolutionPanels(
    log: { meta?: Record<string, unknown>; events?: Array<Record<string, unknown>>; [key: string]: unknown } | null,
    centerLines: string[]
  ): void {
    this.clearResolutionPanels();
    const layout = getPageLayout(this);
    const contentX = layout.content.x + 16;
    const contentY = layout.content.y + CONTENT_BODY_TOP_OFFSET + 38;
    const contentWidth = Math.max(300, layout.content.width - 32);
    const contentHeight = Math.max(180, layout.content.height - CONTENT_BODY_TOP_OFFSET - CONTENT_BODY_BOTTOM_PADDING - 24);
    const gap = 14;
    const leftWidth = Math.max(190, Math.floor(contentWidth * 0.28));
    const rightWidth = Math.max(220, Math.floor(contentWidth * 0.31));
    const centerWidth = Math.max(220, contentWidth - leftWidth - rightWidth - gap * 2);

    const leftX = contentX;
    const centerX = leftX + leftWidth + gap;
    const rightX = centerX + centerWidth + gap;

    const leftPanel = this.add
      .rectangle(leftX, contentY, leftWidth, contentHeight, 0x1d6c35, 0.32)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xa0d7ae, 0.7);
    const centerPanel = this.add
      .rectangle(centerX, contentY, centerWidth, contentHeight, 0x7c1018, 0.4)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xff7c88, 0.75);
    const rightPanel = this.add
      .rectangle(rightX, contentY, rightWidth, contentHeight, 0x242e86, 0.34)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xaeb6ff, 0.7);

    const allyTitle = this.add
      .text(leftX + 8, contentY + 8, "ALLIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#ddffe5",
      })
      .setOrigin(0, 0);
    const enemyTitle = this.add
      .text(rightX + 8, contentY + 8, "ENEMIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#e4e8ff",
      })
      .setOrigin(0, 0);

    const allyEntries = this.extractParticipantLabels(log, "player", 9);
    this.drawGrid(leftX + 10, contentY + 34, leftWidth - 20, Math.min(contentHeight - 44, 220), allyEntries, 0x2ca84a, "#e8fff0");

    const enemyEntries = this.extractParticipantLabels(log, "enemy", 99);
    const enemyGroups = Math.max(1, Math.ceil(enemyEntries.length / 9));
    const enemyGroupGap = 12;
    const enemyGridHeight = Math.floor((contentHeight - 44 - enemyGroupGap * (enemyGroups - 1)) / enemyGroups);
    for (let i = 0; i < enemyGroups; i += 1) {
      const groupEntries = enemyEntries.slice(i * 9, (i + 1) * 9);
      this.drawGrid(
        rightX + 10,
        contentY + 34 + i * (enemyGridHeight + enemyGroupGap),
        rightWidth - 20,
        enemyGridHeight,
        groupEntries,
        0x4651d0,
        "#f0f2ff"
      );
    }

    this.detailText?.destroy();
    this.detailText = this.add
      .text(centerX + 10, contentY + 8, centerLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#ffe6ea",
        lineSpacing: 4,
        wordWrap: { width: Math.max(140, centerWidth - 20) },
      })
      .setOrigin(0, 0);

    this.resolutionUiObjects.push(leftPanel, centerPanel, rightPanel, allyTitle, enemyTitle, this.detailText);
  }

  private drawGrid(
    x: number,
    y: number,
    width: number,
    height: number,
    labels: string[],
    fillColor: number,
    textColorHex: string
  ): void {
    const columns = 3;
    const rows = 3;
    const gap = 6;
    const cellW = Math.max(32, Math.floor((width - gap * (columns - 1)) / columns));
    const cellH = Math.max(28, Math.floor((height - gap * (rows - 1)) / rows));

    for (let i = 0; i < columns * rows; i += 1) {
      const col = i % columns;
      const row = Math.floor(i / columns);
      const cellX = x + col * (cellW + gap);
      const cellY = y + row * (cellH + gap);
      const label = labels[i] ?? "";
      const box = this.add
        .rectangle(cellX, cellY, cellW, cellH, fillColor, label ? 0.46 : 0.16)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.65);
      const text = this.add
        .text(cellX + 4, cellY + 4, label, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "11px",
          color: textColorHex,
          wordWrap: { width: Math.max(20, cellW - 8) },
        })
        .setOrigin(0, 0);
      this.resolutionUiObjects.push(box, text);
    }
  }

  private extractParticipantLabels(
    log: { meta?: Record<string, unknown>; [key: string]: unknown } | null,
    side: "player" | "enemy",
    max: number
  ): string[] {
    const participants = log
      && typeof log.meta === "object"
      && log.meta !== null
      && typeof (log.meta as Record<string, unknown>).participants === "object"
      && (log.meta as Record<string, unknown>).participants !== null
      ? ((log.meta as Record<string, unknown>).participants as Record<string, unknown>)
      : null;
    const list = participants && Array.isArray(participants[side]) ? participants[side] : [];
    const labels: string[] = [];
    for (const entry of list.slice(0, max)) {
      if (!entry || typeof entry !== "object") continue;
      const record = entry as Record<string, unknown>;
      const id = side === "player"
        ? String(record.unit_instance_id ?? "unit")
        : String(record.slug ?? "enemy");
      labels.push(id);
    }
    return labels;
  }

  private clearResolutionPanels(): void {
    for (const obj of this.resolutionUiObjects) {
      obj.destroy();
    }
    this.resolutionUiObjects = [];
  }
}






