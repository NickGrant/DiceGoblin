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
import FormationGrid3x3, { type FormationMap } from "../components/FormationGrid3x3";

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
  private logMaskGraphics?: Phaser.GameObjects.Graphics;
  private logViewport: { x: number; y: number; width: number; height: number } | null = null;
  private logScrollOffset = 0;
  private wheelHandlerRegistered = false;

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

    if (!this.wheelHandlerRegistered && this.input && typeof this.input.on === "function") {
      this.input.on("wheel", this.handleLogWheel, this);
      this.wheelHandlerRegistered = true;
      if (this.events && typeof this.events.once === "function") {
        this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
          if (this.input && typeof this.input.off === "function") {
            this.input.off("wheel", this.handleLogWheel, this);
          }
          this.wheelHandlerRegistered = false;
        });
      }
    }

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

    const centerPanel = this.add
      .rectangle(centerX, contentY, centerWidth, contentHeight, 0x7c1018, 0.4)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xff7c88, 0.75);

    const allyTitle = this.add
      .text(leftX + 8, contentY + 8, "ALLIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f4f4f4",
      })
      .setOrigin(0, 0);
    const enemyTitle = this.add
      .text(rightX + 8, contentY + 8, "ENEMIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f4f4f4",
      })
      .setOrigin(0, 0);

    const allyEntries = this.extractParticipantLabels(log, "player", 9);
    this.createFormationGrid(leftX + 10, contentY + 34, leftWidth - 20, Math.min(contentHeight - 44, 220), allyEntries);

    const enemyEntries = this.extractParticipantLabels(log, "enemy", 99);
    const enemyGroups = Math.max(1, Math.ceil(enemyEntries.length / 9));
    const enemyGroupGap = 12;
    const enemyGridHeight = Math.floor((contentHeight - 44 - enemyGroupGap * (enemyGroups - 1)) / enemyGroups);
    for (let i = 0; i < enemyGroups; i += 1) {
      const groupEntries = enemyEntries.slice(i * 9, (i + 1) * 9);
      this.createFormationGrid(
        rightX + 10,
        contentY + 34 + i * (enemyGridHeight + enemyGroupGap),
        rightWidth - 20,
        enemyGridHeight,
        groupEntries
      );
    }

    this.detailText?.destroy();
    const viewportX = centerX + 10;
    const viewportY = contentY + 10;
    const viewportWidth = Math.max(120, centerWidth - 20);
    const viewportHeight = Math.max(80, contentHeight - 20);
    this.logViewport = { x: viewportX, y: viewportY, width: viewportWidth, height: viewportHeight };

    this.detailText = this.add
      .text(viewportX, viewportY, centerLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#ffe6ea",
        lineSpacing: 4,
        wordWrap: { width: Math.max(120, viewportWidth) },
      })
      .setOrigin(0, 0);

    this.logScrollOffset = 0;
    this.detailText.setY(viewportY);
    this.logMaskGraphics?.destroy();
    this.logMaskGraphics = this.add.graphics();
    this.logMaskGraphics.fillStyle(0xffffff, 1);
    this.logMaskGraphics.fillRect(viewportX, viewportY, viewportWidth, viewportHeight);
    this.logMaskGraphics.visible = false;
    this.detailText.setMask(this.logMaskGraphics.createGeometryMask());

    this.resolutionUiObjects.push(centerPanel, allyTitle, enemyTitle, this.detailText, this.logMaskGraphics);
  }

  private createFormationGrid(
    x: number,
    y: number,
    width: number,
    height: number,
    labels: string[]
  ): void {
    const gap = 6;
    const cellByWidth = Math.floor((width - gap * 2) / 3);
    const cellByHeight = Math.floor((height - gap * 2) / 3);
    const cellSize = Math.max(28, Math.min(cellByWidth, cellByHeight));

    const formation = this.buildFormationMap(labels);
    const grid = new FormationGrid3x3({
      scene: this,
      x,
      y,
      cellSize,
      gap,
      formation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => {
        if (!unitId) return "";
        return String(unitId);
      },
      colors: {
        cellFill: 0x0f0f0f,
        cellFillAlpha: 0.88,
        stroke: 0xffffff,
        strokeAlpha: 0.55,
        selectedStroke: 0xffffff,
        selectedStrokeAlpha: 0.55,
        text: "#f2f2f2",
      },
    });
    this.resolutionUiObjects.push(grid);
  }

  private buildFormationMap(labels: string[]): Partial<FormationMap> {
    const cells: Array<keyof FormationMap> = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];
    const formation: Partial<FormationMap> = {};
    for (let i = 0; i < cells.length; i += 1) {
      const cell = cells[i];
      if (!cell) {
        continue;
      }
      formation[cell] = labels[i] ? labels[i] : null;
    }
    return formation;
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
    this.logMaskGraphics = undefined;
    this.logViewport = null;
    this.logScrollOffset = 0;
  }

  private handleLogWheel(
    pointer: Phaser.Input.Pointer,
    _gameObjects: Phaser.GameObjects.GameObject[],
    _deltaX: number,
    deltaY: number
  ): void {
    if (!this.logViewport || !this.detailText) {
      return;
    }
    const withinX = pointer.x >= this.logViewport.x && pointer.x <= this.logViewport.x + this.logViewport.width;
    const withinY = pointer.y >= this.logViewport.y && pointer.y <= this.logViewport.y + this.logViewport.height;
    if (!withinX || !withinY) {
      return;
    }

    const textBounds = this.detailText.getBounds();
    const maxScroll = Math.max(0, textBounds.height - this.logViewport.height);
    if (maxScroll <= 0) {
      this.logScrollOffset = 0;
      this.detailText.setY(this.logViewport.y);
      return;
    }

    const direction = deltaY > 0 ? 1 : -1;
    this.logScrollOffset = Phaser.Math.Clamp(this.logScrollOffset + direction * 24, 0, maxScroll);
    this.detailText.setY(this.logViewport.y - this.logScrollOffset);
  }
}






