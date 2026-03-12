import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ActionButton from "../components/clickable-panel/ActionButton";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import DiceCardGrid from "../components/DiceCardGrid";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { adaptDiceDetails, adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import type { UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

const FRAME_BODY_TOP_OFFSET = 74;
const FRAME_BODY_BOTTOM_PADDING = 18;
const ACTION_BODY_TOP_OFFSET = 72;

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
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Manage Units",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Manage Inventory",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    const buttonX = layout.buttons.x + 10;
    const inRestContext = this.runId !== "" && this.nodeId !== "";
    const bodyTop = layout.content.y + FRAME_BODY_TOP_OFFSET;
    const bodyHeight = Math.max(240, layout.content.height - FRAME_BODY_TOP_OFFSET - FRAME_BODY_BOTTOM_PADDING);
    const unitPanelWidth = Math.max(250, Math.floor(layout.content.width * 0.34));
    const panelGap = 16;
    const dicePanelX = layout.content.x + unitPanelWidth + panelGap;
    const dicePanelWidth = Math.max(280, layout.content.width - unitPanelWidth - panelGap);

    this.add.text(layout.content.x + 16, bodyTop, inRestContext
      ? "Rest inventory context"
      : "Between-run inventory context", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#ffffff",
    }).setOrigin(0, 0);

    this.add.text(dicePanelX, bodyTop, inRestContext
      ? `Rest context active (run ${this.runId}, node ${this.nodeId}).`
      : "Out-of-run context.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "14px",
      color: "#dddddd",
    }).setOrigin(0, 0);

    this.add.text(dicePanelX, bodyTop + 22, inRestContext
      ? "Dice changes from this screen should be validated against rest-context backend rules."
      : "Dice changes are available here between runs.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#bbbbbb",
      wordWrap: { width: dicePanelWidth - 12 },
    }).setOrigin(0, 0);

    if (inRestContext || this.returnScene !== "HomeScene") {
      new ActionButton({
        scene: this,
        x: buttonX,
        y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
        label: inRestContext ? "Back to Rest" : "Back",
        onClick: () => this.scene.start(this.returnScene, {
          runId: this.runId,
          nodeId: this.nodeId,
          unitId: this.preferredUnitId ?? undefined,
        }),
      });
    }

    this.statusText = this.add.text(dicePanelX, bodyTop + 48, "Loading inventory...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#dddddd",
      wordWrap: { width: dicePanelWidth - 12 },
    }).setOrigin(0, 0);

    new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 84,
      label: "Equip Selected",
      onClick: () => void this.equipSelected(),
    });
    new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 140,
      label: "Unequip Selected",
      onClick: () => void this.unequipSelected(),
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    const profile = await apiClient.getProfile({ force: true }).catch(() => {
      const debugConfig = getDebugSceneConfig();
      if (!debugConfig.enabled) {
        throw new Error("Failed to fetch");
      }
      return getDebugProfileFixture();
    });
    if (!profile.ok) {
      this.setStatus(`Profile unavailable: ${profile.error.message}`);
      markDebugSceneReady(this, { state: "error" });
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
    markDebugSceneReady(this, {
      units: this.units.length,
      dice: this.dice.length,
      mutationAllowed: this.mutationAllowed,
    });
  }

  private renderUnitPanel(): void {
    this.unitPanel?.destroy();
    const layout = getPageLayout(this);
    const bodyTop = layout.content.y + FRAME_BODY_TOP_OFFSET;
    const bodyHeight = Math.max(240, layout.content.height - FRAME_BODY_TOP_OFFSET - FRAME_BODY_BOTTOM_PADDING);
    const unitPanelWidth = Math.max(250, Math.floor(layout.content.width * 0.34));
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: layout.content.x,
      y: bodyTop,
      width: unitPanelWidth,
      height: bodyHeight,
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
    const bodyTop = layout.content.y + FRAME_BODY_TOP_OFFSET;
    const bodyHeight = Math.max(240, layout.content.height - FRAME_BODY_TOP_OFFSET - FRAME_BODY_BOTTOM_PADDING);
    const unitPanelWidth = Math.max(250, Math.floor(layout.content.width * 0.34));
    const panelGap = 16;
    const dicePanelX = layout.content.x + unitPanelWidth + panelGap;
    const dicePanelWidth = Math.max(280, layout.content.width - unitPanelWidth - panelGap);
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
      x: dicePanelX,
      y: bodyTop + 66,
      width: dicePanelWidth,
      height: Math.max(220, bodyHeight - 66),
      title: "DICE",
      dice: this.dice,
      selectedDiceId: this.selectedDiceId,
      maxVisibleCards: 3,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.diceGrid?.setSelectedDiceId(die.id);
      },
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
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    }).setOrigin(0, 0);
    this.time.delayedCall(2000, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}









