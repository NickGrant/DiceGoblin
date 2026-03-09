import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ActionButton from "../components/clickable-panel/ActionButton";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { apiClient } from "../services/apiClient";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { TeamRecord, UnitRecord, TeamFormationCell } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

type Cell = FormationCell;
const CELLS: Cell[] = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];

function emptyFormation(): FormationMap {
  return { A1: null, B1: null, C1: null, A2: null, B2: null, C2: null, A3: null, B3: null, C3: null };
}

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
  private clearButton?: ActionButton;
  private saveButton?: ActionButton;
  private activateButton?: ActionButton;

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
      bodyColor: 0x00f6ff,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Squad Actions",
      bodyColor: 0x00ff72,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading squad details...", {
      fontFamily: "Arial",
      fontSize: "20px",
      color: "#ffffff",
    });
    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true });
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
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const actionButtonX = layout.buttons.x + 10;
    if (!this.squad) return;

    this.titleText?.destroy();
    this.titleText = this.add.text(layout.content.x + 16, layout.content.y - 34, `SQUAD: ${this.squad.name}`, {
      fontFamily: "Arial",
      fontSize: "20px",
      color: "#ffffff",
    });

    this.unitPanel?.destroy();
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: 400,
      height: 420,
      title: "UNITS",
      units: this.units,
      getCardState: (u) => this.getUnitRowState(u),
      onUnitClick: (u) => this.handleUnitClick(u),
      maxVisibleCards: 6,
    });

    this.grid?.destroy();
    this.grid = new FormationGrid3x3({
      scene: this,
      x: layout.content.x + 520,
      y: layout.content.y + 48,
      formation: this.editFormation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => this.getCellLabel(cell, unitId),
      onCellClick: (cell) => this.handleCellClick(cell),
      onCellDoubleClick: (cell) => this.handleCellDoubleClick(cell),
    });

    this.clearButton?.destroy();
    this.saveButton?.destroy();
    this.activateButton?.destroy();
    new ActionButtonList({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 24,
      gapY: 5,
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

    this.clearButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 184,
      label: "Clear Cell",
      enabled: false,
      onClick: () => this.clearSelectedCell(),
    });
    this.saveButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 264,
      label: "Save Squad",
      onClick: () => void this.saveTeam(),
    });
    this.activateButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 344,
      label: "Set Active",
      enabled: !this.squad.is_active,
      onClick: () => void this.activateSquad(),
    });
    new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 424,
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
    const nextName = window.prompt("Rename squad:", this.squad.name)?.trim();
    if (!nextName || nextName === this.squad.name) return;
    await this.saveTeam(nextName);
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
    const confirm = window.confirm(`Delete squad '${this.squad.name}'?`);
    if (!confirm) return;

    const res = await apiClient.deleteTeam(this.squad.id);
    if (!res.ok) {
      this.showToast(`Delete failed: ${res.error.message}`);
      return;
    }
    this.showToast("Squad deleted.", "#ccffcc");
    this.scene.start("WarbandManagementScene");
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: "Arial",
      fontSize: "13px",
      color,
    });
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}




