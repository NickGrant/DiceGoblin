import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture, getDebugRestFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { TeamFormationCell, UnitRecord, RestRunUnitState } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

type Cell = FormationCell;
const CELLS: Cell[] = ["A1", "A2", "A3", "B1", "B2", "B3", "C1", "C2", "C3"];

function emptyFormation(): FormationMap {
  return { A1: null, B1: null, C1: null, A2: null, B2: null, C2: null, A3: null, B3: null, C3: null };
}

const FRAME_BODY_TOP_OFFSET = 74;
const FRAME_BODY_BOTTOM_PADDING = 18;
const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const CONTENT_INSET = 10;
const CONTENT_COLUMN_GAP = 12;
const ACTION_PANEL_PADDING = 14;
const ACTION_TOP_GAP = 14;
const ACTION_BUTTON_STEP = 56;
const ACTION_BUTTON_GAP = 8;
const UNIT_CARD_WIDTH = 132;
const UNIT_PANEL_PADDING = 12;
const UNIT_PANEL_WIDTH = UNIT_CARD_WIDTH * 3 + UNIT_PANEL_PADDING * 4;
const GRID_SIZE = 308;

export default class RestManagementScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";

  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private summaryText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private runUnitState: RestRunUnitState[] = [];
  private baselineRunUnitHp: Map<string, number> = new Map();
  private editUnitIds: Set<string> = new Set();
  private editFormation: FormationMap = emptyFormation();
  private selectedUnitId: string | null = null;
  private finalized = false;

  private grid?: FormationGrid3x3;
  private unitPanel?: UnitCardGrid;
  private applyButton?: SharedActionButton;
  private finalizeButton?: SharedActionButton;
  private buyUnitButton?: SharedActionButton;
  private buyDiceButton?: SharedActionButton;
  private storeStatusText?: Phaser.GameObjects.Text;
  private overviewUiObjects: Phaser.GameObjects.GameObject[] = [];

  constructor() {
    super({ key: "RestManagementScene" });
  }

  init(data: { runId?: string; nodeId?: string }): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    const debugConfig = getDebugSceneConfig();
    if (debugConfig.enabled) {
      if (!this.runId) this.runId = "91";
      if (!this.nodeId) this.nodeId = "503";
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
      title: "Manage Warband",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Rest Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + FRAME_BODY_TOP_OFFSET - 28, "Preparing rest...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    }).setOrigin(0, 0);

    if (!this.runId || !this.nodeId) {
      this.loadingText.setText("Rest unavailable: missing run context.");
      return;
    }

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const [profile, restOpen] = await Promise.all([
        apiClient.getProfile({ force: true }).catch(() => {
          const debugConfig = getDebugSceneConfig();
          if (!debugConfig.enabled) {
            throw new Error("Failed to fetch");
          }
          return getDebugProfileFixture();
        }),
        apiClient.openRest(this.runId, this.nodeId).catch(() => {
          const debugConfig = getDebugSceneConfig();
          if (!debugConfig.enabled) {
            throw new Error("Failed to open rest");
          }
          return getDebugRestFixture();
        }),
      ]);

      if (!profile.ok) throw new Error(profile.error.message);
      if (!restOpen.ok) throw new Error(restOpen.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      this.runUnitState = restOpen.data.run_unit_state ?? [];
      this.baselineRunUnitHp = new Map(this.runUnitState.map((s) => [s.unit_instance_id, s.hp]));
      this.editUnitIds = new Set(restOpen.data.unit_ids ?? []);
      this.editFormation = emptyFormation();
      for (const f of restOpen.data.formation ?? []) {
        const cell = f.cell as Cell;
        if (CELLS.includes(cell)) {
          this.editFormation[cell] = f.unit_instance_id;
        }
      }

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.buildUi();
      markDebugSceneReady(this, {
        runId: this.runId,
        nodeId: this.nodeId,
        unitCount: this.units.length,
      });
    } catch (e) {
      this.loadingText?.setText(`Rest unavailable.\n${(e as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    this.clearOverviewUi();
    const contentBodyX = layout.content.x + FRAME_MARGIN + CONTENT_INSET;
    const contentBodyY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + CONTENT_INSET + 88;
    const contentBodyWidth = Math.max(280, layout.content.width - (FRAME_MARGIN + CONTENT_INSET) * 2);
    const contentBodyHeight = Math.max(220, layout.content.height - FRAME_TITLE_HEIGHT - (FRAME_MARGIN + CONTENT_INSET) * 2 - 88);

    const unitPanelWidth = Math.min(UNIT_PANEL_WIDTH, Math.max(280, contentBodyWidth - GRID_SIZE - CONTENT_COLUMN_GAP));
    const unitPanelX = contentBodyX;
    const unitPanelY = contentBodyY;

    const rightAreaX = unitPanelX + unitPanelWidth + CONTENT_COLUMN_GAP;
    const rightAreaWidth = Math.max(200, contentBodyX + contentBodyWidth - rightAreaX);
    const gridX = rightAreaX + Math.max(0, Math.floor((rightAreaWidth - GRID_SIZE) / 2));
    const gridY = contentBodyY + Math.max(0, Math.floor((contentBodyHeight - GRID_SIZE) / 2));

    const actionBodyX = layout.buttons.x + FRAME_MARGIN + ACTION_PANEL_PADDING;
    const actionBodyY = layout.buttons.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + ACTION_TOP_GAP;
    const actionBodyWidth = Math.max(280, layout.buttons.width - (FRAME_MARGIN + ACTION_PANEL_PADDING) * 2);
    const actionButtonX = actionBodyX + Math.max(0, Math.floor((actionBodyWidth - 280) / 2));

    const overviewLabel = this.add
      .text(layout.content.x + 24, layout.content.y + 88, "REST PHASE", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#f0d38a",
      })
      .setOrigin(0, 0);
    const overviewBody = this.add
      .text(layout.content.x + 24, layout.content.y + 118, "Reposition the run squad, buy a quick upgrade if needed, then lock in the rest stop before returning to the map.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#eef4f5",
        lineSpacing: 6,
        wordWrap: { width: layout.content.width - 48 },
      })
      .setOrigin(0, 0);
    this.overviewUiObjects.push(overviewLabel, overviewBody);

    const summaryCard = this.add
      .rectangle(actionBodyX, actionBodyY, actionBodyWidth, 94, 0x102125, 0.65)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.3);
    const summaryText = this.add
      .text(actionBodyX + 12, actionBodyY + 10, [
        `Rest node: ${this.nodeId}`,
        `Units in squad: ${this.editUnitIds.size}`,
        this.finalized ? "Status: finalized" : "Status: planning phase",
      ].join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "17px",
        color: "#e7f4f5",
        lineSpacing: 6,
        wordWrap: { width: actionBodyWidth - 24 },
      })
      .setOrigin(0, 0);
    this.overviewUiObjects.push(summaryCard, summaryText);

    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: unitPanelX,
      y: unitPanelY,
      width: unitPanelWidth,
      height: contentBodyHeight,
      title: "RUN SQUAD",
      units: this.units,
      getCardState: (u) => this.getUnitRowState(u),
      onUnitClick: (u) => this.handleUnitClick(u),
      maxVisibleCards: 3,
    });

    this.grid = new FormationGrid3x3({
      scene: this,
      x: gridX,
      y: gridY,
      formation: this.editFormation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => this.getCellLabel(cell, unitId),
      onCellClick: (cell) => this.handleCellClick(cell),
      onCellDoubleClick: (cell) => this.handleCellDoubleClick(cell),
    });

    this.add.text(gridX, gridY - 34, "Back (Left)  <- Formation ->  Front (Right)", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f2f2f2",
    }).setOrigin(0, 0);

    this.applyButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + 112,
      label: "Apply State",
      onClick: () => void this.applyRestState(),
    });

    this.finalizeButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + 112 + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP),
      label: "Finalize Rest",
      onClick: () => void this.finalizeRest(),
    });

    new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + 112 + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 2,
      label: "Manage Dice",
      onClick: () => this.scene.start("InventoryScene", {
        runId: this.runId,
        nodeId: this.nodeId,
        returnScene: "RestManagementScene",
      }),
    });
    this.buyUnitButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + 112 + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 3,
      label: `Buy Basic Unit (${30})`,
      enabled: true,
      onClick: () => void this.purchaseStoreItem("basic_unit"),
    });
    this.buyDiceButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + 112 + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 4,
      label: `Buy Basic Dice (${20})`,
      enabled: true,
      onClick: () => void this.purchaseStoreItem("basic_dice"),
    });
    this.storeStatusText = this.add.text(actionButtonX, actionBodyY + 112 + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 5, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#dddddd",
      wordWrap: { width: 280 },
    });

    this.refreshUi();
  }

  private getCellLabel(cell: Cell, unitId: string | null): string {
    if (!unitId) return `${cell}\n(Empty)`;
    const u = this.units.find((x) => x.id === unitId);
    return `${cell}\n${u ? u.name : `Unit ${unitId}`}`;
  }

  private isUnitPlaced(unitId: string): boolean {
    return Object.values(this.editFormation).includes(unitId);
  }

  private getUnitRowState(u: UnitRecord): UnitCardState {
    const inTeam = this.editUnitIds.has(u.id);
    const placed = this.isUnitPlaced(u.id);
    const selected = this.selectedUnitId === u.id;
    return {
      highlighted: inTeam,
      outlined: placed,
      disabled: this.finalized,
      badgeText: selected ? "SELECTED" : placed ? "PLACED" : null,
    };
  }

  private refreshUi(): void {
    this.grid?.setFormation(this.editFormation);
    this.unitPanel?.refreshCardStates();
    this.applyButton?.setEnabled(!this.finalized);
    this.finalizeButton?.setEnabled(!this.finalized);
    this.buyUnitButton?.setEnabled(!this.finalized);
    this.buyDiceButton?.setEnabled(!this.finalized);
    this.storeStatusText?.setText("Rest Store: Basic Unit 30 | Basic Dice 20");
  }

  private handleCellClick(cell: Cell): void {
    if (this.finalized) return;
    if (this.selectedUnitId) {
      this.placeUnitIntoCell(this.selectedUnitId, cell);
      this.selectedUnitId = null;
    }
    this.refreshUi();
  }

  private handleCellDoubleClick(cell: Cell): void {
    if (this.finalized) return;
    if (this.selectedUnitId) return;
    if (this.editFormation[cell] === null) return;
    this.editFormation[cell] = null;
    this.refreshUi();
  }

  private handleUnitClick(u: UnitRecord): void {
    if (this.finalized) return;
    const selectedCell = this.grid?.getSelectedCell() ?? null;
    if (selectedCell) {
      this.placeUnitIntoCell(u.id, selectedCell);
      this.selectedUnitId = null;
    } else {
      this.selectedUnitId = u.id;
    }
    this.refreshUi();
  }

  private placeUnitIntoCell(unitId: string, cell: Cell): void {
    this.editUnitIds.add(unitId);
    for (const c of CELLS) {
      if (this.editFormation[c] === unitId) this.editFormation[c] = null;
    }
    this.editFormation[cell] = unitId;
  }

  private async applyRestState(): Promise<boolean> {
    const formation: TeamFormationCell[] = CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.editFormation[cell] ?? null,
    }));
    const res = await apiClient.updateRestState(this.runId, this.nodeId, {
      unit_ids: Array.from(this.editUnitIds),
      formation,
    });
    if (!res.ok) {
      this.showToast(`Apply failed: ${res.error.message}`);
      return false;
    }
    this.runUnitState = res.data.run_unit_state ?? this.runUnitState;
    this.showToast("Rest state saved.", "#ccffcc");
    return true;
  }

  private async finalizeRest(): Promise<void> {
    if (!(await this.applyRestState())) return;
    const res = await apiClient.finalizeRest(this.runId, this.nodeId);
    if (!res.ok) {
      this.showToast(`Finalize failed: ${res.error.message}`);
      return;
    }

    this.finalized = true;
    this.refreshUi();

    const progressionCount = (res.data.progression ?? []).length;
    const healedUnits = this.runUnitState.filter((s) => {
      const before = this.baselineRunUnitHp.get(s.unit_instance_id) ?? s.hp;
      return s.hp > before;
    }).length;
    const summaryParts = [
      "Rest finalized.",
      `${healedUnits} unit${healedUnits === 1 ? "" : "s"} healed`,
      `${progressionCount} progression update${progressionCount === 1 ? "" : "s"}`,
    ];
    this.scene.start("MapExplorationScene", {
      resolutionMessage: summaryParts.join(" - "),
      resolutionColor: "#ccffcc",
    });
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 34, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    }).setOrigin(0, 0);
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }

  private async purchaseStoreItem(itemType: "basic_unit" | "basic_dice"): Promise<void> {
    if (this.finalized) {
      this.showToast("Rest already finalized.");
      return;
    }
    const res = await apiClient.purchaseRestStoreItem(this.runId, this.nodeId, itemType);
    if (!res.ok) {
      this.showToast(`Store purchase failed: ${this.toPlayerFacingMessage(res.error.message)}`);
      return;
    }

    const purchase = res.data.purchase;
    const itemLabel = itemType === "basic_unit"
      ? `Unit ${(purchase as { unit_instance_id?: string }).unit_instance_id ?? ""}`
      : `Dice ${(purchase as { dice_instance_id?: string }).dice_instance_id ?? ""}`;
    this.showToast(`Purchased ${itemLabel}.`, "#ccffcc");
    this.scene.restart({ runId: this.runId, nodeId: this.nodeId });
  }

  private toPlayerFacingMessage(message: string): string {
    return message
      .replace(/soft currency/gi, "teeth")
      .replace(/\bcurrency\b/gi, "teeth");
  }

  private clearOverviewUi(): void {
    for (const uiObject of this.overviewUiObjects) {
      uiObject.destroy();
    }
    this.overviewUiObjects = [];
  }
}









