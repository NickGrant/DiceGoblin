import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ActionButton from "../components/clickable-panel/ActionButton";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import DiceCardGrid from "../components/DiceCardGrid";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
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
const ACTION_BODY_TOP_OFFSET = 76;

export default class UnitDetailsScene extends Phaser.Scene {
  private unitId = "";
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private detailsText?: Phaser.GameObjects.Text;
  private statusText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private activeRun = false;
  private unit: UnitRecord | null = null;
  private selectedSecondaryIds: string[] = [];
  private secondaryPanel?: UnitCardGrid;
  private dicePanel?: DiceCardGrid;
  private promoteButton?: ActionButton;
  private equipDiceButton?: ActionButton;
  private unequipDiceButton?: ActionButton;

  constructor() {
    super({ key: "UnitDetailsScene" });
  }

  init(data: { unitId?: string }): void {
    this.unitId = String(data?.unitId ?? "");
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
      title: "Unit Details",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Unit Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading unit details...", {
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
      this.activeRun = profile.data.active_run !== null;
      this.unit = this.units.find((u) => u.id === this.unitId) ?? this.units[0] ?? null;
      if (!this.unit) throw new Error("No units found.");
      this.unitId = this.unit.id;

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.buildUi(profile.data.dice ?? [], profile.data.units ?? []);
      markDebugSceneReady(this, {
        unitId: this.unitId,
        activeRun: this.activeRun,
      });
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(rawDice: unknown[], rawUnits: unknown[]): void {
    const layout = getPageLayout(this);
    const buttonX = layout.buttons.x + 10;
    const bodyTop = layout.content.y + FRAME_BODY_TOP_OFFSET;
    const bodyHeight = Math.max(250, layout.content.height - FRAME_BODY_TOP_OFFSET - FRAME_BODY_BOTTOM_PADDING);
    const secondaryPanelWidth = 260;
    const secondaryPanelX = layout.content.x + layout.content.width - 12 - secondaryPanelWidth;
    const detailsWidth = Math.max(260, secondaryPanelX - layout.content.x - 24);
    if (!this.unit) return;

    const diceVm = adaptDiceDetails(rawDice as any, rawUnits as any);
    this.dice = diceVm;
    const equipped = diceVm.filter((d) => d.equipped?.unitId === this.unit?.id);
    const selectedStillExists = this.selectedDiceId
      ? diceVm.some((die) => die.id === this.selectedDiceId)
      : false;
    if (!selectedStillExists) {
      this.selectedDiceId = equipped[0]?.id ?? diceVm[0]?.id ?? null;
    }
    const xp = typeof this.unit.xp === "number" ? this.unit.xp : 0;
    const max = typeof this.unit.max_level === "number" ? this.unit.max_level : "?";
    const tier = typeof this.unit.tier === "number" ? this.unit.tier : 1;

    this.detailsText?.destroy();
    this.detailsText = this.add.text(layout.content.x + 16, bodyTop, [
      `UNIT: ${this.unit.name}`,
      `Level: ${this.unit.level} / ${max}`,
      `XP: ${xp}`,
      `Tier: ${tier}`,
      "",
      `Equipped Dice (${equipped.length}):`,
      ...(equipped.length > 0 ? equipped.map((d) => `- ${d.displayName}`) : ["- None"]),
    ].join("\n"), {
      fontFamily: "monospace",
      fontSize: "15px",
      color: "#f5f5f5",
      wordWrap: { width: 420 },
    });

    this.secondaryPanel?.destroy();
    this.secondaryPanel = new UnitCardGrid({
      scene: this,
      x: secondaryPanelX,
      y: bodyTop,
      width: secondaryPanelWidth,
      height: bodyHeight,
      title: "PROMOTION SECONDARIES",
      units: this.units.filter((u) => u.id !== this.unitId),
      maxVisibleCards: 6,
      onUnitClick: (u) => this.toggleSecondary(u.id),
      getCardState: (u) => this.secondaryRowState(u),
    });

    new ActionButtonList({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
      gapY: 5,
      buttons: [
        { label: "Back", onClick: () => this.scene.start("WarbandManagementScene") },
        {
          label: "Clear 2ndaries",
          onClick: () => {
            this.selectedSecondaryIds = [];
            this.secondaryPanel?.refreshCardStates();
            this.refreshStatus();
          },
        },
      ],
    });

    this.promoteButton?.destroy();
    this.promoteButton = new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 148,
      label: "Promote Unit",
      enabled: !this.activeRun && this.selectedSecondaryIds.length === 2,
      onClick: () => void this.promoteUnit(),
    });

    this.equipDiceButton?.destroy();
    this.unequipDiceButton?.destroy();
    this.equipDiceButton = new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 216,
      label: "Equip Selected Die",
      enabled: false,
      onClick: () => void this.equipSelectedDie(),
    });
    this.unequipDiceButton = new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 272,
      label: "Unequip Selected Die",
      enabled: false,
      onClick: () => void this.unequipSelectedDie(),
    });

    this.dicePanel?.destroy();
    const dicePanelY = layout.buttons.y + ACTION_BODY_TOP_OFFSET + 344;
    const dicePanelHeight = Math.max(180, layout.buttons.height - (dicePanelY - layout.buttons.y) - 14);
    this.dicePanel = new DiceCardGrid({
      scene: this,
      x: layout.buttons.x + 10,
      y: dicePanelY,
      width: layout.buttons.width - 20,
      height: dicePanelHeight,
      title: "DICE",
      dice: this.dice,
      selectedDiceId: this.selectedDiceId,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.dicePanel?.setSelectedDiceId(die.id);
        this.refreshStatus();
      },
    });

    this.statusText?.destroy();
    this.statusText = this.add.text(layout.content.x + 16, bodyTop + bodyHeight - 64, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#dddddd",
      wordWrap: { width: detailsWidth },
    });
    this.refreshStatus();
  }

  private secondaryRowState(unit: UnitRecord): UnitCardState {
    const selected = this.selectedSecondaryIds.includes(unit.id);
    const compatible = this.isPromotionCompatible(this.unitId, unit.id);
    return {
      highlighted: selected,
      disabled: !compatible || this.activeRun,
      badgeText: selected ? "SELECTED" : compatible ? null : "INCOMPATIBLE",
    };
  }

  private toggleSecondary(unitId: string): void {
    if (this.activeRun) return;
    if (!this.isPromotionCompatible(this.unitId, unitId)) return;
    if (this.selectedSecondaryIds.includes(unitId)) {
      this.selectedSecondaryIds = this.selectedSecondaryIds.filter((id) => id !== unitId);
    } else {
      if (this.selectedSecondaryIds.length === 2) this.selectedSecondaryIds.shift();
      this.selectedSecondaryIds.push(unitId);
    }
    this.secondaryPanel?.refreshCardStates();
    this.refreshStatus();
  }

  private async promoteUnit(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Promotion unavailable while a run is active.");
      return;
    }
    if (this.selectedSecondaryIds.length !== 2) {
      this.showToast("Select two compatible secondaries.");
      return;
    }
    const [a, b] = this.selectedSecondaryIds as [string, string];
    const res = await apiClient.promoteUnit(this.unitId, [a, b]);
    if (!res.ok) {
      this.showToast(`Promote failed: ${res.error.message}`);
      return;
    }
    this.showToast("Promotion applied.", "#ccffcc");
    this.selectedSecondaryIds = [];
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

  private refreshStatus(): void {
    const secondaries = this.selectedSecondaryIds.length > 0
      ? this.selectedSecondaryIds.map((id) => this.units.find((u) => u.id === id)?.name ?? id).join(", ")
      : "(none)";
    const selectedDie = this.getSelectedDie();
    const diceStatus = selectedDie
      ? (selectedDie.equipped?.unitId === this.unitId
          ? `${selectedDie.displayName} equipped on this unit.`
          : selectedDie.equipped
            ? `${selectedDie.displayName} equipped on ${selectedDie.equipped.unitName}.`
            : `${selectedDie.displayName} not equipped.`)
      : "No die selected.";
    const gate = this.activeRun
      ? "Promotion disabled while an active run exists."
      : "Select two compatible max-level units as secondaries.";
    this.statusText?.setText(`Primary: ${this.unit?.name ?? this.unitId}\nSecondaries: ${secondaries}\nDice: ${diceStatus}\n${gate}`);
    this.promoteButton?.setEnabled(!this.activeRun && this.selectedSecondaryIds.length === 2);
    this.refreshDiceActionButtons();
  }

  private getSelectedDie(): DiceDetailsViewModel | null {
    if (!this.selectedDiceId) {
      return null;
    }
    return this.dice.find((die) => die.id === this.selectedDiceId) ?? null;
  }

  private refreshDiceActionButtons(): void {
    const selectedDie = this.getSelectedDie();
    const canMutateDice = !this.activeRun;
    const canEquip = Boolean(selectedDie && canMutateDice && !selectedDie.equipped);
    const canUnequip = Boolean(
      selectedDie &&
      canMutateDice &&
      selectedDie.equipped &&
      selectedDie.equipped.unitId === this.unitId
    );

    this.equipDiceButton?.setEnabled(canEquip);
    this.unequipDiceButton?.setEnabled(canUnequip);
  }

  private async equipSelectedDie(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Dice changes unavailable while an active run exists.");
      return;
    }

    const selectedDie = this.getSelectedDie();
    if (!selectedDie) {
      this.showToast("Select a die first.");
      return;
    }
    if (selectedDie.equipped) {
      this.showToast(`Die already equipped on ${selectedDie.equipped.unitName}.`);
      return;
    }

    const res = await apiClient.equipDice(this.unitId, selectedDie.id);
    if (!res.ok) {
      this.showToast(`Equip failed: ${res.error.message}`);
      return;
    }

    this.showToast("Die equipped.", "#ccffcc");
    await this.loadData();
  }

  private async unequipSelectedDie(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Dice changes unavailable while an active run exists.");
      return;
    }

    const selectedDie = this.getSelectedDie();
    if (!selectedDie) {
      this.showToast("Select a die first.");
      return;
    }
    if (!selectedDie.equipped || selectedDie.equipped.unitId !== this.unitId) {
      this.showToast("Selected die is not equipped on this unit.");
      return;
    }

    const res = await apiClient.unequipDice(this.unitId, selectedDie.id);
    if (!res.ok) {
      this.showToast(`Unequip failed: ${res.error.message}`);
      return;
    }

    this.showToast("Die unequipped.", "#ccffcc");
    await this.loadData();
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






