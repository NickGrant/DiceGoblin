import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import UiButton from "../components/Button";

import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitListPanel, { type UnitListRowState } from "../components/UnitListPanel";

import { apiClient } from "../services/apiClient";
import type { TeamRecord, UnitRecord, TeamFormationCell } from "../types/ApiResponse";

type Cell = FormationCell;

const CELLS: Cell[] = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];

function emptyFormation(): FormationMap {
  return {
    A1: null, B1: null, C1: null,
    A2: null, B2: null, C2: null,
    A3: null, B3: null, C3: null,
  };
}

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private teams: TeamRecord[] = [];
  private activeTeam: TeamRecord | null = null;

  // Editing state
  private editUnitIds: Set<string> = new Set();
  private editFormation: FormationMap = emptyFormation();

  // Selection state
  private selectedUnitId: string | null = null;

  // UI
  private titleText?: Phaser.GameObjects.Text;
  private tipText1?: Phaser.GameObjects.Text;
  private tipText2?: Phaser.GameObjects.Text;

  private grid?: FormationGrid3x3;
  private unitPanel?: UnitListPanel;

  private saveButton?: UiButton;
  private clearButton?: UiButton;
  private createTeamButton?: UiButton;

  constructor() {
    super({ key: "WarbandManagementScene" });
  }

  create(): void {
    new BackgroundImage(this, "background_workbench");
    new HudPanel(this);
    new HomeButton(this, { x: 64, y: 52 }).setScale(0.5);

    this.loadingText = this.add
      .text(480, 270, "Loading warband…", {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#ffffff",
      })
      .setOrigin(0.5);

    void this.loadData();
  }

  // ----------------------------
  // Data load + UI build
  // ----------------------------

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = (profile.data.units ?? []) as UnitRecord[];
      this.teams = (profile.data.squads ?? []) as TeamRecord[];
      this.activeTeam = this.teams.find((t) => t.is_active) ?? this.teams[0] ?? null;

      this.loadingText?.destroy();
      this.loadingText = undefined;

      this.buildUi();
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
    }
  }

  private destroyUi(): void {
    this.toastText?.destroy();
    this.toastText = undefined;

    this.titleText?.destroy();
    this.tipText1?.destroy();
    this.tipText2?.destroy();

    this.grid?.destroy();
    this.grid = undefined;

    this.unitPanel?.destroy();
    this.unitPanel = undefined;

    this.saveButton?.destroy();
    this.saveButton = undefined;

    this.clearButton?.destroy();
    this.clearButton = undefined;

    this.createTeamButton?.destroy();
    this.createTeamButton = undefined;
  }

  private buildUi(): void {
    this.destroyUi();

    if (!this.activeTeam) {
      this.titleText = this.add
        .text(480, 160, "No teams found.", {
          fontFamily: "Arial",
          fontSize: "18px",
          color: "#ffffff",
        })
        .setOrigin(0.5);

      this.createTeamButton = new UiButton({
        scene: this,
        x: 480,
        y: 240,
        label: "Create Team",
        size: "small",
        onClick: () => void this.createTeam(),
      });

      return;
    }

    // Initialize local edit state from active team
    this.selectedUnitId = null;
    this.editUnitIds = new Set(this.activeTeam.unit_ids ?? []);
    this.editFormation = emptyFormation();

    for (const f of this.activeTeam.formation ?? []) {
      const cell = f.cell as Cell;
      if (CELLS.includes(cell)) {
        this.editFormation[cell] = f.unit_instance_id;
      }
    }

    // Header
    this.titleText = this.add
      .text(480, 92, `WARBAND: ${this.activeTeam.name}`, {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#ffffff",
      })
      .setOrigin(0.5);

    // Unit panel (left)
    this.unitPanel = new UnitListPanel({
      scene: this,
      x: 20,
      y: 120,
      width: 400,
      height: 420,
      title: "UNITS",
      units: this.units,
      getRowState: (u) => this.getUnitRowState(u),
      onUnitClick: (u) => this.handleUnitClick(u),
    });

    // Formation grid (right)
    this.grid = new FormationGrid3x3({
      scene: this,
      x: 540,
      y: 170,
      formation: this.editFormation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => this.getCellLabel(cell, unitId),
      onCellClick: (cell) => this.handleCellClick(cell),
      onCellDoubleClick: (cell) => this.handleCellDoubleClick(cell),
    });

    // Buttons
    this.clearButton = new UiButton({
      scene: this,
      x: 700,
      y: 92,
      label: "Clear Cell",
      size: "tiny",
      enabled: false,
      onClick: () => this.clearSelectedCell(),
    });

    this.saveButton = new UiButton({
      scene: this,
      x: 860,
      y: 92,
      label: "Save",
      size: "tiny",
      enabled: true,
      onClick: () => void this.saveTeam(),
    });

    // Tips
    this.tipText1 = this.add.text(40, 550, "Tip: click a grid cell, then click a unit.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
    });

    this.tipText2 = this.add.text(540, 550, "Tip: double-click an occupied cell to clear.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
    });

    this.refreshDerivedUiState();
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    this.toastText = this.add
      .text(480, 610, message, {
        fontFamily: "Arial",
        fontSize: "12px",
        color,
      })
      .setOrigin(0.5);

    // Auto-clear after a moment
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }

  // ----------------------------
  // Derived state helpers
  // ----------------------------

  private getSelectedCell(): Cell | null {
    return this.grid?.getSelectedCell() ?? null;
  }

  private isUnitPlaced(unitId: string): boolean {
    return Object.values(this.editFormation).includes(unitId);
  }

  private getCellLabel(cell: Cell, unitId: string | null): string {
    if (!unitId) return `${cell}\n(Empty)`;
    const u = this.units.find((x) => x.id === unitId);
    return `${cell}\n${u ? u.name : `Unit ${unitId}`}`;
  }

  private getUnitRowState(u: UnitRecord): UnitListRowState {
    const inTeam = this.editUnitIds.has(u.id);
    const placed = this.isUnitPlaced(u.id);
    const selected = this.selectedUnitId === u.id;

    return {
      highlighted: inTeam,
      outlined: placed,
      disabled: false,
      badgeText: selected ? "SELECTED" : placed ? "PLACED" : null,
    };
  }

  private refreshDerivedUiState(): void {
    // Update list row styling
    this.unitPanel?.refreshRowStates();

    // Update grid labels/selection visuals
    this.grid?.setFormation(this.editFormation);

    // Clear button enabled when selected cell is occupied
    const cell = this.getSelectedCell();
    const occupied = cell ? this.editFormation[cell] !== null : false;
    this.clearButton?.setEnabled(!!cell && occupied);
  }

  // ----------------------------
  // Interaction handlers
  // ----------------------------

  private handleCellClick(cell: Cell): void {
    // If user has a unit armed, place it
    if (this.selectedUnitId) {
      this.placeUnitIntoCell(this.selectedUnitId, cell);
      this.selectedUnitId = null;
    }
    this.refreshDerivedUiState();
  }

  private handleCellDoubleClick(cell: Cell): void {
    // Double-click clears (only if occupied and not in "placing mode")
    if (this.selectedUnitId) return;
    if (this.editFormation[cell] === null) return;

    this.editFormation[cell] = null;
    this.refreshDerivedUiState();
  }

  private handleUnitClick(u: UnitRecord): void {
    const cell = this.getSelectedCell();

    if (cell) {
      // place immediately if a cell is selected
      this.placeUnitIntoCell(u.id, cell);
      this.selectedUnitId = null;
    } else {
      // arm unit for placement
      this.selectedUnitId = u.id;
    }

    this.refreshDerivedUiState();
  }

  private placeUnitIntoCell(unitId: string, cell: Cell): void {
    // Ensure membership includes the unit (bench allowed; we keep membership even if unplaced)
    this.editUnitIds.add(unitId);

    // Uniqueness: unit can only be in one cell
    for (const c of CELLS) {
      if (this.editFormation[c] === unitId) this.editFormation[c] = null;
    }

    // Place into target cell
    this.editFormation[cell] = unitId;
  }

  private clearSelectedCell(): void {
    const cell = this.getSelectedCell();
    if (!cell) return;
    if (this.editFormation[cell] === null) return;

    this.editFormation[cell] = null;
    this.refreshDerivedUiState();
  }

  // ----------------------------
  // Persistence
  // ----------------------------

  private async createTeam(): Promise<void> {
    const res = await apiClient.createTeam("My Warband", true);
    if (!res.ok) {
      this.showToast(`Create failed: ${res.error.message}`);
      return;
    }
    await this.loadData();
  }

  private async saveTeam(): Promise<void> {
    if (!this.activeTeam) return;

    const formation: TeamFormationCell[] = CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.editFormation[cell] ?? null,
    }));

    const res = await apiClient.updateTeam(this.activeTeam.id, {
      unit_ids: Array.from(this.editUnitIds),
      formation,
    });

    if (!res.ok) {
      this.showToast(`Save failed: ${res.error.message}`);
      return;
    }

    this.showToast("Saved!", "#ccffcc");
    await this.loadData();
  }
}
