import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ActionButton from "../components/clickable-panel/ActionButton";
import FormationGrid3x3, { type FormationCell, type FormationMap } from "../components/FormationGrid3x3";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { TeamFormationCell, UnitRecord, RestRunUnitState } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

type Cell = FormationCell;
const CELLS: Cell[] = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];

function emptyFormation(): FormationMap {
  return { A1: null, B1: null, C1: null, A2: null, B2: null, C2: null, A3: null, B3: null, C3: null };
}

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
  private promotionPrimaryId: string | null = null;
  private promotionSecondaryIds: string[] = [];

  private grid?: FormationGrid3x3;
  private unitPanel?: UnitCardGrid;
  private applyButton?: ActionButton;
  private finalizeButton?: ActionButton;
  private setPrimaryButton?: ActionButton;
  private addSecondaryButton?: ActionButton;
  private clearPromotionButton?: ActionButton;
  private promoteButton?: ActionButton;
  private promotionStatusText?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "RestManagementScene" });
  }

  init(data: { runId?: string; nodeId?: string }): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
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
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y - 56, "Preparing rest...", {
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
        apiClient.getProfile({ force: true }),
        apiClient.openRest(this.runId, this.nodeId),
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
    } catch (e) {
      this.loadingText?.setText(`Rest unavailable.\n${(e as Error).message}`);
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const actionButtonX = layout.buttons.x + 10;
    this.add.text(layout.content.x + 16, layout.content.y - 56, "REST MANAGEMENT", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "22px",
      color: "#ffffff",
    }).setOrigin(0, 0);

    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: 400,
      height: 420,
      title: "RUN SQUAD",
      units: this.units,
      getCardState: (u) => this.getUnitRowState(u),
      onUnitClick: (u) => this.handleUnitClick(u),
      maxVisibleCards: 6,
    });

    this.grid = new FormationGrid3x3({
      scene: this,
      x: layout.content.x + 520,
      y: layout.content.y + 50,
      formation: this.editFormation,
      selectedCell: null,
      getCellLabel: (cell, unitId) => this.getCellLabel(cell, unitId),
      onCellClick: (cell) => this.handleCellClick(cell),
      onCellDoubleClick: (cell) => this.handleCellDoubleClick(cell),
    });

    this.applyButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 24,
      label: "Apply State",
      onClick: () => void this.applyRestState(),
    });

    this.finalizeButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 74,
      label: "Finalize Rest",
      onClick: () => void this.finalizeRest(),
    });

    this.add.text(layout.content.x + 10, layout.content.y + 432, "Tip: changes apply to run snapshot and saved squad together.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#dddddd",
    });
    this.add.text(layout.content.x + 10, layout.content.y + 447, "Use promotion controls for max-level units while rest is open.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#bbbbbb",
    });

    new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 124,
      label: "Manage Dice",
      onClick: () => this.scene.start("DiceInventoryScene", {
        runId: this.runId,
        nodeId: this.nodeId,
        returnScene: "RestManagementScene",
      }),
    });
    this.setPrimaryButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 174,
      label: "Set Primary",
      enabled: true,
      onClick: () => this.setPromotionPrimaryFromSelection(),
    });
    this.addSecondaryButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 224,
      label: "Add Secondary",
      enabled: true,
      onClick: () => this.addPromotionSecondaryFromSelection(),
    });
    this.clearPromotionButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 274,
      label: "Clear Promo",
      enabled: true,
      onClick: () => this.clearPromotionSelection(),
    });
    this.promoteButton = new ActionButton({
      scene: this,
      x: actionButtonX,
      y: layout.buttons.y + 324,
      label: "Promote",
      enabled: false,
      onClick: () => void this.promoteSelectedUnit(),
    });
    this.promotionStatusText = this.add.text(layout.buttons.x + 8, layout.buttons.y + 350, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#dddddd",
      wordWrap: { width: layout.buttons.width - 16 },
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
    this.promoteButton?.setEnabled(
      !this.finalized && !!this.promotionPrimaryId && this.promotionSecondaryIds.length === 2
    );
    this.promotionStatusText?.setText(this.buildPromotionStatusText());
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

    const progression = res.data.progression ?? [];
    const progressionLines = progression.length > 0
      ? progression.map((p) => `Unit ${p.id}: Lv ${p.from_level} -> ${p.to_level}`)
      : ["No level/promotion changes."];

    const healingLines = this.runUnitState.map((s) => {
      const before = this.baselineRunUnitHp.get(s.unit_instance_id) ?? s.hp;
      const healed = Math.max(0, s.hp - before);
      return `Unit ${s.unit_instance_id}: healed +${healed} (current HP ${s.hp})`;
    });
    const summary = [
      "END OF REST SUMMARY",
      "",
      "Progression:",
      ...progressionLines,
      "",
      "Healing:",
      ...(healingLines.length > 0 ? healingLines : ["No healing changes."]),
      "",
      "Press Continue to return to Run Map.",
    ].join("\n");

    this.summaryText?.destroy();
    const layout = getPageLayout(this);
    this.summaryText = this.add.text(layout.content.x + 10, layout.content.y + 460, summary, {
      fontFamily: "monospace",
      fontSize: "13px",
      color: "#f5f5f5",
      wordWrap: { width: layout.content.width - 16 },
    });

    new ActionButton({
      scene: this,
      x: layout.buttons.x + 10,
      y: layout.buttons.y + 374,
      label: "Continue",
      onClick: () => this.scene.start("MapExplorationScene"),
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

  private setPromotionPrimaryFromSelection(): void {
    if (!this.selectedUnitId) {
      this.showToast("Select a unit first.");
      return;
    }
    this.promotionPrimaryId = this.selectedUnitId;
    this.promotionSecondaryIds = this.promotionSecondaryIds.filter((id) => id !== this.promotionPrimaryId);
    this.refreshUi();
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
    if (this.runUnitState.some((s) => s.unit_instance_id === this.selectedUnitId)) {
      this.showToast("Secondary units from active run snapshot cannot be consumed.");
      return;
    }
    if (this.promotionSecondaryIds.includes(this.selectedUnitId)) {
      return;
    }
    if (this.promotionSecondaryIds.length === 2) {
      this.promotionSecondaryIds.shift();
    }
    this.promotionSecondaryIds.push(this.selectedUnitId);
    this.refreshUi();
  }

  private clearPromotionSelection(): void {
    this.promotionPrimaryId = null;
    this.promotionSecondaryIds = [];
    this.refreshUi();
  }

  private async promoteSelectedUnit(): Promise<void> {
    if (this.finalized) {
      this.showToast("Rest already finalized.");
      return;
    }
    if (!this.promotionPrimaryId || this.promotionSecondaryIds.length !== 2) {
      this.showToast("Set one primary and two secondary units.");
      return;
    }
    const [secondaryA, secondaryB] = this.promotionSecondaryIds as [string, string];
    const res = await apiClient.promoteUnit(
      this.promotionPrimaryId,
      [secondaryA, secondaryB],
      { runId: this.runId, nodeId: this.nodeId }
    );
    if (!res.ok) {
      this.showToast(`Promote failed: ${res.error.message}`);
      return;
    }
    this.showToast("Promotion applied.", "#ccffcc");
    this.scene.restart({ runId: this.runId, nodeId: this.nodeId });
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
    return `${p}\nSecondaries: ${s1}, ${s2}\nPromotion available while rest is open.`;
  }

  private unitName(unitId: string): string {
    const unit = this.units.find((u) => u.id === unitId);
    return unit ? unit.name : `Unit ${unitId}`;
  }
}









