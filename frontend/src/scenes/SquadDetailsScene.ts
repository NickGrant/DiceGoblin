import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import UnifiedButtonList from "../components/clickable-panel/UnifiedButtonList";
import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { apiClient } from "../services/apiClient";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { TeamRecord, UnitRecord, TeamFormationCell } from "../types/ApiResponse";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import InputModal from "../components/feedback/InputModal";
import ConfirmModal from "../components/feedback/ConfirmModal";
import {
  SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
  normalizeNewSquadName,
} from "./warbandManagementState";

type Cell = FormationCell;
const CELLS: Cell[] = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];

function emptyFormation(): FormationMap {
  return { A1: null, B1: null, C1: null, A2: null, B2: null, C2: null, A3: null, B3: null, C3: null };
}

const FRAME_BODY_TOP_OFFSET = 74;
const FRAME_BODY_BOTTOM_PADDING = 18;
const ACTION_BODY_TOP_OFFSET = 64;
const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const CONTENT_INSET = 10;
const CONTENT_COLUMN_GAP = 12;
const ACTION_PANEL_PADDING = 14;
const ACTION_TOP_GAP = 14;
const ACTION_BUTTON_STEP = 64;
const ACTION_BUTTON_GAP = 12;
const UNIT_CARD_WIDTH = 132;
const UNIT_PANEL_PADDING = 12;
const UNIT_PANEL_WIDTH = UNIT_CARD_WIDTH * 3 + UNIT_PANEL_PADDING * 4;
const GRID_SIZE = 308;

export default class SquadDetailsScene extends Phaser.Scene {
  private squadId = "";
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private titleText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private squad: TeamRecord | null = null;
  private squadCount = 0;
  private hasActiveRun = false;

  private editUnitIds: Set<string> = new Set();
  private editFormation: FormationMap = emptyFormation();
  private selectedUnitId: string | null = null;

  private grid?: FormationGrid3x3;
  private unitPanel?: UnitCardGrid;
  private clearButton?: SharedActionButton;
  private saveButton?: SharedActionButton;
  private activateButton?: SharedActionButton;
  private renameDialog?: InputModal;
  private deleteDialog?: ConfirmModal;

  constructor() {
    super({ key: "SquadDetailsScene" });
  }

  init(data: { squadId?: string }): void {
    this.squadId = String(data?.squadId ?? "");
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
      title: "Squad Details",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Squad Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading squad details...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });
    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true }).catch(() => {
        const debugConfig = getDebugSceneConfig();
        if (!debugConfig.enabled) {
          throw new Error("Failed to fetch");
        }
        return getDebugProfileFixture();
      });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      const squads = (profile.data.squads ?? []) as TeamRecord[];
      this.squadCount = squads.length;
      this.hasActiveRun = profile.data.active_run !== null;
      this.squad = squads.find((s) => s.id === this.squadId) ?? squads[0] ?? null;
      if (!this.squad) throw new Error("No squads found.");

      this.editUnitIds = new Set(this.squad.unit_ids ?? []);
      this.editFormation = emptyFormation();
      for (const f of this.squad.formation ?? []) {
        const cell = f.cell as Cell;
        if (CELLS.includes(cell)) this.editFormation[cell] = f.unit_instance_id;
      }

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.buildUi();
      markDebugSceneReady(this, {
        squadId: this.squad.id,
        unitCount: this.units.length,
      });
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const contentBodyX = layout.content.x + FRAME_MARGIN + CONTENT_INSET;
    const contentBodyY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + CONTENT_INSET;
    const contentBodyWidth = Math.max(280, layout.content.width - (FRAME_MARGIN + CONTENT_INSET) * 2);
    const contentBodyHeight = Math.max(220, layout.content.height - FRAME_TITLE_HEIGHT - (FRAME_MARGIN + CONTENT_INSET) * 2);

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
    if (!this.squad) return;

    this.titleText?.destroy();
    this.titleText = undefined;

    this.unitPanel?.destroy();
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: unitPanelX,
      y: unitPanelY,
      width: unitPanelWidth,
      height: contentBodyHeight,
      title: "UNITS",
      units: this.units,
      getCardState: (u) => this.getUnitRowState(u),
      onUnitClick: (u) => this.handleUnitClick(u),
      maxVisibleCards: 3,
    });

    this.grid?.destroy();
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

    this.clearButton?.destroy();
    this.saveButton?.destroy();
    this.activateButton?.destroy();
    new UnifiedButtonList({
      scene: this,
      x: actionButtonX,
      y: actionBodyY,
      gapY: ACTION_BUTTON_GAP,
      buttons: [
        {
          label: "Back",
          onClick: () => this.scene.start("WarbandManagementScene"),
        },
        {
          label: "Rename",
          onClick: () => void this.renameSquad(),
        },
      ],
    });

    this.clearButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 2,
      label: "Clear Cell",
      enabled: false,
      onClick: () => this.clearSelectedCell(),
    });
    this.saveButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 3,
      label: "Save Squad",
      onClick: () => void this.saveTeam(),
    });
    this.activateButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 4,
      label: "Set Active",
      enabled: !this.squad.is_active,
      onClick: () => void this.activateSquad(),
    });
    new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: actionBodyY + (ACTION_BUTTON_STEP + ACTION_BUTTON_GAP) * 5,
      label: "Delete Squad",
      enabled: this.canDeleteSquad(),
      onClick: () => void this.deleteSquad(),
    });

    this.refreshDerivedUiState();
  }

  private getSelectedCell(): Cell | null {
    return this.grid?.getSelectedCell() ?? null;
  }

  private getCellLabel(cell: Cell, unitId: string | null): string {
    if (!unitId) return `${cell}\n(Empty)`;
    const u = this.units.find((x) => x.id === unitId);
    return `${cell}\n${u ? u.name : `Unit ${unitId}`}`;
  }

  private getUnitRowState(u: UnitRecord): UnitCardState {
    const inTeam = this.editUnitIds.has(u.id);
    const placed = Object.values(this.editFormation).includes(u.id);
    const selected = this.selectedUnitId === u.id;
    return {
      highlighted: inTeam,
      outlined: placed,
      badgeText: selected ? "SELECTED" : placed ? "PLACED" : null,
    };
  }

  private refreshDerivedUiState(): void {
    this.unitPanel?.refreshCardStates();
    this.grid?.setFormation(this.editFormation);
    const cell = this.getSelectedCell();
    const occupied = cell ? this.editFormation[cell] !== null : false;
    this.clearButton?.setEnabled(!!cell && occupied);
  }

  private handleCellClick(cell: Cell): void {
    if (this.selectedUnitId) {
      this.placeUnitIntoCell(this.selectedUnitId, cell);
      this.selectedUnitId = null;
    }
    this.refreshDerivedUiState();
  }

  private handleCellDoubleClick(cell: Cell): void {
    if (this.selectedUnitId) return;
    if (this.editFormation[cell] === null) return;
    this.editFormation[cell] = null;
    this.refreshDerivedUiState();
  }

  private handleUnitClick(u: UnitRecord): void {
    const cell = this.getSelectedCell();
    if (cell) {
      this.placeUnitIntoCell(u.id, cell);
      this.selectedUnitId = null;
    } else {
      this.selectedUnitId = u.id;
    }
    this.refreshDerivedUiState();
  }

  private placeUnitIntoCell(unitId: string, cell: Cell): void {
    this.editUnitIds.add(unitId);
    for (const c of CELLS) {
      if (this.editFormation[c] === unitId) this.editFormation[c] = null;
    }
    this.editFormation[cell] = unitId;
  }

  private clearSelectedCell(): void {
    const cell = this.getSelectedCell();
    if (!cell || this.editFormation[cell] === null) return;
    this.editFormation[cell] = null;
    this.refreshDerivedUiState();
  }

  private async saveTeam(nameOverride?: string): Promise<void> {
    if (!this.squad) return;
    const formation: TeamFormationCell[] = CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.editFormation[cell] ?? null,
    }));
    const payload: {
      unit_ids: string[];
      formation: TeamFormationCell[];
      name?: string;
    } = {
      unit_ids: Array.from(this.editUnitIds),
      formation,
    };
    if (nameOverride) payload.name = nameOverride;

    const res = await apiClient.updateTeam(this.squad.id, payload);
    if (!res.ok) {
      this.showToast(`Save failed: ${res.error.message}`);
      return;
    }
    this.showToast("Squad saved.", "#ccffcc");
    await this.loadData();
  }

  private async renameSquad(): Promise<void> {
    if (!this.squad) return;
    if (this.renameDialog) return;
    let enteredName = this.squad.name;

    this.renameDialog = new InputModal({
      scene: this,
      title: "RENAME SQUAD",
      message: "Enter a new squad name.",
      acceptLabel: "Rename",
      rejectLabel: "Cancel",
      input: {
        initialValue: this.squad.name,
        placeholder: this.squad.name,
        maxLength: 24,
        allowedCharacterPattern: SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
      },
      onAcceptInput: (value) => {
        enteredName = value;
      },
      onReject: () => {
        this.renameDialog = undefined;
      },
      onAccept: async () => {
        this.renameDialog?.close();
        this.renameDialog = undefined;
        const nextName = normalizeNewSquadName(enteredName);
        if (!nextName) {
          this.showToast("Name must use letters, numbers, spaces, or [].-");
          return;
        }
        if (nextName === this.squad?.name) return;
        await this.saveTeam(nextName);
      },
      width: 620,
      height: 320,
    });
  }

  private async activateSquad(): Promise<void> {
    if (!this.squad) return;
    const res = await apiClient.activateTeam(this.squad.id);
    if (!res.ok) {
      this.showToast(`Activate failed: ${res.error.message}`);
      return;
    }
    this.showToast("Squad set active.", "#ccffcc");
    await this.loadData();
  }

  private canDeleteSquad(): boolean {
    if (!this.squad) return false;
    if (this.squadCount <= 1) return false;
    if (this.hasActiveRun && this.squad.is_active) return false;
    return true;
  }

  private async deleteSquad(): Promise<void> {
    if (!this.squad) return;
    if (!this.canDeleteSquad()) {
      this.showToast("Cannot delete this squad in current state.");
      return;
    }
    if (this.deleteDialog) return;

    const squadId = this.squad.id;
    const squadName = this.squad.name;
    this.deleteDialog = new ConfirmModal({
      scene: this,
      title: "DELETE SQUAD?",
      message: `Delete squad '${squadName}'? This cannot be undone.`,
      acceptLabel: "Delete",
      rejectLabel: "Cancel",
      width: 620,
      height: 320,
      onReject: () => {
        this.deleteDialog = undefined;
      },
      onAccept: async () => {
        this.deleteDialog?.close();
        this.deleteDialog = undefined;

        const res = await apiClient.deleteTeam(squadId);
        if (!res.ok) {
          this.showToast(`Delete failed: ${res.error.message}`);
          return;
        }
        this.showToast("Squad deleted.", "#ccffcc");
        this.scene.start("WarbandManagementScene");
      },
    });
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    });
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}






