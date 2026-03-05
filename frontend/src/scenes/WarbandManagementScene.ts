import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import ActionButton from "../components/clickable-panel/ActionButton";

import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitListPanel, { type UnitListRowState } from "../components/UnitListPanel";

import { apiClient } from "../services/apiClient";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { TeamRecord, UnitRecord, TeamFormationCell } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

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
  private squads: TeamRecord[] = [];
  private activeSquad: TeamRecord | null = null;
  private hasActiveRun = false;

  // Editing state
  private editUnitIds: Set<string> = new Set();
  private editFormation: FormationMap = emptyFormation();

  // Selection state
  private selectedUnitId: string | null = null;
  private promotionPrimaryId: string | null = null;
  private promotionSecondaryIds: string[] = [];

  // UI
  private titleText?: Phaser.GameObjects.Text;
  private tipText1?: Phaser.GameObjects.Text;
  private tipText2?: Phaser.GameObjects.Text;

  private grid?: FormationGrid3x3;
  private unitPanel?: UnitListPanel;

  private saveButton?: ActionButton;
  private clearButton?: ActionButton;
  private createSquadButton?: ActionButton;
  private setPrimaryButton?: ActionButton;
  private addSecondaryButton?: ActionButton;
  private clearPromotionButton?: ActionButton;
  private promoteButton?: ActionButton;
  private promotionStatusText?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "WarbandManagementScene" });
  }

  create(): void {
    new BackgroundImage(this, "background_workbench");
    new HudPanel(this);
    const layout = getPageLayout(this);
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    this.loadingText = this.add
      .text(layout.content.x + 16, layout.content.y + 120, "Loading warband...", {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    void this.loadData();
  }

  // ----------------------------
  // Data load + UI build
  // ----------------------------

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      this.squads = (profile.data.squads ?? []) as TeamRecord[];
      this.activeSquad = this.squads.find((t) => t.is_active) ?? this.squads[0] ?? null;
      this.hasActiveRun = profile.data.active_run !== null;

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

    this.createSquadButton?.destroy();
    this.createSquadButton = undefined;

    this.setPrimaryButton?.destroy();
    this.setPrimaryButton = undefined;
    this.addSecondaryButton?.destroy();
    this.addSecondaryButton = undefined;
    this.clearPromotionButton?.destroy();
    this.clearPromotionButton = undefined;
    this.promoteButton?.destroy();
    this.promoteButton = undefined;
    this.promotionStatusText?.destroy();
    this.promotionStatusText = undefined;
  }

  private buildUi(): void {
    this.destroyUi();
    const layout = getPageLayout(this);
    const actionButtonX = layout.buttons.x + 10;

    if (!this.activeSquad) {
      this.titleText = this.add
        .text(layout.content.x + 16, layout.content.y + 16, "No squads found.", {
          fontFamily: "Arial",
          fontSize: "18px",
          color: "#ffffff",
        })
        .setOrigin(0, 0);

      this.createSquadButton = new ActionButton({
        scene: this,
        x: actionButtonX,
        y: layout.buttons.y + 64,
        label: "Create Squad",
        onClick: () => void this.createSquad(),
      });

      return;
    }

    // Initialize local edit state from active squad
    this.selectedUnitId = null;
    this.promotionPrimaryId = null;
    this.promotionSecondaryIds = [];
    this.editUnitIds = new Set(this.activeSquad.unit_ids ?? []);
    this.editFormation = emptyFormation();

    for (const f of this.activeSquad.formation ?? []) {
      const cell = f.cell as Cell;
      if (CELLS.includes(cell)) {
        this.editFormation[cell] = f.unit_instance_id;
      }
    }

    // Header
    this.titleText = this.add
      .text(layout.content.x + 16, layout.content.y - 34, `WARBAND: ${this.activeSquad.name}`, {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    // Unit panel (left)
    this.unitPanel = new UnitListPanel({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
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
      x: layout.content.x + 520,
      y: layout.content.y + 48,
      formation: this.editFormation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => this.getCellLabel(cell, unitId),
      onCellClick: (cell) => this.handleCellClick(cell),
      onCellDoubleClick: (cell) => this.handleCellDoubleClick(cell),
    });

    // Buttons
    this.clearButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 24,
      label: "Clear Cell",
      enabled: false,
      onClick: () => this.clearSelectedCell(),
    });

    this.saveButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 74,
      label: "Save",
      enabled: true,
      onClick: () => void this.saveTeam(),
    });

    this.setPrimaryButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 154,
      label: "Set Primary",
      enabled: true,
      onClick: () => this.setPromotionPrimaryFromSelection(),
    });
    this.addSecondaryButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 204,
      label: "Add Secondary",
      enabled: true,
      onClick: () => this.addPromotionSecondaryFromSelection(),
    });
    this.clearPromotionButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 254,
      label: "Clear Promo",
      enabled: true,
      onClick: () => this.clearPromotionSelection(),
    });
    this.promoteButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 304,
      label: "Promote",
      enabled: false,
      onClick: () => void this.promoteSelectedUnit(),
    });
    this.promotionStatusText = this.add.text(layout.buttons.x + 8, layout.buttons.y + 340, "", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
      wordWrap: { width: layout.buttons.width - 16 },
    });

    // Tips
    this.tipText1 = this.add.text(layout.content.x + 16, layout.content.y + 430, "Tip: click a grid cell, then click a unit.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
    });

    this.tipText2 = this.add.text(layout.content.x + 520, layout.content.y + 430, "Tip: double-click an occupied cell to clear.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
    });

    this.refreshDerivedUiState();
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add
      .text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
        fontFamily: "Arial",
        fontSize: "12px",
        color,
      })
      .setOrigin(0, 0);

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
    this.promoteButton?.setEnabled(
      !this.hasActiveRun && !!this.promotionPrimaryId && this.promotionSecondaryIds.length === 2
    );
    this.promotionStatusText?.setText(this.buildPromotionStatusText());
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

  private async createSquad(): Promise<void> {
    const res = await apiClient.createTeam("My Warband", true);
    if (!res.ok) {
      this.showToast(`Create failed: ${res.error.message}`);
      return;
    }
    await this.loadData();
  }

  private async saveTeam(): Promise<void> {
    if (!this.activeSquad) return;

    const formation: TeamFormationCell[] = CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.editFormation[cell] ?? null,
    }));

    try {
      const res = await apiClient.updateTeam(this.activeSquad.id, {
        unit_ids: Array.from(this.editUnitIds),
        formation,
      });

      if (!res.ok) {
        this.showToast(`Save failed: ${res.error.message}`);
        return;
      }

      this.showToast("Saved!", "#ccffcc");
      await this.loadData();
    } catch (e) {
      const message = e instanceof Error ? e.message : "Unexpected save error.";
      this.showToast(`Save failed: ${message}`);
    }
  }

  private setPromotionPrimaryFromSelection(): void {
    if (!this.selectedUnitId) {
      this.showToast("Select a unit first.");
      return;
    }
    this.promotionPrimaryId = this.selectedUnitId;
    this.promotionSecondaryIds = this.promotionSecondaryIds.filter((id) => id !== this.promotionPrimaryId);
    this.refreshDerivedUiState();
  }

  private addPromotionSecondaryFromSelection(): void {
    if (!this.selectedUnitId) {
      this.showToast("Select a unit first.");
      return;
    }
    if (!this.promotionPrimaryId) {
      this.showToast("Set a primary unit first.");
      return;
    }
    if (this.selectedUnitId === this.promotionPrimaryId) {
      this.showToast("Primary unit cannot be a secondary.");
      return;
    }
    if (!this.isPromotionCompatible(this.promotionPrimaryId, this.selectedUnitId)) {
      this.showToast("Secondary must match primary type/tier and be max level.");
      return;
    }
    if (this.promotionSecondaryIds.includes(this.selectedUnitId)) {
      return;
    }
    if (this.promotionSecondaryIds.length === 2) {
      this.promotionSecondaryIds.shift();
    }
    this.promotionSecondaryIds.push(this.selectedUnitId);
    this.refreshDerivedUiState();
  }

  private clearPromotionSelection(): void {
    this.promotionPrimaryId = null;
    this.promotionSecondaryIds = [];
    this.refreshDerivedUiState();
  }

  private async promoteSelectedUnit(): Promise<void> {
    if (this.hasActiveRun) {
      this.showToast("Promotion from Warband is disabled while a run is active.");
      return;
    }
    if (!this.promotionPrimaryId || this.promotionSecondaryIds.length !== 2) {
      this.showToast("Set one primary and two secondary units.");
      return;
    }
    const [secondaryA, secondaryB] = this.promotionSecondaryIds as [string, string];
    const res = await apiClient.promoteUnit(
      this.promotionPrimaryId,
      [secondaryA, secondaryB]
    );
    if (!res.ok) {
      this.showToast(`Promote failed: ${res.error.message}`);
      return;
    }
    this.showToast("Promotion applied.", "#ccffcc");
    this.clearPromotionSelection();
    await this.loadData();
  }

  private isPromotionCompatible(primaryId: string, secondaryId: string): boolean {
    const primary = this.units.find((u) => u.id === primaryId);
    const secondary = this.units.find((u) => u.id === secondaryId);
    if (!primary || !secondary) return false;
    if ((primary.unit_type_id ?? null) !== (secondary.unit_type_id ?? null)) return false;
    if ((primary.tier ?? 1) !== (secondary.tier ?? 1)) return false;
    const maxPrimary = typeof primary.max_level === "number" ? primary.max_level : null;
    const maxSecondary = typeof secondary.max_level === "number" ? secondary.max_level : null;
    if (maxPrimary !== null && primary.level < maxPrimary) return false;
    if (maxSecondary !== null && secondary.level < maxSecondary) return false;
    return true;
  }

  private buildPromotionStatusText(): string {
    const p = this.promotionPrimaryId ? `Primary: ${this.unitName(this.promotionPrimaryId)}` : "Primary: (none)";
    const s1 = this.promotionSecondaryIds[0]
      ? this.unitName(this.promotionSecondaryIds[0])
      : "(none)";
    const s2 = this.promotionSecondaryIds[1]
      ? this.unitName(this.promotionSecondaryIds[1])
      : "(none)";
    const gate = this.hasActiveRun
      ? "Promotion disabled here while a run is active."
      : "Promotion available between runs.";
    return `${p}\nSecondaries: ${s1}, ${s2}\n${gate}`;
  }

  private unitName(unitId: string): string {
    const unit = this.units.find((u) => u.id === unitId);
    return unit ? unit.name : `Unit ${unitId}`;
  }
}




