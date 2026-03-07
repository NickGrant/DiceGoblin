import BackgroundImage from "../components/BackgroundImage";
import HudPanel from "../components/HudPanel";
import ActionButton from "../components/clickable-panel/ActionButton";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import DiceCardGrid from "../components/DiceCardGrid";
import { adaptDiceDetails, adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import type { UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import HomeCornerButton from "../components/navigation/HomeCornerButton";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";


export default class DiceInventoryScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private returnScene = "HomeScene";
  private preferredUnitId: string | null = null;
  private mutationAllowed = true;

  private units: UnitRecord[] = [];
  private dice: DiceDetailsViewModel[] = [];
  private selectedUnitId: string | null = null;
  private selectedDiceId: string | null = null;
  private unitPanel?: UnitCardGrid;
  private diceGrid?: DiceCardGrid;
  private statusText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "DiceInventoryScene" });
  }

  init(data: { runId?: string; nodeId?: string; returnScene?: string; unitId?: string }): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    this.returnScene = String(data?.returnScene ?? "HomeScene");
    this.preferredUnitId = data?.unitId ? String(data.unitId) : null;
  }

  create(): void {
    new BackgroundImage(this);
    new HudPanel(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Manage Units",
      bodyColor: 0x00f6ff,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Manage Inventory",
      bodyColor: 0x00ff72,
    });
    actionsFrame.setDepth(-800);
    const buttonX = layout.buttons.x + 10;
    new HomeCornerButton({
      scene: this,
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    const inRestContext = this.runId !== "" && this.nodeId !== "";
    this.add.text(layout.content.x + 410, layout.content.y - 34, "DICE INVENTORY", {
      fontFamily: "Arial",
      fontSize: "22px",
      color: "#ffffff",
    }).setOrigin(0, 0);

    this.add.text(layout.content.x + 410, layout.content.y + 10, inRestContext
      ? `Rest context active (run ${this.runId}, node ${this.nodeId}).`
      : "Out-of-run context.", {
      fontFamily: "Arial",
      fontSize: "14px",
      color: "#dddddd",
    }).setOrigin(0, 0);

    this.add.text(layout.content.x + 410, layout.content.y + 42, inRestContext
      ? "Dice changes from this screen should be validated against rest-context backend rules."
      : "Dice changes are available here between runs.", {
      fontFamily: "Arial",
      fontSize: "13px",
      color: "#bbbbbb",
      align: "center",
      wordWrap: { width: layout.content.width - 40 },
    }).setOrigin(0, 0);

    if (inRestContext || this.returnScene !== "HomeScene") {
      new ActionButton({
        scene: this,
        x: buttonX,
        y: layout.buttons.y + 24,
        label: inRestContext ? "Back to Rest" : "Back",
        onClick: () => this.scene.start(this.returnScene, {
          runId: this.runId,
          nodeId: this.nodeId,
          unitId: this.preferredUnitId ?? undefined,
        }),
      });
    }

    this.statusText = this.add.text(layout.content.x + 410, layout.content.y + 68, "Loading inventory...", {
      fontFamily: "Arial",
      fontSize: "13px",
      color: "#dddddd",
      align: "center",
      wordWrap: { width: layout.content.width - 40 },
    }).setOrigin(0, 0);

    new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + 244,
      label: "Equip Selected",
      onClick: () => void this.equipSelected(),
    });
    new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + 304,
      label: "Unequip Selected",
      onClick: () => void this.unequipSelected(),
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    const profile = await apiClient.getProfile({ force: true });
    if (!profile.ok) {
      this.setStatus(`Profile unavailable: ${profile.error.message}`);
      return;
    }
    this.units = adaptUnitRecords(profile.data.units ?? []);
    this.dice = adaptDiceDetails(profile.data.dice ?? [], profile.data.units ?? []);
    this.mutationAllowed = this.runId !== "" && this.nodeId !== "" ? true : profile.data.active_run === null;
    if (this.selectedUnitId === null && this.units.length > 0) {
      const preferred = this.preferredUnitId ? this.units.find((u) => u.id === this.preferredUnitId) : null;
      this.selectedUnitId = preferred?.id ?? this.units[0]?.id ?? null;
    }
    this.renderUnitPanel();
    this.renderDiceGrid();
    const modeLabel = this.mutationAllowed
      ? "Dice actions are enabled."
      : "Active run detected outside rest context: dice actions disabled.";
    this.setStatus(modeLabel);
  }

  private renderUnitPanel(): void {
    this.unitPanel?.destroy();
    const layout = getPageLayout(this);
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: layout.content.x,
      y: layout.content.y + 90,
      width: 380,
      height: 350,
      title: "UNITS",
      units: this.units,
      onUnitClick: (u) => {
        this.selectedUnitId = u.id;
        this.unitPanel?.refreshCardStates();
        this.renderDiceGrid();
      },
      getCardState: (u) => this.getUnitRowState(u),
      maxVisibleCards: 6,
    });
  }

  private getUnitRowState(unit: UnitRecord): UnitCardState {
    return {
      highlighted: unit.id === this.selectedUnitId,
      disabled: !this.mutationAllowed,
      badgeText: unit.id === this.selectedUnitId ? "SELECTED" : null,
    };
  }

  private renderDiceGrid(): void {
    const layout = getPageLayout(this);
    if (this.selectedDiceId === null && this.dice.length > 0) {
      this.selectedDiceId = this.dice[0]?.id ?? null;
    }
    const selectedIndex = this.dice.findIndex((d) => d.id === this.selectedDiceId);
    const safeSelected = selectedIndex >= 0 ? selectedIndex : 0;
    if (this.dice.length > 0) {
      const selectedDie = this.dice[safeSelected];
      this.selectedDiceId = selectedDie ? selectedDie.id : null;
    }
    this.diceGrid?.destroy();
    this.diceGrid = new DiceCardGrid({
      scene: this,
      x: layout.content.x + 410,
      y: layout.content.y + 96,
      width: Math.max(260, layout.content.width - 430),
      height: 344,
      title: "DICE",
      dice: this.dice,
      selectedDiceId: this.selectedDiceId,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.diceGrid?.setSelectedDiceId(die.id);
      },
      maxVisibleCards: 6,
    });
  }

  private async equipSelected(): Promise<void> {
    if (!this.mutationAllowed) {
      this.showToast("Equip blocked outside rest while run is active.");
      return;
    }
    if (!this.selectedUnitId || !this.selectedDiceId) {
      this.showToast("Select a unit and die.");
      return;
    }
    const res = await apiClient.equipDice(
      this.selectedUnitId,
      this.selectedDiceId,
      this.runId && this.nodeId ? { runId: this.runId, nodeId: this.nodeId } : undefined
    );
    if (!res.ok) {
      this.showToast(`Equip failed: ${res.error.message}`);
      return;
    }
    this.showToast("Die equipped.", "#ccffcc");
    await this.loadData();
  }

  private async unequipSelected(): Promise<void> {
    if (!this.mutationAllowed) {
      this.showToast("Unequip blocked outside rest while run is active.");
      return;
    }
    if (!this.selectedUnitId || !this.selectedDiceId) {
      this.showToast("Select a unit and die.");
      return;
    }
    const res = await apiClient.unequipDice(
      this.selectedUnitId,
      this.selectedDiceId,
      this.runId && this.nodeId ? { runId: this.runId, nodeId: this.nodeId } : undefined
    );
    if (!res.ok) {
      this.showToast(`Unequip failed: ${res.error.message}`);
      return;
    }
    this.showToast("Die unequipped.", "#ccffcc");
    await this.loadData();
  }

  private setStatus(message: string): void {
    this.statusText?.setText(message);
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 410, layout.content.y + layout.content.height - 24, message, {
      fontFamily: "Arial",
      fontSize: "13px",
      color,
    }).setOrigin(0, 0);
    this.time.delayedCall(2000, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}



