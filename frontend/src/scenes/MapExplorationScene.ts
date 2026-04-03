import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import UnifiedButtonList from "../components/clickable-panel/UnifiedButtonList";
import NodeList from "../components/encounter-map/NodeList";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugRunFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { apiClient } from "../services/apiClient";
import type { CurrentRunNode, RunResponse } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import { resolveContentFrameBodyRect } from "../components/layout/contentAreaMath";
import { isNodeResolutionType } from "./nodeResolutionFlow";
import ConfirmModal from "../components/feedback/ConfirmModal";
import ToastMessage from "../components/feedback/ToastMessage";

const ACTION_BODY_TOP_OFFSET = 72;
const ACTION_BUTTON_WIDTH = 280;
const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 10;
const BODY_INNER_PADDING = 10;
const OVERVIEW_CHIP_HEIGHT = 54;
const OVERVIEW_CHIP_GAP = 10;
const MAP_NODE_TYPES = new Set(["combat", "loot", "rest", "boss", "exit"]);
const MAP_NODE_STATUSES = new Set(["locked", "available", "cleared"]);

export default class MapExplorationScene extends Phaser.Scene {
  private runEnvelope: RunResponse | null = null;
  private fallbackText?: Phaser.GameObjects.Text;
  private toast?: ToastMessage;
  private nodeList?: NodeList;
  private abandonDialog?: ConfirmModal;
  private overviewUiObjects: Phaser.GameObjects.GameObject[] = [];
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
    const actionButtonX =
      layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2));
    new UnifiedButtonList({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
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
    this.clearOverviewUi();
    this.nodeList?.destroy();
    this.nodeList = undefined;

    try {
      const run = await apiClient.getCurrentRun().catch(() => {
        const debugConfig = getDebugSceneConfig();
        if (!debugConfig.enabled) {
          throw new Error("Failed to fetch");
        }
        return getDebugRunFixture();
      });
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

      const nodes = run.data.map.nodes.filter((node) => this.isValidMapNode(node));
      if (nodes.length === 0) {
        this.showFallback("Run map payload is invalid. Please refresh or restart run.");
        return;
      }

      const edges = run.data.map.edges.filter((edge) => {
        const from = String(edge.from_node_id ?? "").trim();
        const to = String(edge.to_node_id ?? "").trim();
        return from.length > 0 && to.length > 0;
      });

      this.runEnvelope = run;

      const layout = getPageLayout(this);
      const contentBody = resolveContentFrameBodyRect({
        width: layout.content.width,
        height: layout.content.height,
        titleHeight: FRAME_TITLE_HEIGHT,
        marginPx: FRAME_MARGIN,
      });
      const contentBodyX = layout.content.x + contentBody.x;
      const contentBodyY = layout.content.y + contentBody.y;
      const contentBodyWidth = contentBody.width;
      const contentBodyHeight = contentBody.height;
      const availableCount = nodes.filter((node) => String(node.status) === "available").length;
      const clearedCount = nodes.filter((node) => String(node.status) === "cleared").length;
      const lockedCount = nodes.filter((node) => String(node.status) === "locked").length;
      const chipObjects: Phaser.GameObjects.GameObject[] = [];
      const chipY = contentBodyY + BODY_INNER_PADDING;
      const chipGap = OVERVIEW_CHIP_GAP;
      const chipWidth = Math.floor((contentBodyWidth - BODY_INNER_PADDING * 2 - chipGap * 2) / 3);
      if (typeof (this.add as unknown as { rectangle?: unknown }).rectangle === "function") {
        const chipConfigs = [
          { label: "Available", value: availableCount, stroke: 0x8bdfe0 },
          { label: "Cleared", value: clearedCount, stroke: 0x99e09c },
          { label: "Locked", value: lockedCount, stroke: 0xffd89e },
        ];
        chipConfigs.forEach((chip, index) => {
          const chipX = contentBodyX + BODY_INNER_PADDING + index * (chipWidth + chipGap);
          const card = this.add
            .rectangle(chipX, chipY, chipWidth, OVERVIEW_CHIP_HEIGHT, 0x11181d, 0.55)
            .setOrigin(0, 0)
            .setStrokeStyle(1, chip.stroke, 0.5);
          const label = this.add
            .text(chipX + 12, chipY + 9, chip.label.toUpperCase(), {
              fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
              fontSize: "13px",
              color: "#dfe8ea",
            })
            .setOrigin(0, 0);
          const value = this.add
            .text(chipX + 12, chipY + 27, String(chip.value), {
              fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
              fontSize: "18px",
              color: "#ffffff",
            })
            .setOrigin(0, 0);
          chipObjects.push(card, label, value);
        });
      }
      const mapBackdropRect = new Phaser.Geom.Rectangle(
        contentBodyX + BODY_INNER_PADDING,
        chipY + OVERVIEW_CHIP_HEIGHT + OVERVIEW_CHIP_GAP,
        contentBodyWidth - BODY_INNER_PADDING * 2,
        contentBodyHeight - BODY_INNER_PADDING * 2 - OVERVIEW_CHIP_HEIGHT - OVERVIEW_CHIP_GAP
      );
      const backdrop = this.createMapBackdrop(run.data.run.region_id, mapBackdropRect);
      this.overviewUiObjects.push(...chipObjects);
      if (backdrop) {
        this.overviewUiObjects.push(backdrop);
      }

      this.nodeList = new NodeList(
        this,
        0,
        0,
        run.data.run,
        nodes,
        edges,
        {
          scatterRect: new Phaser.Geom.Rectangle(
            mapBackdropRect.x + 26,
            mapBackdropRect.y + 24,
            Math.max(220, mapBackdropRect.width - 52),
            Math.max(180, mapBackdropRect.height - 48)
          ),
          nodeSize: 64,
          onNodeClick: (node) => this.handleNodeClick(node),
        }
      );
      markDebugSceneReady(this, {
        runId: run.data.run.run_id,
        nodeCount: nodes.length,
      });
    } catch {
      this.showFallback("Run data unavailable. Please retry.");
    }
  }

  private createMapBackdrop(regionId: string, rect: Phaser.Geom.Rectangle): Phaser.GameObjects.GameObject | undefined {
    const addApi = this.add as unknown as {
      image?: (x: number, y: number, key: string) => Phaser.GameObjects.Image;
      rectangle?: (x: number, y: number, width: number, height: number, color?: number, alpha?: number) => Phaser.GameObjects.Rectangle;
    };
    const textureKey = this.resolveRegionMapTextureKey(regionId);
    if (textureKey && typeof addApi.image === "function" && this.hasTexture(textureKey)) {
      return addApi.image(rect.x, rect.y, textureKey).setOrigin(0, 0).setDisplaySize(rect.width, rect.height);
    }
    if (typeof addApi.rectangle === "function") {
      return addApi.rectangle(rect.x, rect.y, rect.width, rect.height, 0x182026, 0.9).setOrigin(0, 0);
    }
    return undefined;
  }

  private resolveRegionMapTextureKey(regionId: string): string | null {
    if (regionId === "3") return "region_farm_map";
    if (regionId === "1") return "region_mountain_map";
    if (regionId === "2") return "region_swamp_map";
    return null;
  }

  private hasTexture(key: string): boolean {
    const textures = (this as Phaser.Scene & { textures?: { exists?: (textureKey: string) => boolean } }).textures;
    return typeof textures?.exists === "function" && textures.exists(key);
  }

  private isValidMapNode(node: CurrentRunNode): boolean {
    const nodeType = String(node.node_type ?? "").trim();
    const status = String(node.status ?? "").trim();
    if (!MAP_NODE_TYPES.has(nodeType)) {
      return false;
    }
    if (!MAP_NODE_STATUSES.has(status)) {
      return false;
    }
    return true;
  }

  private async handleNodeClick(node: CurrentRunNode): Promise<void> {
    if (!this.runEnvelope?.ok || this.runEnvelope.data.run === null) return;

    if (String(node.status ?? "") !== "available") {
      this.showFallback(`Node '${node.id}' is ${String(node.status)} and cannot be selected.`);
      return;
    }

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
    this.abandonDialog = new ConfirmModal({
      scene: this,
      title: "ABANDON RUN?",
      message: "This will end the current run immediately.",
      width: 640,
      height: 320,
      acceptLabel: "Abandon",
      rejectLabel: "Stay",
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

  private clearOverviewUi(): void {
    for (const uiObject of this.overviewUiObjects) {
      uiObject.destroy();
    }
    this.overviewUiObjects = [];
  }

  private showFallback(message: string): void {
    this.fallbackText?.destroy();
    const layout = getPageLayout(this);
    this.fallbackText = this.add.text(layout.content.x + 16, layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + 16, message, {
      fontFamily: "monospace",
      fontSize: "16px",
      color: "#f5f5f5",
      align: "left",
      wordWrap: { width: Math.max(320, layout.content.width - 32) },
    }).setOrigin(0, 0);
    markDebugSceneReady(this, { state: "fallback", message });
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

